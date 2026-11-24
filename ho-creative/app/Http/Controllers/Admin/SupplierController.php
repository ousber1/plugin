<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/** Supplier file: purchases, goods supplied, payments made and balance owed. */
class SupplierController extends Controller
{
    public function index()
    {
        $this->authorize('suppliers.manage');
        $suppliers = Supplier::withCount('purchaseOrders')->orderBy('name')->get()->map(fn ($s) => [$s, $s->balance()]);

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('suppliers.manage');
        $supplier->load(['purchaseOrders' => fn ($q) => $q->latest()->with('items'), 'payments.user', 'retailProducts', 'materials']);

        return view('admin.suppliers.show', ['supplier' => $supplier, 'balance' => $supplier->balance()]);
    }

    public function payment(Request $request, Supplier $supplier)
    {
        $this->authorize('purchasing.manage');
        $v = $request->validate(['amount' => 'required|numeric|min:0.01', 'method' => 'required|in:bank_transfer,cheque,cash,card_in_store', 'reference' => 'nullable|string|max:120',
            'paid_at' => 'required|date', 'purchase_order_id' => 'nullable|exists:purchase_orders,id', 'notes' => 'nullable|string|max:500', 'key' => 'required|uuid']);
        $p = SupplierPayment::firstOrCreate(['idempotency_key' => 'sp:'.$v['key']], collect($v)->except('key')->all() + ['supplier_id' => $supplier->id, 'user_id' => auth()->id()]);
        if ($p->wasRecentlyCreated) {
            AuditLogger::log('supplier.payment', $supplier, null, ['amount' => $p->amount, 'method' => $p->method]);
        }

        return back()->with('ok', 'Règlement fournisseur enregistré.');
    }
}
