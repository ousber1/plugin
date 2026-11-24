<?php

namespace App\Services;

use App\Models\Material;
use App\Models\ProductionJob;
use App\Models\StockMovement;
use App\Support\Money;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock ledger. Every movement carries a unique idempotency key so a retried
 * request (double submit, network retry) never moves stock twice.
 *
 * Sign convention of `quantity`:
 *   receipt +, consumption −, waste −, adjustment ±  → affect stock_qty
 *   reservation +, release −                          → affect reserved_qty only
 */
class StockService
{
    public function move(Material $material, string $type, string $qty, string $key, array $extra = []): StockMovement
    {
        if ($existing = StockMovement::where('idempotency_key', $key)->first()) {
            return $existing;
        }
        try {
            return DB::transaction(function () use ($material, $type, $qty, $key, $extra) {
                $m = Material::whereKey($material->id)->lockForUpdate()->first();
                $q = Money::of($qty)->toScale(3, RoundingMode::HalfUp);
                $movement = StockMovement::create([
                    'material_id' => $m->id, 'type' => $type, 'quantity' => (string) $q,
                    'unit_cost' => $extra['unit_cost'] ?? $m->unit_cost,
                    'production_job_id' => $extra['production_job_id'] ?? null,
                    'purchase_order_item_id' => $extra['purchase_order_item_id'] ?? null,
                    'idempotency_key' => $key, 'user_id' => auth()->id(), 'notes' => $extra['notes'] ?? null,
                ]);
                if (in_array($type, ['reservation', 'release'])) {
                    $reserved = Money::of($m->reserved_qty)->plus($q);
                    $m->reserved_qty = (string) Money::max($reserved, 0)->toScale(3);
                } else {
                    $newStock = Money::of($m->stock_qty)->plus($q);
                    if ($type === 'receipt' && $q->isPositive()) {
                        // weighted average unit cost
                        $oldValue = Money::max($m->stock_qty, 0)->multipliedBy($m->unit_cost);
                        $inValue = $q->multipliedBy(Money::of($extra['unit_cost'] ?? $m->unit_cost));
                        if ($newStock->isPositive()) {
                            $m->unit_cost = (string) $oldValue->plus($inValue)->dividedBy($newStock, 4, RoundingMode::HalfUp);
                        }
                    }
                    $m->stock_qty = (string) $newStock->toScale(3);
                }
                $m->save();

                return $movement;
            });
        } catch (UniqueConstraintViolationException) {
            return StockMovement::where('idempotency_key', $key)->firstOrFail();
        }
    }

    public function reserve(Material $m, string $qty, ProductionJob $job, string $key): StockMovement
    {
        $this->assertPositive($qty);

        return $this->move($m, 'reservation', $qty, $key, ['production_job_id' => $job->id, 'notes' => 'Réservation '.$job->number]);
    }

    public function outstandingReservation(Material $m, ProductionJob $job): string
    {
        $sum = StockMovement::where('material_id', $m->id)->where('production_job_id', $job->id)
            ->whereIn('type', ['reservation', 'release'])->pluck('quantity');

        return Money::str(Money::max(Money::sum($sum), 0), 3);
    }

    /** Consume for a job: converts any outstanding reservation first, records the job cost. */
    public function consume(Material $m, string $qty, ProductionJob $job, string $key, string $type = 'consumption', ?string $notes = null): StockMovement
    {
        $this->assertPositive($qty);

        return DB::transaction(function () use ($m, $qty, $job, $key, $type, $notes) {
            $already = StockMovement::where('idempotency_key', $key)->first();
            if ($already) {
                return $already;
            }
            $outstanding = Money::of($this->outstandingReservation($m, $job));
            $fromReservation = Money::min($outstanding, $qty);
            if ($fromReservation->isPositive()) {
                $this->move($m, 'release', (string) $fromReservation->negated(), $key.':rel', ['production_job_id' => $job->id, 'notes' => 'Conversion réservation']);
            }
            $mv = $this->move($m, $type, (string) Money::of($qty)->negated(), $key, ['production_job_id' => $job->id, 'notes' => $notes]);
            $m->refresh();
            app(ProductionService::class)->addCost($job, [
                'type' => $type === 'waste' ? 'waste' : 'material',
                'description' => ($type === 'waste' ? 'Gâche – ' : '').$m->name.' ('.$qty.' '.$m->unit.')',
                'quantity' => $qty, 'unit_cost' => $mv->unit_cost, 'stock_movement_id' => $mv->id,
            ]);

            return $mv;
        });
    }

    public function releaseJobReservations(ProductionJob $job): void
    {
        $materialIds = StockMovement::where('production_job_id', $job->id)->where('type', 'reservation')->distinct()->pluck('material_id');
        foreach (Material::whereIn('id', $materialIds)->get() as $m) {
            $out = Money::of($this->outstandingReservation($m, $job));
            if ($out->isPositive()) {
                $this->move($m, 'release', (string) $out->negated(), "release:job:{$job->id}:mat:{$m->id}:".md5((string) $out), ['production_job_id' => $job->id, 'notes' => 'Libération (annulation)']);
            }
        }
    }

    public function adjust(Material $m, string $newQty, string $reason, string $key): StockMovement
    {
        $delta = Money::of($newQty)->minus($m->stock_qty);
        $mv = $this->move($m, 'adjustment', (string) $delta, $key, ['notes' => $reason]);
        AuditLogger::log('stock.adjustment', $m, ['stock_qty' => $m->stock_qty], ['stock_qty' => $newQty], $reason);

        return $mv;
    }

    protected function assertPositive(string $qty): void
    {
        if (! Money::of($qty)->isPositive()) {
            throw ValidationException::withMessages(['quantity' => 'La quantité doit être positive.']);
        }
    }
}
