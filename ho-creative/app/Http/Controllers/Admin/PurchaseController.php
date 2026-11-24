<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\ProductionJob;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use App\Services\NumberGenerator;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index()
    {
        $this->authorize('purchasing.manage');

        return view('admin.po.index', ['pos' => PurchaseOrder::with('supplier')->latest()->paginate(40)]);
    }

    public function create()
    {
        $this->authorize('purchasing.manage');

        return view('admin.po.create', ['suppliers' => Supplier::orderBy('name')->get(), 'materials' => Material::where('is_active', true)->orderBy('name')->get(),
            'jobs' => ProductionJob::whereNotIn('stage', ['delivered', 'cancelled'])->latest()->limit(100)->get(),
            'retailProducts' => \App\Models\RetailProduct::where('is_active', true)->orderBy('name')->get(), 'supplierId' => request('supplier')]);
    }

    public function store(Request $request, NumberGenerator $numbers, PurchaseService $purchases)
    {
        $this->authorize('purchasing.manage');
        $v = $request->validate(['supplier_id' => 'required|exists:suppliers,id', 'expected_at' => 'nullable|date', 'notes' => 'nullable|string|max:2000',
            'supplier_reference' => 'nullable|string|max:120',
            'items' => 'required|array|min:1', 'items.*.material_id' => 'nullable|exists:materials,id', 'items.*.retail_product_id' => 'nullable|exists:retail_products,id', 'items.*.production_job_id' => 'nullable|exists:production_jobs,id',
            'items.*.description' => 'nullable|string|max:190', 'items.*.quantity' => 'required|numeric|min:0.001', 'items.*.unit_cost' => 'required|numeric|min:0']);
        $po = DB::transaction(function () use ($v, $numbers, $purchases) {
            $po = PurchaseOrder::create(['number' => $numbers->next('purchase_order'), 'supplier_id' => $v['supplier_id'], 'supplier_reference' => $v['supplier_reference'] ?? null, 'expected_at' => $v['expected_at'] ?? null, 'notes' => $v['notes'] ?? null, 'created_by' => auth()->id()]);
            foreach ($v['items'] as $it) {
                $desc = ($it['description'] ?? null) ?: (! empty($it['retail_product_id']) ? \App\Models\RetailProduct::find($it['retail_product_id'])->name : (! empty($it['material_id']) ? Material::find($it['material_id'])->name : 'Sous-traitance'));
                $po->items()->create(['material_id' => $it['material_id'] ?? null, 'retail_product_id' => $it['retail_product_id'] ?? null, 'production_job_id' => $it['production_job_id'] ?? null, 'description' => $desc, 'quantity' => $it['quantity'], 'unit_cost' => $it['unit_cost']]);
            }
            $purchases->recalc($po);
            AuditLogger::log('purchase.created', $po, null, ['total' => $po->fresh()->total]);

            return $po;
        });

        return redirect()->route('admin.po.show', $po)->with('ok', 'Bon de commande créé.');
    }

    public function show(PurchaseOrder $po)
    {
        $this->authorize('purchasing.manage');
        $po->load('supplier', 'items.material', 'items.job');
        $docs = \App\Models\Document::where('purchase_order_id', $po->id)->latest()->get();

        return view('admin.po.show', compact('po', 'docs'));
    }

    public function status(Request $request, PurchaseOrder $po)
    {
        $this->authorize('purchasing.manage');
        $s = $request->validate(['status' => 'required|in:sent,cancelled'])['status'];
        abort_if(in_array($po->status, ['received', 'cancelled']), 422);
        $old = $po->status;
        $po->update(['status' => $s]);
        AuditLogger::log('purchase.status', $po, ['status' => $old], ['status' => $s]);

        return back()->with('ok', 'Statut mis à jour.');
    }

    public function receive(Request $request, PurchaseOrder $po, PurchaseService $purchases)
    {
        $this->authorize('purchasing.manage');
        $v = $request->validate(['qty' => 'required|array', 'qty.*' => 'nullable|numeric|min:0', 'key' => 'required|uuid']);
        $purchases->receive($po, array_filter($v['qty'], fn ($q) => (float) $q > 0), $v['key']);

        return back()->with('ok', 'Réception enregistrée.');
    }

    public function document(PurchaseOrder $po, DocumentService $docs)
    {
        $this->authorize('purchasing.manage');
        $doc = $docs->purchaseOrder($po);

        return redirect()->route('files.document', $doc);
    }
}
