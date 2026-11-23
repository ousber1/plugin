<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\EditorController;
use App\Models\Product;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function index()
    {
        $this->authorize('templates.manage');

        return view('admin.templates.index', ['templates' => Template::with('product')->orderBy('sort')->paginate(40)]);
    }

    public function create()
    {
        $this->authorize('templates.manage');

        return view('admin.templates.create', ['products' => Product::orderBy('slug')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize('templates.manage');
        $v = $request->validate(['name.fr' => 'required|max:190', 'name.ar' => 'nullable|max:190', 'product_id' => 'nullable|exists:products,id',
            'width_mm' => 'required|numeric|min:5|max:10000', 'height_mm' => 'required|numeric|min:5|max:10000', 'bleed_mm' => 'required|numeric|min:0|max:20',
            'safe_mm' => 'required|numeric|min:0|max:50', 'sides' => 'required|in:1,2']);
        $t = Template::create($v + ['slug' => Str::slug($v['name']['fr']).'-'.Str::lower(Str::random(5)), 'design' => ['front' => ['objects' => []]], 'is_published' => false]);

        return redirect()->route('admin.templates.edit', $t);
    }

    public function edit(Template $template)
    {
        $this->authorize('templates.manage');

        return view('store.editor', ['item' => null, 'design' => null, 'template' => $template, 'product' => $template->product, 'adminTemplate' => $template, 'config' => [
            'width_mm' => (float) $template->width_mm, 'height_mm' => (float) $template->height_mm, 'bleed_mm' => (float) $template->bleed_mm,
            'safe_mm' => (float) $template->safe_mm, 'sides' => (int) $template->sides, 'units_per_mm' => EditorController::UNITS_PER_MM,
            'data' => $template->design, 'uuid' => null, 'item' => null, 'template' => $template->id, 'product' => $template->product_id,
            'save_url' => route('admin.templates.update', $template), 'max_px' => 1600, 'dpi' => 150,
        ]]);
    }

    /** Receives the editor payload (JSON); a "final" save also refreshes the thumbnail. */
    public function update(Request $request, Template $template)
    {
        $this->authorize('templates.manage');
        if ($request->has('publish')) {
            $template->update(['is_published' => $request->boolean('publish')]);

            return back()->with('ok', 'Statut du modèle mis à jour.');
        }
        $v = $request->validate(['data' => 'required|array', 'exports' => 'nullable|array', 'final' => 'boolean']);
        $template->design = $v['data'];
        if (! empty($v['final']) && ! empty($v['exports']['front']) && preg_match('#^data:image/png;base64,(.+)$#', $v['exports']['front'], $m)) {
            $bin = base64_decode($m[1], true);
            if ($bin && ($img = @imagecreatefromstring($bin))) {
                $thumb = imagescale($img, 600);
                ob_start();
                imagepng($thumb, null, 7);
                $path = 'templates/'.$template->slug.'-'.time().'.png';
                Storage::disk('public')->put($path, ob_get_clean());
                $template->thumbnail = '/storage/'.$path;
            }
        }
        $template->save();

        return response()->json(['ok' => true, 'uuid' => null, 'redirect' => ! empty($v['final']) ? route('admin.templates.index') : null]);
    }
}
