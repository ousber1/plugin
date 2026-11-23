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
        return view('admin.products.index', ['items' => Product::latest()->paginate(25)]);
    }

    public function create()
    {
        return view('admin.products.form', ['item' => new Product(['status' => 'draft'])]);
    }

    public function store(Request $request, MediaService $media)
    {
        $this->save(new Product, $request, $media);

        return redirect()->route('admin.products.index')->with('success', 'تمت إضافة المنتج.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', ['item' => $product]);
    }

    public function update(Request $request, Product $product, MediaService $media)
    {
        $this->save($product, $request, $media);

        return redirect()->route('admin.products.index')->with('success', 'تم حفظ المنتج.');
    }

    public function destroy(Product $product, MediaService $media)
    {
        if (Order::where('product_id', $product->id)->exists()) {
            return back()->with('error', 'لا يمكن حذف منتج له طلبات. اجعله مسودة بدلًا من ذلك.');
        }
        $media->delete($product->disk, $product->file_path);
        $product->delete();

        return back()->with('success', 'تم حذف المنتج.');
    }

    private function save(Product $item, Request $request, MediaService $media): void
    {
        $data = $request->validate([
            ...$this->trRules('title', true, 190),
            ...$this->trRules('description', false, 50000),
            'price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'status' => ['required', 'in:draft,published'],
            'file' => [$item->exists ? 'nullable' : 'required', 'file', 'mimes:pdf,zip,epub,mp3,docx,xlsx,pptx', 'max:'.(config('academy.attachments.max_mb') * 1024)],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $item->fill([
            'title' => $this->trInput($request, 'title'), 'description' => $this->trInput($request, 'description', 'rich'),
            'price_cents' => (int) round($data['price'] * 100), 'status' => $data['status'],
        ]);
        $t = $data['title'];
        $item->slug = $item->slug ?: $this->uniqueSlug(Product::class, ($t['en'] ?? '') ?: (($t['fr'] ?? '') ?: $t['ar']));
        if ($request->hasFile('cover')) {
            if ($item->cover_path) {
                Storage::disk('public')->delete($item->cover_path);
            }
            $item->cover_path = \App\Support\ImageOptimizer::store($request->file('cover'), 'products');
        }
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
