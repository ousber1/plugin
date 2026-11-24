<?php

namespace App\Http\Controllers\Admin\Retail;

use App\Http\Controllers\Controller;
use App\Models\RetailCategory;
use App\Models\RetailProduct;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\RetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Store goods: catalogue, prices, barcodes, stock ledger. */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('retail.view');
        $products = RetailProduct::with('category', 'supplier')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")->orWhere('barcode', $s)))
            ->when($request->query('category'), fn ($q, $c) => $q->where('retail_category_id', $c))
            ->when($request->boolean('low'), fn ($q) => $q->where('track_stock', true)->whereColumn('stock_qty', '<=', 'low_stock_threshold'))
            ->orderBy('name')->paginate(50)->withQueryString();
        $value = RetailProduct::where('track_stock', true)->get()->sum(fn ($p) => max(0, (float) $p->stock_qty) * (float) $p->cost_price);

        return view('admin.retail.products', ['products' => $products, 'categories' => RetailCategory::orderBy('sort')->get(), 'stockValue' => $value,
            'lowCount' => RetailProduct::where('track_stock', true)->where('is_active', true)->whereColumn('stock_qty', '<=', 'low_stock_threshold')->count()]);
    }

    protected function form(RetailProduct $product)
    {
        return view('admin.retail.product-form', ['product' => $product, 'categories' => RetailCategory::orderBy('sort')->get(), 'suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function create()
    {
        $this->authorize('retail.manage');

        return $this->form(new RetailProduct(['tax_rate' => setting('retail.default_tax_rate', '20.00'), 'unit' => 'piece', 'track_stock' => true, 'is_active' => true, 'sku' => 'ART-'.Str::upper(Str::random(6))]));
    }

    public function edit(RetailProduct $product)
    {
        $this->authorize('retail.manage');

        return $this->form($product);
    }

    protected function data(Request $r, ?RetailProduct $p): array
    {
        $v = $r->validate([
            'name' => 'required|string|max:190', 'sku' => ['required', 'max:60', Rule::unique('retail_products', 'sku')->ignore($p?->id)],
            'barcode' => ['nullable', 'max:64', Rule::unique('retail_products', 'barcode')->ignore($p?->id)], 'retail_category_id' => 'nullable|exists:retail_categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id', 'unit' => 'required|in:piece,kg,m,l,pack,ream', 'price_ttc' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100', 'low_stock_threshold' => 'required|numeric|min:0', 'description' => 'nullable|string|max:2000',
            'image_file' => 'nullable|image|max:2048', 'initial_stock' => 'nullable|numeric|min:0', 'initial_cost' => 'nullable|numeric|min:0',
        ]);
        $data = collect($v)->except(['image_file', 'initial_stock', 'initial_cost'])->all();
        $data['track_stock'] = $r->boolean('track_stock');
        $data['is_active'] = $r->boolean('is_active');
        if ($r->hasFile('image_file')) {
            $data['image'] = '/storage/'.$r->file('image_file')->store('retail', 'public');
        }

        return $data;
    }

    public function store(Request $request, RetailService $retail)
    {
        $this->authorize('retail.manage');
        $p = RetailProduct::create($this->data($request, null) + ['stock_qty' => 0, 'cost_price' => 0]);
        if ((float) $request->input('initial_stock') > 0) {
            $retail->move($p, 'initial', (string) $request->input('initial_stock'), 'initial:'.$p->id, ['unit_cost' => (string) ($request->input('initial_cost') ?: 0), 'notes' => 'Stock initial']);
        }
        AuditLogger::log('retail.product_created', $p, null, ['price_ttc' => $p->price_ttc]);

        return redirect()->route('admin.retail.products.show', $p)->with('ok', 'Article créé.');
    }

    public function update(Request $request, RetailProduct $product)
    {
        $this->authorize('retail.manage');
        $old = $product->only(['price_ttc', 'tax_rate']);
        $product->update($this->data($request, $product));
        if ($old != $product->only(['price_ttc', 'tax_rate'])) {
            AuditLogger::log('retail.price_changed', $product, $old, $product->only(['price_ttc', 'tax_rate']));
        }

        return redirect()->route('admin.retail.products.show', $product)->with('ok', 'Article mis à jour.');
    }

    public function show(RetailProduct $product)
    {
        $this->authorize('retail.view');
        $movements = $product->movements()->with('user', 'sale')->latest('id')->paginate(40);
        $sold30 = (float) $product->movements()->where('type', 'sale')->where('created_at', '>=', now()->subDays(30))->sum('quantity') * -1;

        return view('admin.retail.product', compact('product', 'movements', 'sold30'));
    }

    public function adjust(Request $request, RetailProduct $product, RetailService $retail)
    {
        $this->authorize('retail.manage');
        $v = $request->validate(['mode' => 'required|in:count,loss,receipt', 'quantity' => 'required|numeric|min:0', 'unit_cost' => 'nullable|numeric|min:0', 'reason' => 'required|string|max:300', 'key' => 'required|uuid']);
        match ($v['mode']) {
            'count' => $retail->adjust($product, (string) $v['quantity'], $v['reason'], 'radj:'.$v['key']),
            'loss' => $retail->move($product, 'loss', (string) -$v['quantity'], 'rloss:'.$v['key'], ['notes' => $v['reason']]),
            'receipt' => $retail->move($product, 'purchase', (string) $v['quantity'], 'rrec:'.$v['key'], ['unit_cost' => (string) ($v['unit_cost'] ?? $product->cost_price), 'notes' => $v['reason']]),
        };
        if ($v['mode'] === 'loss') {
            AuditLogger::log('retail.stock_loss', $product, null, ['quantity' => $v['quantity']], $v['reason']);
        }

        return back()->with('ok', 'Stock mis à jour.');
    }
}
