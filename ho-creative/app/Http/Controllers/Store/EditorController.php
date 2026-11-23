<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Design;
use App\Models\Product;
use App\Models\Template;
use App\Services\CartService;
use App\Services\Pricing\PricingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Browser editor (Fabric.js). Design JSON is stored in "design units" of 4 units per mm.
 * Export = PNG raster per side at up to 300 dpi (RGB) + a PDF wrapping it at the exact
 * trim + bleed size. Not vector, not CMYK: always reviewed by prepress.
 */
class EditorController extends Controller
{
    public const UNITS_PER_MM = 4;

    protected function cartItem(Request $request): ?CartItem
    {
        if (! $request->input('item')) {
            return null;
        }
        $cart = app(CartService::class)->current();
        $item = CartItem::with('product', 'template', 'design')->find($request->input('item'));
        abort_unless($item && $cart && $item->cart_id === $cart->id, 404);

        return $item;
    }

    protected function ownsDesign(Request $request, Design $d): bool
    {
        return ($request->user() && $d->user_id === $request->user()->id)
            || in_array($d->id, (array) session('designs', []), true);
    }

    public function show(Request $request, PricingEngine $engine)
    {
        $item = $this->cartItem($request);
        $design = null;
        $template = null;
        $bleed = 3.0;
        $safe = 4.0;
        if ($item) {
            $design = $item->design;
            $template = $item->template;
            $price = $engine->forProduct($item->product, $item->selection);
            $w = (float) ($price->widthMm ?: 85);
            $h = (float) ($price->heightMm ?: 55);
            $sides = ($price->meta['two_sided'] ?? false) ? 2 : 1;
            $product = $item->product;
        } else {
            if ($request->query('design')) {
                $design = Design::where('uuid', $request->query('design'))->firstOrFail();
                abort_unless($this->ownsDesign($request, $design), 404);
            }
            $template = $request->query('template') ? Template::where('is_published', true)->find($request->query('template')) : $design?->template;
            $product = $design?->product ?? $template?->product ?? ($request->query('product') ? Product::where('slug', $request->query('product'))->first() : null);
            $w = (float) ($design->width_mm ?? $template->width_mm ?? 85);
            $h = (float) ($design->height_mm ?? $template->height_mm ?? 55);
            $sides = (int) ($design->sides ?? $template->sides ?? 1);
        }
        if ($template) {
            $bleed = (float) $template->bleed_mm;
            $safe = (float) $template->safe_mm;
        }
        $data = $design?->data ?? $this->scaleTemplate($template, $w, $h);

        return view('store.editor', [
            'item' => $item, 'design' => $design, 'template' => $template, 'product' => $product,
            'config' => [
                'width_mm' => $w, 'height_mm' => $h, 'bleed_mm' => $bleed, 'safe_mm' => $safe, 'sides' => $sides,
                'units_per_mm' => self::UNITS_PER_MM, 'data' => $data, 'uuid' => $design?->uuid, 'item' => $item?->id,
                'template' => $template?->id, 'product' => $product?->id, 'save_url' => route('editor.save'),
                'max_px' => 7000, 'dpi' => 300,
            ],
        ]);
    }

    /** Fit a template designed for another size into the requested size (uniform scale + centre). */
    protected function scaleTemplate(?Template $t, float $w, float $h): ?array
    {
        if (! $t) {
            return null;
        }
        $design = $t->design;
        if (abs((float) $t->width_mm - $w) < 0.5 && abs((float) $t->height_mm - $h) < 0.5) {
            return $design;
        }
        $sx = ($w + 2 * $t->bleed_mm) / ($t->width_mm + 2 * $t->bleed_mm);
        $sy = ($h + 2 * $t->bleed_mm) / ($t->height_mm + 2 * $t->bleed_mm);
        $s = min($sx, $sy);
        $dx = (($w + 2 * $t->bleed_mm) - ($t->width_mm + 2 * $t->bleed_mm) * $s) * self::UNITS_PER_MM / 2;
        $dy = (($h + 2 * $t->bleed_mm) - ($t->height_mm + 2 * $t->bleed_mm) * $s) * self::UNITS_PER_MM / 2;
        foreach ($design as $side => &$json) {
            foreach ($json['objects'] ?? [] as &$o) {
                $o['left'] = ($o['left'] ?? 0) * $s + $dx;
                $o['top'] = ($o['top'] ?? 0) * $s + $dy;
                $o['scaleX'] = ($o['scaleX'] ?? 1) * $s;
                $o['scaleY'] = ($o['scaleY'] ?? 1) * $s;
            }
        }

        return $design;
    }

    public function save(Request $request)
    {
        $v = $request->validate([
            'uuid' => 'nullable|uuid', 'item' => 'nullable|integer', 'template' => 'nullable|integer', 'product' => 'nullable|integer',
            'width_mm' => 'required|numeric|min:5|max:10000', 'height_mm' => 'required|numeric|min:5|max:10000',
            'bleed_mm' => 'required|numeric|min:0|max:20', 'safe_mm' => 'required|numeric|min:0|max:50', 'sides' => 'required|integer|in:1,2',
            'data' => 'required|array', 'exports' => 'nullable|array', 'exports.*' => 'string|max:40000000', 'final' => 'boolean',
        ]);
        $item = $this->cartItem($request);
        $design = $v['uuid'] ? Design::where('uuid', $v['uuid'])->first() : null;
        if ($design) {
            abort_unless($this->ownsDesign($request, $design), 403);
        } else {
            $design = new Design(['uuid' => (string) Str::uuid()]);
        }
        $encoded = json_encode($v['data']);
        if (strlen($encoded) > 15 * 1024 * 1024) {
            throw ValidationException::withMessages(['data' => 'Design trop volumineux (réduisez la taille des images importées).']);
        }
        $design->fill([
            'user_id' => $request->user()?->id, 'session_key' => $request->session()->getId(),
            'product_id' => $item?->product_id ?? $v['product'] ?? null, 'template_id' => $item?->template_id ?? $v['template'] ?? null,
            'width_mm' => $v['width_mm'], 'height_mm' => $v['height_mm'], 'bleed_mm' => $v['bleed_mm'], 'safe_mm' => $v['safe_mm'],
            'sides' => $v['sides'], 'data' => $v['data'], 'name' => $item?->product?->t('name'),
        ])->save();
        session()->put('designs', array_values(array_unique([...(array) session('designs', []), $design->id])));

        if (! empty($v['final']) && ! empty($v['exports'])) {
            $exports = [];
            foreach (['front', 'back'] as $side) {
                if (empty($v['exports'][$side])) {
                    continue;
                }
                if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $v['exports'][$side], $m)) {
                    throw ValidationException::withMessages(['exports' => 'Export invalide.']);
                }
                $bin = base64_decode($m[1], true);
                $info = $bin ? @getimagesizefromstring($bin) : false;
                if (! $info || $info[2] !== IMAGETYPE_PNG) {
                    throw ValidationException::withMessages(['exports' => 'Export invalide.']);
                }
                $path = "designs/{$design->uuid}/{$side}.png";
                Storage::disk('private')->put($path, $bin);
                $exports[$side] = $path;
                $exports[$side.'_px'] = [$info[0], $info[1]];
            }
            $exports['pdf'] = $this->pdf($design, $exports);
            $exports['generated_at'] = now()->toIso8601String();
            $design->update(['exports' => $exports]);
        }
        if ($item) {
            $item->update(['design_id' => $design->id]);
        }

        return response()->json(['ok' => true, 'uuid' => $design->uuid, 'redirect' => ! empty($v['final']) ? ($item ? route('cart') : route('account.designs')) : null]);
    }

    /** Wrap the PNG exports in a PDF at the exact trim + bleed size (one page per side). */
    protected function pdf(Design $d, array $exports): ?string
    {
        $W = (float) $d->width_mm + 2 * (float) $d->bleed_mm;
        $H = (float) $d->height_mm + 2 * (float) $d->bleed_mm;
        $tmp = storage_path('app/mpdf-tmp');
        @mkdir($tmp, 0775, true);
        $mpdf = new \Mpdf\Mpdf(['tempDir' => $tmp, 'format' => [$W, $H], 'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0, 'margin_header' => 0, 'margin_footer' => 0]);
        $mpdf->SetTitle('HO CREATIVE design '.$d->uuid);
        $first = true;
        foreach (['front', 'back'] as $side) {
            if (empty($exports[$side])) {
                continue;
            }
            if (! $first) {
                $mpdf->AddPage();
            }
            $mpdf->Image(Storage::disk('private')->path($exports[$side]), 0, 0, $W, $H, 'png', '', true, false);
            $first = false;
        }
        $path = "designs/{$d->uuid}/print.pdf";
        Storage::disk('private')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
