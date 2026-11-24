<?php

namespace App\Http\Controllers\Admin\Retail;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\RetailSale;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\RetailService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('retail.view');
        $sales = RetailSale::with('customer', 'cashier', 'payments')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('cashier'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(40)->withQueryString();
        $cashiers = User::where('is_staff', true)->orderBy('name')->get();

        return view('admin.retail.sales', compact('sales', 'cashiers'));
    }

    public function show(RetailSale $sale, RetailService $retail)
    {
        $this->authorize('retail.view');
        $sale->load('items.product', 'payments.user', 'customer', 'cashier', 'documents', 'auditLogs.user', 'session');

        return view('admin.retail.sale', ['sale' => $sale, 'openSession' => $retail->currentSession($sale->session?->register ?? 'Caisse 1')]);
    }

    public function refund(Request $request, RetailSale $sale, RetailService $retail)
    {
        $this->authorize('retail.refund');
        $v = $request->validate(['qty' => 'required|array', 'qty.*' => 'nullable|numeric|min:0', 'method' => 'required|in:'.implode(',', config('hocreative.retail_payment_methods')),
            'restock' => 'boolean', 'reason' => 'required|string|max:300', 'key' => 'required|uuid']);
        $session = $retail->currentSession($sale->session?->register ?? 'Caisse 1');
        $p = $retail->refund($sale, array_filter($v['qty'], fn ($q) => (float) $q > 0), $v['method'], $request->boolean('restock'), $v['reason'], 'refund:'.$v['key'], $session);

        return back()->with('ok', 'Retour enregistré : '.money($p->amount).' remboursé ('.__('payment.'.$p->method).').');
    }

    public function invoice(Request $request, RetailSale $sale, DocumentService $docs)
    {
        $this->authorize('retail.sell');
        $customer = $request->input('customer_id') ? Customer::findOrFail($request->input('customer_id')) : null;
        if ($customer && ! $sale->customer_id) {
            $sale->update(['customer_id' => $customer->id]);
        }
        $doc = $docs->retailInvoice($sale, $customer);

        return redirect()->route('files.document', $doc);
    }
}
