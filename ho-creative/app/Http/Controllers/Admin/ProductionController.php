<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Material;
use App\Models\ProductionJob;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProductionService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductionController extends Controller
{
    public function __construct(protected ProductionService $production) {}

    protected function filtered(Request $request)
    {
        return ProductionJob::with('order.customer', 'item.proofs', 'assignee', 'machine')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%$s%"))))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department', $d))
            ->when($request->query('assigned'), fn ($q, $a) => $q->where('assigned_to', $a))
            ->when($request->query('stage'), fn ($q, $s) => $q->where('stage', $s));
    }

    public function board(Request $request)
    {
        $this->authorize('production.view');
        $jobs = $this->filtered($request)->where(fn ($q) => $q->whereNotIn('stage', ['delivered', 'cancelled'])->orWhere('updated_at', '>', now()->subDays(3)))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->orderBy('due_at')->get()->groupBy('stage');
        $staff = User::where('is_staff', true)->orderBy('name')->get();

        return view('admin.production.board', ['jobs' => $jobs, 'stages' => ProductionService::STAGES, 'staff' => $staff]);
    }

    public function list(Request $request)
    {
        $this->authorize('production.view');
        $jobs = $this->filtered($request)->latest()->paginate(50)->withQueryString();
        $staff = User::where('is_staff', true)->orderBy('name')->get();

        return view('admin.production.list', compact('jobs', 'staff'));
    }

    public function show(ProductionJob $job)
    {
        $this->authorize('production.view');
        $job->load('order.customer', 'item.artworkFiles', 'item.proofs', 'item.design', 'item.remakeOf', 'events.user', 'costs.user', 'stockMovements.material', 'assignee', 'machine');

        return view('admin.production.show', [
            'job' => $job, 'stages' => ProductionService::STAGES, 'summary' => $this->production->costSummary($job),
            'staff' => User::where('is_staff', true)->orderBy('name')->get(), 'machines' => Machine::where('is_active', true)->get(),
            'materials' => Material::where('is_active', true)->orderBy('name')->get(), 'blockers' => $this->production->blockers($job, 'printing'),
        ]);
    }

    public function sheet(ProductionJob $job)
    {
        $this->authorize('production.view');
        $job->load('order.customer', 'item.artworkFiles', 'item.proofs', 'item.design', 'assignee', 'machine');
        $approved = $job->item->proofs->where('status', 'approved')->sortByDesc('version')->first();

        return view('admin.production.sheet', compact('job', 'approved'));
    }

    public function transition(Request $request, ProductionJob $job)
    {
        $this->authorize('production.manage');
        $v = $request->validate(['stage' => 'required|in:'.implode(',', ProductionService::STAGES), 'note' => 'nullable|string|max:500']);
        if ($v['stage'] === 'cancelled' && empty($v['note']) && ! $request->expectsJson()) {
            throw ValidationException::withMessages(['note' => 'Motif requis pour annuler un travail.']);
        }
        $this->production->transition($job, $v['stage'], $v['note'] ?? null);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Étape mise à jour : '.__('stage.'.$v['stage']));
    }

    public function update(Request $request, ProductionJob $job)
    {
        $this->authorize('production.manage');
        $v = $request->validate(['assigned_to' => 'nullable|exists:users,id', 'department' => 'nullable|string|max:40', 'machine_id' => 'nullable|exists:machines,id',
            'due_at' => 'nullable|date', 'priority' => 'required|in:low,normal,high,urgent', 'internal_notes' => 'nullable|string|max:5000']);
        $old = $job->only(array_keys($v));
        $job->update($v);
        AuditLogger::log('production.updated', $job, $old, $job->only(array_keys($v)));

        return back()->with('ok', 'Travail mis à jour.');
    }

    public function cost(Request $request, ProductionJob $job)
    {
        $this->authorize('production.manage');
        $v = $request->validate(['type' => 'required|in:labor,setup,machine,outsourced,other', 'description' => 'required|string|max:190',
            'quantity' => 'required|numeric|min:0', 'unit_cost' => 'required|numeric|min:0', 'is_estimate' => 'boolean', 'key' => 'required|uuid']);
        $this->production->addCost($job, $v + ['is_estimate' => $request->boolean('is_estimate'), 'idempotency_key' => 'cost:'.$v['key']]);

        return back()->with('ok', 'Coût enregistré.');
    }

    public function material(Request $request, ProductionJob $job, StockService $stock)
    {
        $this->authorize('stock.consume');
        $v = $request->validate(['material_id' => 'required|exists:materials,id', 'action' => 'required|in:reserve,consume,waste,release',
            'quantity' => 'required|numeric|min:0.001', 'notes' => 'nullable|string|max:300', 'key' => 'required|uuid']);
        $m = Material::findOrFail($v['material_id']);
        $key = 'job:'.$job->id.':'.$v['action'].':'.$v['key'];
        match ($v['action']) {
            'reserve' => $stock->reserve($m, (string) $v['quantity'], $job, $key),
            'consume' => $stock->consume($m, (string) $v['quantity'], $job, $key, 'consumption', $v['notes'] ?? null),
            'waste' => $stock->consume($m, (string) $v['quantity'], $job, $key, 'waste', $v['notes'] ?? null),
            'release' => $stock->move($m, 'release', (string) -min((float) $v['quantity'], (float) $stock->outstandingReservation($m, $job)), $key, ['production_job_id' => $job->id, 'notes' => 'Libération manuelle']),
        };

        return back()->with('ok', 'Mouvement de stock enregistré.');
    }
}
