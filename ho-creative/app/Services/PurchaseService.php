<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(protected StockService $stock, protected ProductionService $production) {}

    public function recalc(PurchaseOrder $po): void
    {
        $po->update(['total' => Money::str(Money::sum($po->items()->get()->map(fn ($i) => Money::of($i->quantity)->multipliedBy($i->unit_cost))))]);
    }

    /**
     * Partial or full receipt. $quantities = [item_id => qty received now].
     * $receiptKey makes the whole receipt idempotent.
     */
    public function receive(PurchaseOrder $po, array $quantities, string $receiptKey): void
    {
        if (in_array($po->status, ['cancelled', 'received'])) {
            throw ValidationException::withMessages(['status' => 'Ce bon de commande est clôturé.']);
        }
        DB::transaction(function () use ($po, $quantities, $receiptKey) {
            foreach ($po->items as $item) {
                $q = Money::of($quantities[$item->id] ?? 0);
                if (! $q->isPositive()) {
                    continue;
                }
                $remaining = Money::of($item->quantity)->minus($item->quantity_received);
                if ($q->isGreaterThan($remaining)) {
                    throw ValidationException::withMessages(['qty' => "Quantité reçue supérieure au reliquat pour « {$item->description} »."]);
                }
                $key = "po-receipt:{$receiptKey}:item:{$item->id}";
                if (\App\Models\StockMovement::where('idempotency_key', $key)->exists() || \App\Models\JobCost::where('idempotency_key', $key)->exists()) {
                    continue; // replayed receipt
                }
                if ($item->material_id) {
                    $this->stock->move($item->material, 'receipt', (string) $q, $key, ['unit_cost' => $item->unit_cost, 'purchase_order_item_id' => $item->id, 'notes' => 'Réception '.$po->number]);
                }
                if ($item->production_job_id) {
                    $this->production->addCost($item->job, ['type' => 'outsourced', 'description' => 'Sous-traitance '.$po->number.' – '.$item->description, 'quantity' => (string) $q, 'unit_cost' => $item->unit_cost, 'idempotency_key' => $key]);
                }
                $item->update(['quantity_received' => (string) Money::of($item->quantity_received)->plus($q)]);
            }
            $po->refresh();
            $done = $po->items->every(fn ($i) => Money::of($i->quantity_received)->isGreaterThanOrEqualTo($i->quantity));
            $any = $po->items->contains(fn ($i) => Money::of($i->quantity_received)->isPositive());
            $po->update(['status' => $done ? 'received' : ($any ? 'partial' : $po->status)]);
            AuditLogger::log('purchase.receipt', $po, null, ['quantities' => $quantities, 'status' => $po->status]);
        });
    }
}
