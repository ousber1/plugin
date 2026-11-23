<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('catalog.view');
        $products = Product::with('category', 'publishedRuleSet')
            ->when($request->query('q'), fn ($q, $s) => $q->where('search_text', 'like', '%'.mb_strtolower($s).'%'))
            ->when($request->query('mode'), fn ($q, $m) => $q->where('sale_mode', $m))
            ->orderBy('category_id')->orderBy('sort')->paginate(50)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $this->authorize('catalog.manage');

        return view('admin.products.form', ['product' => new Product(['sale_mode' => 'unpublished', 'artwork_modes' => ['upload', 'later'], 'requires_proof' => true]), 'categories' => Category::orderBy('sort')->get()]);
    }

    public function edit(Product $product)
    {
        $this->authorize('catalog.manage');

        return view('admin.products.form', ['product' => $product, 'categories' => Category::orderBy('sort')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize('catalog.manage');
        $product = Product::create($this->data($request, null));
        AuditLogger::log('product.created', $product, null, ['slug' => $product->slug]);

        return redirect()->route('admin.pricing.edit', $product)->with('ok', 'Produit créé. Configurez maintenant ses options et ses prix.');
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('catalog.manage');
        $old = $product->only(['sale_mode', 'design_fee']);
        $product->update($this->data($request, $product));
        if ($old !== $product->only(['sale_mode', 'design_fee'])) {
            AuditLogger::log('product.updated', $product, $old, $product->only(['sale_mode', 'design_fee']));
        }

        return back()->with('ok', 'Produit enregistré.');
    }

    /** "FR | AR" per line → [{fr, ar}] ; "a | b | c | d" → pairs */
    public static function lines(?string $text, int $cols): array
    {
        $out = [];
        foreach (preg_split('/\R/', (string) $text) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $p = array_map('trim', explode('|', $line)) + array_fill(0, $cols, '');
            $out[] = $p;
        }

        return $out;
    }

    public static function toLines(?array $items, string $kind): string
    {
        return collect($items ?? [])->map(fn ($i) => match ($kind) {
            'finishes' => ($i['fr'] ?? '').' | '.($i['ar'] ?? ''),
            'specs' => ($i['label']['fr'] ?? '').' | '.($i['value']['fr'] ?? '').' | '.($i['label']['ar'] ?? '').' | '.($i['value']['ar'] ?? ''),
            'faq' => ($i['q']['fr'] ?? '').' | '.($i['a']['fr'] ?? '').' | '.($i['q']['ar'] ?? '').' | '.($i['a']['ar'] ?? ''),
        })->implode("\n");
    }

    protected function data(Request $request, ?Product $product): array
    {
        $v = $request->validate([
            'category_id' => 'required|exists:categories,id', 'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['nullable', 'max:60', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name.fr' => 'required|string|max:190', 'name.ar' => 'nullable|string|max:190', 'summary.fr' => 'nullable|string|max:500', 'summary.ar' => 'nullable|string|max:500',
            'description.fr' => 'nullable|string|max:20000', 'description.ar' => 'nullable|string|max:20000',
            'sale_mode' => 'required|in:'.implode(',', Product::MODES), 'artwork_modes' => 'nullable|array', 'artwork_modes.*' => 'in:'.implode(',', Product::ARTWORK_MODES),
            'design_fee' => 'required|numeric|min:0', 'sort' => 'nullable|integer|min:0',
            'specs_text' => 'nullable|string|max:10000', 'finishes_text' => 'nullable|string|max:5000', 'faq_text' => 'nullable|string|max:20000',
            'meta_title.fr' => 'nullable|max:190', 'meta_title.ar' => 'nullable|max:190', 'meta_description.fr' => 'nullable|max:300', 'meta_description.ar' => 'nullable|max:300',
            'images_keep' => 'nullable|array', 'images_new' => 'nullable|array', 'images_new.*' => 'image|max:4096',
            'guides_keep' => 'nullable|array', 'guides_new' => 'nullable|array', 'guides_new.*' => 'file|mimes:pdf|max:20480',
        ]);
        $images = array_values(array_filter((array) ($v['images_keep'] ?? [])));
        foreach ((array) $request->file('images_new', []) as $img) {
            $images[] = '/storage/'.$img->store('products', 'public');
        }
        $guides = collect($product?->guides ?? [])->filter(fn ($g) => in_array($g['path'], (array) ($v['guides_keep'] ?? [])))->values()->all();
        foreach ((array) $request->file('guides_new', []) as $g) {
            $guides[] = ['title' => ['fr' => pathinfo($g->getClientOriginalName(), PATHINFO_FILENAME), 'ar' => ''], 'path' => '/storage/'.$g->store('guides', 'public')];
        }

        return [
            'category_id' => $v['category_id'], 'slug' => $v['slug'], 'sku' => $v['sku'] ?? null,
            'name' => $v['name'], 'summary' => $v['summary'] ?? null, 'description' => $v['description'] ?? null,
            'sale_mode' => $v['sale_mode'], 'artwork_modes' => array_values($v['artwork_modes'] ?? []), 'design_fee' => $v['design_fee'],
            'requires_proof' => $request->boolean('requires_proof'), 'is_popular' => $request->boolean('is_popular'), 'sort' => $v['sort'] ?? 0,
            'specs' => array_map(fn ($p) => ['label' => ['fr' => $p[0], 'ar' => $p[2]], 'value' => ['fr' => $p[1], 'ar' => $p[3]]], self::lines($v['specs_text'] ?? '', 4)),
            'finishes' => array_map(fn ($p) => ['fr' => $p[0], 'ar' => $p[1]], self::lines($v['finishes_text'] ?? '', 2)),
            'faq' => array_map(fn ($p) => ['q' => ['fr' => $p[0], 'ar' => $p[2]], 'a' => ['fr' => $p[1], 'ar' => $p[3]]], self::lines($v['faq_text'] ?? '', 4)),
            'meta_title' => $v['meta_title'] ?? null, 'meta_description' => $v['meta_description'] ?? null,
            'images' => $images, 'guides' => $guides,
        ];
    }
}
