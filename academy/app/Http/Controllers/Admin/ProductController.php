<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends AdminController
{
    public function index()
    {
        $items = Product::with('category')->withCount(['orderItems as sales_count' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', 'paid'))])
            ->withSum(['orderItems as revenue' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', 'paid')->where('currency', 'MAD'))], 'price_cents')
            ->orderBy('sort_order')->latest()->paginate(25);

        return view('admin.products.index', ['items' => $items]);
    }

    public function create()
    {
        return view('admin.products.form', ['item' => new Product(['status' => 'draft', 'billing' => 'once', 'license_sites' => 1, 'file_format' => 'ZIP']), 'categories' => $this->categories()]);
    }

    public function store(Request $request, MediaService $media)
    {
        $this->save(new Product, $request, $media);

        return redirect()->route('admin.products.index')->with('success', 'تمت إضافة المنتج.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', ['item' => $product, 'categories' => $this->categories()]);
    }

    public function update(Request $request, Product $product, MediaService $media)
    {
        $this->save($product, $request, $media);

        return redirect()->route('admin.products.index')->with('success', 'تم حفظ المنتج.');
    }

    public function destroy(Product $product, MediaService $media)
    {
        if (Order::where('product_id', $product->id)->exists() || $product->orderItems()->exists()) {
            return back()->with('error', 'لا يمكن حذف منتج له طلبات. اجعله مسودة بدلًا من ذلك.');
        }
        $media->delete($product->disk, $product->file_path);
        $product->delete();

        return back()->with('success', 'تم حذف المنتج.');
    }

    private function categories()
    {
        return \App\Models\Category::ofType('product')->get();
    }

    private function save(Product $item, Request $request, MediaService $media): void
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:1000000'];
        $data = $request->validate([
            ...$this->trRules('title', true, 190), ...$this->trRules('subtitle', false, 190),
            ...$this->trRules('description', false, 50000), ...$this->trRules('features', false, 5000), ...$this->trRules('requirements', false, 5000),
            ...$this->trRules('seo_title', false, 190), ...$this->trRules('seo_description', false, 320),
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:1', 'max:1000000'], 'compare_price' => $money,
            'price_usd' => $money, 'compare_price_usd' => $money,
            'billing' => ['required', 'in:once,year,month'], 'license_sites' => ['required', 'integer', 'between:1,1000'],
            'version' => ['nullable', 'string', 'max:30'], 'requires' => ['nullable', 'string', 'max:60'], 'tested_up_to' => ['nullable', 'string', 'max:60'],
            'file_format' => ['nullable', 'string', 'max:20'], 'files_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'demo_url' => ['nullable', 'url', 'max:500', 'regex:/^https?:\/\//'], 'updated_on' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:draft,published'],
            'changelog' => ['nullable', 'array', 'max:50'], 'changelog.*.version' => ['nullable', 'string', 'max:30'],
            'changelog.*.date' => ['nullable', 'string', 'max:30'], 'changelog.*.notes' => ['nullable', 'string', 'max:3000'],
            'file' => ['nullable', 'file', 'mimes:pdf,zip,epub,mp3,docx,xlsx,pptx', 'max:'.(config('academy.attachments.max_mb') * 1024)],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery_add' => ['nullable', 'array', 'max:12'], 'gallery_add.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery_remove' => ['nullable', 'array'],
        ]);
        $cents = fn ($v) => $v === null || $v === '' ? null : (int) round($v * 100);
        $item->fill([
            'title' => $this->trInput($request, 'title'), 'subtitle' => $this->trInput($request, 'subtitle'),
            'description' => $this->trInput($request, 'description', 'rich'),
            'features' => $this->trInput($request, 'features', 'list'), 'requirements' => $this->trInput($request, 'requirements', 'list'),
            'seo_title' => $this->trInput($request, 'seo_title'), 'seo_description' => $this->trInput($request, 'seo_description'),
            'category_id' => $data['category_id'] ?? null,
            'price_cents' => $cents($data['price']), 'compare_price_cents' => $cents($data['compare_price'] ?? null),
            'price_usd_cents' => $cents($data['price_usd'] ?? null), 'compare_price_usd_cents' => $cents($data['compare_price_usd'] ?? null),
            'billing' => $data['billing'], 'license_sites' => (int) $data['license_sites'],
            'version' => $data['version'] ?? null, 'requires' => $data['requires'] ?? null, 'tested_up_to' => $data['tested_up_to'] ?? null,
            'file_format' => $data['file_format'] ?? null, 'files_count' => $data['files_count'] ?? null,
            'demo_url' => $data['demo_url'] ?? null, 'updated_on' => $data['updated_on'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_featured' => $request->boolean('is_featured'), 'status' => $data['status'],
            'changelog' => collect($data['changelog'] ?? [])->filter(fn ($e) => trim((string) ($e['version'] ?? '')) !== '')
                ->map(fn ($e) => ['version' => trim($e['version']), 'date' => trim((string) ($e['date'] ?? '')), 'notes' => trim((string) ($e['notes'] ?? ''))])->values()->all(),
        ]);
        $t = $data['title'];
        $item->slug = $item->slug ?: $this->uniqueSlug(Product::class, ($t['en'] ?? '') ?: (($t['fr'] ?? '') ?: $t['ar']));
        if (! $item->exists && ! $request->hasFile('file')) {
            // A product can be published without a file (e.g. a service delivered by WhatsApp).
        }
        if ($request->hasFile('cover')) {
            if ($item->cover_path) {
                Storage::disk('public')->delete($item->cover_path);
            }
            $item->cover_path = \App\Support\ImageOptimizer::store($request->file('cover'), 'products');
        }
        $gallery = collect($item->gallery ?: []);
        $remove = (array) $request->input('gallery_remove', []);
        foreach ($gallery->intersect($remove) as $path) {
            Storage::disk('public')->delete($path);
        }
        $gallery = $gallery->diff($remove)->values();
        foreach ($request->file('gallery_add', []) as $img) {
            $gallery->push(\App\Support\ImageOptimizer::store($img, 'products/gallery'));
        }
        $item->gallery = $gallery->take(12)->values()->all();
        if ($request->hasFile('file')) {
            $stored = $media->storeUpload($request->file('file'), 'products');
            $old = [$item->disk, $item->file_path];
            $item->disk = $stored['disk'];
            $item->file_path = $stored['path'];
            $item->original_name = $stored['original_name'];
            $item->save();
            $media->delete($old[0], $old[1]);
        } else {
            $item->save();
        }
    }
}
