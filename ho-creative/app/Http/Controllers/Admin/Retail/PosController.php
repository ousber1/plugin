<?php

namespace App\Http\Controllers\Admin\Retail;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\RetailCategory;
use App\Models\RetailProduct;
use App\Models\RetailSale;
use App\Services\RetailService;
use App\Support\Money;
use Illuminate\Http\Request;

/** Point-of-sale screen (touch & barcode-scanner friendly). */
class PosController extends Controller
{
    public function __construct(protected RetailService $retail) {}

    protected function register(Request $r): string
    {
        $regs = (array) setting('retail.registers', ['Caisse 1']);
        $reg = (string) $r->input('register', session('pos_register', $regs[0] ?? 'Caisse 1'));

        return in_array($reg, $regs, true) ? $reg : ($regs[0] ?? 'Caisse 1');
    }

    public function index(Request $request)
    {
        $this->authorize('retail.sell');
        $register = $this->register($request);
        session(['pos_register' => $register]);
        $session = $this->retail->currentSession($register);
        $categories = RetailCategory::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'color']);
        $products = RetailProduct::where('is_active', true)->orderBy('name')->limit(2000)->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'barcode' => $p->barcode, 'price' => $p->price_ttc,
                'tax' => $p->tax_rate, 'stock' => $p->track_stock ? (float) $p->stock_qty : null, 'unit' => $p->unit, 'cat' => $p->retail_category_id, 'image' => $p->image]);

        return view('admin.retail.pos', ['register' => $register, 'registers' => (array) setting('retail.registers', ['Caisse 1']), 'session' => $session,
            'categories' => $categories, 'products' => $products, 'summary' => $session ? $this->retail->sessionSummary($session) : null]);
    }

    public function open(Request $request)
    {
        $this->authorize('retail.cash');
        $v = $request->validate(['register' => 'required|string|max:40', 'opening_float' => 'required|numeric|min:0']);
        $this->retail->openSession($this->register($request), (string) $v['opening_float'], $request->user());

        return redirect()->route('admin.pos')->with('ok', 'Caisse ouverte. Bonnes ventes !');
    }

    public function sell(Request $request)
    {
        $this->authorize('retail.sell');
        $v = $request->validate([
            'key' => 'required|uuid', 'register' => 'required|string|max:40', 'customer_id' => 'nullable|exists:customers,id',
            'lines' => 'required|array|min:1', 'lines.*.product_id' => 'required|integer', 'lines.*.quantity' => 'required|numeric|min:0.001|max:100000',
            'lines.*.discount' => 'nullable|numeric|min:0', 'discount_reason' => 'nullable|string|max:190',
            'payments' => 'required|array|min:1', 'payments.*.method' => 'required|string', 'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:120', 'notes' => 'nullable|string|max:500',
        ]);
        $session = $this->retail->currentSession($this->register($request));
        abort_unless($session, 422, 'Caisse fermée.');
        $sale = $this->retail->sell($session, $v['lines'], $v['payments'], $v['key'], ! empty($v['customer_id']) ? Customer::find($v['customer_id']) : null, $v['discount_reason'] ?? null, $v['notes'] ?? null);

        return response()->json(['ok' => true, 'number' => $sale->number, 'total' => Money::format($sale->total_ttc), 'change' => Money::format($sale->change_given),
            'ticket_url' => route('admin.retail.ticket', $sale), 'sale_url' => route('admin.retail.sales.show', $sale)]);
    }

    public function customers(Request $request)
    {
        $this->authorize('retail.sell');
        $q = trim((string) $request->query('q'));
        $list = Customer::query()->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('company_name', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")->orWhere('ice', 'like', "%$q%")))
            ->orderBy('name')->limit(15)->get()->map(fn ($c) => ['id' => $c->id, 'label' => $c->displayName(), 'phone' => $c->phone, 'ice' => $c->ice]);

        return response()->json($list);
    }

    public function quickCustomer(Request $request)
    {
        $this->authorize('retail.sell');
        $v = $request->validate(['name' => 'required|string|max:120', 'phone' => 'nullable|string|max:40', 'company_name' => 'nullable|string|max:190', 'ice' => 'nullable|string|max:30', 'email' => 'nullable|email']);
        $c = Customer::create($v + ['type' => ! empty($v['company_name']) ? 'company' : 'individual', 'source' => 'walk_in']);

        return response()->json(['id' => $c->id, 'label' => $c->displayName(), 'phone' => $c->phone]);
    }

    public function ticket(RetailSale $sale)
    {
        $this->authorize('retail.sell');
        $sale->load('items', 'payments', 'customer', 'cashier');

        return view('admin.retail.ticket', compact('sale'));
    }
}
