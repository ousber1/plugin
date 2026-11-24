<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\RetailProduct;
use App\Models\RetailSale;
use App\Models\RetailSaleItem;
use App\Models\RetailSalePayment;
use App\Models\RetailStockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physical store (point of sale).
 *
 * Prices are shelf prices TTC (what the customer pays). Per line:
 *   total_ttc = round2(price_ttc × qty − discount)
 *   tax       = round2(total_ttc − total_ttc / (1 + rate))
 *   total_ht  = total_ttc − tax
 * Payments are stored net (cash tendered − change). A sale must be fully paid; only cash can give change.
 * Every stock movement, sale, refund and cash movement carries a unique key (safe on retries).
 */
class RetailService
{
    public function __construct(protected NumberGenerator $numbers, protected Settings $settings) {}

    /* ------------------------------------------------------------------ cash sessions */

    public function currentSession(string $register = 'Caisse 1'): ?CashSession
    {
        return CashSession::where('register', $register)->where('status', 'open')->latest('id')->first();
    }

    public function openSession(string $register, string $float, ?User $user): CashSession
    {
        return DB::transaction(function () use ($register, $float, $user) {
            if ($existing = CashSession::where('register', $register)->where('status', 'open')->lockForUpdate()->first()) {
                return $existing;
            }
            $s = CashSession::create(['register' => $register, 'status' => 'open', 'opened_by' => $user?->id, 'opened_at' => now(), 'opening_float' => Money::str(Money::max($float, 0))]);
            AuditLogger::log('retail.session_opened', $s, null, ['register' => $register, 'float' => $s->opening_float]);

            return $s;
        });
    }

    /** @return array{float:string, cash_sales:string, cash_refunds:string, cash_in:string, cash_out:string, expected:string, by_method:array} */
    public function sessionSummary(CashSession $s): array
    {
        $pay = RetailSalePayment::where('cash_session_id', $s->id)->get();
        $sum = fn ($type, $method = null) => Money::sum($pay->where('type', $type)->when($method, fn ($c) => $c->where('method', $method))->pluck('amount'));
        $in = Money::sum($s->movements()->where('type', 'in')->pluck('amount'));
        $out = Money::sum($s->movements()->where('type', 'out')->pluck('amount'));
        $expected = Money::of($s->opening_float)->plus($sum('payment', 'cash'))->minus($sum('refund', 'cash'))->plus($in)->minus($out);
        $byMethod = [];
        foreach (config('hocreative.retail_payment_methods') as $m) {
            $byMethod[$m] = ['payments' => Money::str($sum('payment', $m)), 'refunds' => Money::str($sum('refund', $m))];
        }

        return ['float' => Money::str($s->opening_float), 'cash_sales' => Money::str($sum('payment', 'cash')), 'cash_refunds' => Money::str($sum('refund', 'cash')),
            'cash_in' => Money::str($in), 'cash_out' => Money::str($out), 'expected' => Money::str($expected), 'by_method' => $byMethod,
            'sales_count' => $s->sales()->count(), 'sales_total' => Money::str(Money::sum($s->sales()->pluck('total_ttc')))];
    }

    public function cashMovement(CashSession $s, string $type, string $amount, string $reason, string $key): CashMovement
    {
        if ($s->status !== 'open') {
            throw ValidationException::withMessages(['session' => 'La caisse est fermée.']);
        }
        if ($m = CashMovement::where('idempotency_key', $key)->first()) {
            return $m;
        }
        $m = $s->movements()->create(['type' => $type, 'amount' => Money::str($amount), 'reason' => $reason, 'idempotency_key' => $key, 'user_id' => auth()->id()]);
        AuditLogger::log('retail.cash_'.$type, $s, null, ['amount' => $m->amount], $reason);

        return $m;
    }

    public function closeSession(CashSession $s, string $counted, ?string $notes, ?User $user): CashSession
    {
        return DB::transaction(function () use ($s, $counted, $notes, $user) {
            $s = CashSession::whereKey($s->id)->lockForUpdate()->first();
            if ($s->status === 'closed') {
                return $s;
            }
            $expected = $this->sessionSummary($s)['expected'];
            $s->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $user?->id, 'expected_cash' => $expected,
                'counted_cash' => Money::str($counted), 'difference' => Money::str(Money::of($counted)->minus($expected)), 'notes' => $notes]);
            AuditLogger::log('retail.session_closed', $s, null, ['expected' => $expected, 'counted' => $s->counted_cash, 'difference' => $s->difference], $notes);

            return $s;
        });
    }

    /* ------------------------------------------------------------------ stock */

    public function move(RetailProduct $p, string $type, string $qty, string $key, array $extra = []): RetailStockMovement
    {
        if ($m = RetailStockMovement::where('idempotency_key', $key)->first()) {
            return $m;
        }
        try {
            return DB::transaction(function () use ($p, $type, $qty, $key, $extra) {
                $p = RetailProduct::whereKey($p->id)->lockForUpdate()->first();
                $q = Money::of($qty)->toScale(3, RoundingMode::HalfUp);
                $mv = RetailStockMovement::create([
                    'retail_product_id' => $p->id, 'type' => $type, 'quantity' => (string) $q,
                    'unit_cost' => $extra['unit_cost'] ?? $p->cost_price, 'retail_sale_id' => $extra['retail_sale_id'] ?? null,
                    'purchase_order_item_id' => $extra['purchase_order_item_id'] ?? null, 'idempotency_key' => $key,
                    'user_id' => auth()->id(), 'notes' => $extra['notes'] ?? null,
                ]);
                $newStock = Money::of($p->stock_qty)->plus($q);
                if (in_array($type, ['purchase', 'initial']) && $q->isPositive() && isset($extra['unit_cost'])) {
                    $oldValue = Money::max($p->stock_qty, 0)->multipliedBy($p->cost_price);
                    $total = Money::max($p->stock_qty, 0)->plus($q);
                    $p->cost_price = (string) $oldValue->plus($q->multipliedBy(Money::of($extra['unit_cost'])))->dividedBy($total, 4, RoundingMode::HalfUp);
                }
                $p->stock_qty = (string) $newStock->toScale(3);
                $p->save();

                return $mv;
            });
        } catch (UniqueConstraintViolationException) {
            return RetailStockMovement::where('idempotency_key', $key)->firstOrFail();
        }
    }

    public function adjust(RetailProduct $p, string $newQty, string $reason, string $key): RetailStockMovement
    {
        $delta = Money::of($newQty)->minus($p->stock_qty);
        $mv = $this->move($p, 'adjustment', (string) $delta, $key, ['notes' => $reason]);
        AuditLogger::log('retail.stock_adjustment', $p, ['stock_qty' => $p->stock_qty], ['stock_qty' => $newQty], $reason);

        return $mv;
    }

    /* ------------------------------------------------------------------ sales */

    /**
     * @param  array  $lines  [['product_id' => int, 'quantity' => num, 'discount' => num?]]
     * @param  array  $payments  [['method' => 'cash'|..., 'amount' => num (tendered for cash), 'reference' => ?]]
     */
    public function sell(CashSession $session, array $lines, array $payments, string $key, ?Customer $customer = null, ?string $discountReason = null, ?string $notes = null, bool $isDemo = false): RetailSale
    {
        if ($existing = RetailSale::where('idempotency_key', $key)->first()) {
            return $existing; // double-click / network retry
        }
        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['session' => 'Ouvrez la caisse avant d\'encaisser.']);
        }
        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'Le ticket est vide.']);
        }

        return DB::transaction(function () use ($session, $lines, $payments, $key, $customer, $discountReason, $notes, $isDemo) {
            $allowNegative = $this->settings->bool('retail.allow_negative_stock');
            $rows = [];
            $total = BigDecimal::zero();
            $discountTotal = BigDecimal::zero();
            $merged = [];
            foreach ($lines as $l) {
                $id = (int) ($l['product_id'] ?? 0);
                $merged[$id] = ['product_id' => $id, 'quantity' => Money::of($merged[$id]['quantity'] ?? 0)->plus(Money::of($l['quantity'] ?? 0)), 'discount' => Money::of($merged[$id]['discount'] ?? 0)->plus(Money::of($l['discount'] ?? 0))];
            }
            foreach ($merged as $l) {
                $p = RetailProduct::whereKey($l['product_id'])->lockForUpdate()->first();
                if (! $p || ! $p->is_active) {
                    throw ValidationException::withMessages(['lines' => 'Article introuvable ou inactif.']);
                }
                $qty = Money::of($l['quantity'])->toScale(3, RoundingMode::HalfUp);
                if (! $qty->isPositive()) {
                    throw ValidationException::withMessages(['lines' => "Quantité invalide pour {$p->name}."]);
                }
                if ($p->unit === 'piece' && ! $qty->isEqualTo($qty->toScale(0, RoundingMode::Down))) {
                    throw ValidationException::withMessages(['lines' => "{$p->name} se vend à l'unité."]);
                }
                if ($p->track_stock && ! $allowNegative && Money::of($p->stock_qty)->isLessThan($qty)) {
                    throw ValidationException::withMessages(['lines' => "Stock insuffisant pour {$p->name} (disponible : ".(float) $p->stock_qty.')']);
                }
                $gross = Money::of($p->price_ttc)->multipliedBy($qty);
                $disc = Money::round($l['discount']);
                if ($disc->isNegative() || $disc->isGreaterThan($gross)) {
                    throw ValidationException::withMessages(['lines' => "Remise invalide pour {$p->name}."]);
                }
                $lineTtc = Money::round($gross->minus($disc));
                $tax = Money::round($lineTtc->minus($lineTtc->multipliedBy(100)->dividedBy(Money::of($p->tax_rate)->plus(100), 6, RoundingMode::HalfUp)));
                $rows[] = ['product' => $p, 'qty' => $qty, 'disc' => $disc, 'ttc' => $lineTtc, 'tax' => $tax, 'ht' => $lineTtc->minus($tax)];
                $total = $total->plus($lineTtc);
                $discountTotal = $discountTotal->plus($disc);
            }
            if ($discountTotal->isPositive()) {
                if (! auth()->user()?->can('retail.discount')) {
                    throw ValidationException::withMessages(['lines' => 'Remise non autorisée pour votre profil.']);
                }
                if (! trim((string) $discountReason)) {
                    throw ValidationException::withMessages(['discount_reason' => 'Indiquez le motif de la remise.']);
                }
            }

            // payments
            $nonCash = BigDecimal::zero();
            $cash = BigDecimal::zero();
            $clean = [];
            foreach ($payments as $pay) {
                $amt = Money::round($pay['amount'] ?? 0);
                if (! $amt->isPositive()) {
                    continue;
                }
                $method = (string) ($pay['method'] ?? '');
                if (! in_array($method, config('hocreative.retail_payment_methods'), true)) {
                    throw ValidationException::withMessages(['payments' => 'Moyen de paiement invalide.']);
                }
                if ($method === 'cash') {
                    $cash = $cash->plus($amt);
                } else {
                    $nonCash = $nonCash->plus($amt);
                }
                $clean[] = ['method' => $method, 'amount' => $amt, 'reference' => $pay['reference'] ?? null];
            }
            if ($nonCash->isGreaterThan($total)) {
                throw ValidationException::withMessages(['payments' => 'Les paiements par carte / chèque / virement dépassent le total.']);
            }
            if ($nonCash->plus($cash)->isLessThan($total)) {
                throw ValidationException::withMessages(['payments' => 'Montant encaissé insuffisant ('.Money::format($nonCash->plus($cash)).' pour '.Money::format($total).').']);
            }
            $change = $nonCash->plus($cash)->minus($total);

            $sale = RetailSale::create([
                'number' => $this->numbers->next('ticket'), 'cash_session_id' => $session->id, 'customer_id' => $customer?->id,
                'user_id' => auth()->id(), 'status' => 'completed',
                'subtotal_ht' => Money::str(Money::sum(array_column($rows, 'ht'))), 'tax_total' => Money::str(Money::sum(array_column($rows, 'tax'))),
                'discount_total' => Money::str($discountTotal), 'discount_reason' => $discountTotal->isPositive() ? $discountReason : null,
                'total_ttc' => Money::str($total), 'amount_paid' => Money::str($total), 'change_given' => Money::str($change),
                'idempotency_key' => $key, 'notes' => $notes, 'is_demo' => $isDemo,
            ]);
            $cost = BigDecimal::zero();
            foreach ($rows as $r) {
                $p = $r['product'];
                $item = $sale->items()->create([
                    'retail_product_id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'quantity' => (string) $r['qty'],
                    'unit_price_ttc' => $p->price_ttc, 'discount' => Money::str($r['disc']), 'tax_rate' => $p->tax_rate,
                    'total_ttc' => Money::str($r['ttc']), 'total_ht' => Money::str($r['ht']), 'tax_amount' => Money::str($r['tax']), 'unit_cost' => $p->cost_price,
                ]);
                $cost = $cost->plus(Money::of($p->cost_price)->multipliedBy($r['qty']));
                if ($p->track_stock) {
                    $this->move($p, 'sale', (string) $r['qty']->negated(), "sale:{$sale->id}:item:{$item->id}", ['retail_sale_id' => $sale->id, 'unit_cost' => $p->cost_price, 'notes' => $sale->number]);
                }
            }
            // record payments net: change is taken from cash
            $changeLeft = $change;
            foreach ($clean as $pay) {
                $amt = $pay['amount'];
                if ($pay['method'] === 'cash' && $changeLeft->isPositive()) {
                    $take = Money::min($changeLeft, $amt);
                    $amt = $amt->minus($take);
                    $changeLeft = $changeLeft->minus($take);
                }
                if ($amt->isPositive()) {
                    $sale->payments()->create(['cash_session_id' => $session->id, 'type' => 'payment', 'method' => $pay['method'], 'amount' => Money::str($amt), 'reference' => $pay['reference'], 'user_id' => auth()->id()]);
                }
            }
            $sale->update(['cost_total' => Money::str($cost)]);
            AuditLogger::log('retail.sale', $sale, null, ['total' => $sale->total_ttc, 'items' => count($rows)]);
            if ($discountTotal->isPositive()) {
                AuditLogger::log('retail.discount', $sale, null, ['amount' => $sale->discount_total], $discountReason);
            }

            return $sale->fresh(['items', 'payments']);
        });
    }

    /**
     * Partial or full return. $lines = [item_id => qty]. Cash refunds need an open session.
     */
    public function refund(RetailSale $sale, array $lines, string $method, bool $restock, string $reason, string $key, ?CashSession $session = null): RetailSalePayment
    {
        if ($p = RetailSalePayment::where('idempotency_key', $key)->first()) {
            return $p;
        }

        return DB::transaction(function () use ($sale, $lines, $method, $restock, $reason, $key, $session) {
            $sale = RetailSale::whereKey($sale->id)->lockForUpdate()->first();
            if ($method === 'cash' && (! $session || $session->status !== 'open')) {
                throw ValidationException::withMessages(['method' => 'Un remboursement en espèces nécessite une caisse ouverte.']);
            }
            $amount = BigDecimal::zero();
            $any = false;
            foreach ($sale->items as $item) {
                $q = Money::of($lines[$item->id] ?? 0);
                if (! $q->isPositive()) {
                    continue;
                }
                $left = Money::of($item->quantity)->minus($item->returned_qty);
                if ($q->isGreaterThan($left)) {
                    throw ValidationException::withMessages(['lines' => "Quantité retournée supérieure à la quantité vendue restante ({$item->name})."]);
                }
                $any = true;
                $amount = $amount->plus(Money::of($item->total_ttc)->multipliedBy($q)->dividedBy($item->quantity, 6, RoundingMode::HalfUp));
                $item->update(['returned_qty' => (string) Money::of($item->returned_qty)->plus($q)->toScale(3)]);
                if ($restock && $item->product && $item->product->track_stock) {
                    $this->move($item->product, 'return', (string) $q, "return:{$key}:item:{$item->id}", ['retail_sale_id' => $sale->id, 'unit_cost' => $item->unit_cost, 'notes' => 'Retour '.$sale->number]);
                }
            }
            if (! $any) {
                throw ValidationException::withMessages(['lines' => 'Sélectionnez les articles retournés.']);
            }
            $amount = Money::round($amount);
            $payment = $sale->payments()->create(['cash_session_id' => $session?->id, 'type' => 'refund', 'method' => $method, 'amount' => Money::str($amount),
                'reference' => mb_substr($reason, 0, 190), 'idempotency_key' => $key, 'user_id' => auth()->id()]);
            $refunded = Money::of($sale->refunded_total)->plus($amount);
            $allBack = $sale->items->every(fn (RetailSaleItem $i) => Money::of($i->returned_qty)->isGreaterThanOrEqualTo($i->quantity));
            $sale->update(['refunded_total' => Money::str($refunded), 'status' => $allBack ? 'refunded' : 'partially_refunded']);
            AuditLogger::log('retail.refund', $sale, null, ['amount' => (string) $amount, 'method' => $method, 'restock' => $restock], $reason);

            return $payment;
        });
    }
}
