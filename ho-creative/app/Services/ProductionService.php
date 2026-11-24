<?php

namespace App\Services;

use App\Models\JobCost;
use App\Models\OrderItem;
use App\Models\ProductionJob;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public const STAGES = ['artwork_pending', 'prepress_review', 'proof_pending', 'approved', 'printing',
        'finishing', 'quality_control', 'packing', 'ready_for_pickup', 'shipped', 'delivered', 'cancelled'];

    /** Stages that physically consume resources: require cleared proof & confirmed order. */
    public const GUARDED_FROM = 'approved';

    public function __construct(protected NumberGenerator $numbers) {}

    public static function index(string $stage): int
    {
        return (int) array_search($stage, self::STAGES, true);
    }

    public function createJobFor(OrderItem $item, ?CarbonInterface $due = null): ProductionJob
    {
        if ($item->job) {
            return $item->job;
        }
        $stage = $item->artwork_mode === 'later' && ! $item->artworkFiles()->exists() ? 'artwork_pending' : 'prepress_review';
        $job = ProductionJob::create([
            'number' => $this->numbers->next('job'),
            'order_item_id' => $item->id,
            'order_id' => $item->order_id,
            'stage' => $stage,
            'due_at' => $due?->toDateString(),
            'department' => $this->guessDepartment($item),
            'priority' => str_contains(json_encode($item->selection ?? []), 'express') ? 'high' : 'normal',
        ]);
        $job->events()->create(['from_stage' => null, 'to_stage' => $stage, 'user_id' => auth()->id(), 'note' => 'Création', 'created_at' => now()]);

        return $job;
    }

    protected function guessDepartment(OrderItem $item): string
    {
        return ! empty($item->breakdown['area_m2']) && (float) ($item->breakdown['area_m2']) > 0.5 ? 'large_format' : 'digital';
    }

    /** Reasons the job cannot enter $to; empty when allowed. */
    public function blockers(ProductionJob $job, string $to): array
    {
        $item = $job->item;
        $order = $job->order;
        $reasons = [];
        if ($to === 'cancelled') {
            return [];
        }
        if ($job->stage === 'cancelled') {
            $reasons[] = __('Ce travail est annulé.');
        }
        if ($order->status === 'cancelled') {
            $reasons[] = __('La commande est annulée.');
        }
        if (self::index($to) >= self::index(self::GUARDED_FROM)) {
            if (! $item->proofCleared()) {
                $reasons[] = __('Le BAT actuel (v:v) doit être approuvé par le client avant la production.', ['v' => $item->currentProof?->version ?? '—']);
            }
            if (! $item->requires_proof && $item->artwork_status === 'pending') {
                $reasons[] = __('Aucun fichier d\'impression reçu.');
            }
        }
        if (self::index($to) >= self::index('printing') && $order->status === 'pending') {
            $reasons[] = __('La commande doit être confirmée (paiement / acompte ou validation commerciale) avant l\'impression.');
        }

        return $reasons;
    }

    /**
     * Idempotent: moving to the current stage is a no-op (safe on replays / double clicks).
     */
    public function transition(ProductionJob $job, string $to, ?string $note = null, bool $force = false): ProductionJob
    {
        if (! in_array($to, self::STAGES, true)) {
            throw ValidationException::withMessages(['stage' => 'Étape inconnue.']);
        }

        return DB::transaction(function () use ($job, $to, $note, $force) {
            $job = ProductionJob::whereKey($job->id)->lockForUpdate()->first();
            if ($job->stage === $to) {
                return $job;
            }
            if (! $force && ($b = $this->blockers($job, $to))) {
                throw ValidationException::withMessages(['stage' => $b]);
            }
            $from = $job->stage;
            $updates = ['stage' => $to];
            if ($to === 'printing' && ! $job->started_at) {
                $updates['started_at'] = now();
            }
            if (in_array($to, ['ready_for_pickup', 'shipped', 'delivered']) && ! $job->completed_at) {
                $updates['completed_at'] = now();
            }
            $job->update($updates);
            $job->events()->create(['from_stage' => $from, 'to_stage' => $to, 'user_id' => auth()->id(), 'note' => $note, 'created_at' => now()]);
            AuditLogger::log('production.transition', $job, ['stage' => $from], ['stage' => $to], $note);

            if ($to === 'cancelled') {
                app(StockService::class)->releaseJobReservations($job);
            }
            app(OrderService::class)->syncFulfillment($job->order);

            return $job;
        });
    }

    public function addCost(ProductionJob $job, array $data): JobCost
    {
        if (! empty($data['idempotency_key']) && ($existing = JobCost::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing;
        }
        $total = Money::str(Money::of($data['quantity'] ?? 1)->multipliedBy(Money::of($data['unit_cost'] ?? 0)));
        $cost = $job->costs()->create([
            'type' => $data['type'], 'is_estimate' => (bool) ($data['is_estimate'] ?? false),
            'description' => $data['description'], 'quantity' => $data['quantity'] ?? 1,
            'unit_cost' => $data['unit_cost'] ?? 0, 'total' => $total,
            'stock_movement_id' => $data['stock_movement_id'] ?? null, 'user_id' => auth()->id(),
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
        $this->refreshEstimate($job);

        return $cost;
    }

    public function refreshEstimate(ProductionJob $job): void
    {
        $job->update(['estimated_cost' => Money::str(Money::sum($job->costs()->where('is_estimate', true)->pluck('total')))]);
    }

    /** @return array{estimated:string, actual:string, revenue:string, margin:string, margin_pct:?string} */
    public function costSummary(ProductionJob $job): array
    {
        $est = Money::sum($job->costs->where('is_estimate', true)->pluck('total'));
        $act = Money::sum($job->costs->where('is_estimate', false)->pluck('total'));
        $rev = Money::of($job->item->total);
        $margin = $rev->minus($act);

        return [
            'estimated' => Money::str($est), 'actual' => Money::str($act), 'revenue' => Money::str($rev),
            'margin' => Money::str($margin),
            'margin_pct' => $rev->isPositive() ? Money::str($margin->multipliedBy(100)->dividedBy($rev, 2, \Brick\Math\RoundingMode::HalfUp)) : null,
        ];
    }

    /** Remake / reprint linked to the original line, at zero price by default. */
    public function remake(OrderItem $item, string $reason, bool $charge = false): OrderItem
    {
        return DB::transaction(function () use ($item, $reason, $charge) {
            $copy = $item->replicate(['proof_status', 'artwork_status']);
            $copy->remake_of_id = $item->id;
            $copy->remake_reason = $reason;
            $copy->description = 'REPRISE – '.$item->description;
            if (! $charge) {
                $copy->unit_price = 0;
                $copy->subtotal = 0;
                $copy->total = 0;
                $copy->manual_discount = 0;
            }
            $copy->proof_status = $item->proof_status === 'approved' ? 'approved' : 'none';
            $copy->artwork_status = $item->artwork_status;
            $copy->save();
            foreach ($item->artworkFiles as $f) {
                $copy->artworkFiles()->create($f->only(['side', 'version', 'disk', 'path', 'original_name', 'mime', 'size', 'checksum', 'checks', 'review_status', 'review_note', 'uploaded_by']));
            }
            $job = $this->createJobFor($copy, now());
            $job->update(['priority' => 'urgent', 'internal_notes' => 'Reprise : '.$reason]);
            AuditLogger::log('production.remake', $copy, null, ['original_item' => $item->id, 'charged' => $charge], $reason);

            return $copy;
        });
    }
}
