<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payments & refunds. Payment status (money) is tracked independently of the
 * fulfillment status (goods). Each payment has a unique idempotency key so a
 * replayed form post or provider callback is recorded at most once.
 */
class PaymentService
{
    public function record(Order $order, string $type, string $method, string $amount, string $idempotencyKey, array $extra = []): Payment
    {
        if ($p = Payment::where('idempotency_key', $idempotencyKey)->first()) {
            return $p;
        }
        try {
            return DB::transaction(function () use ($order, $type, $method, $amount, $idempotencyKey, $extra) {
                $order = Order::whereKey($order->id)->lockForUpdate()->first();
                $amt = Money::round($amount);
                if (! $amt->isPositive()) {
                    throw ValidationException::withMessages(['amount' => 'Le montant doit être positif.']);
                }
                if ($type === 'refund' && $amt->isGreaterThan($order->netPaid())) {
                    throw ValidationException::withMessages(['amount' => 'Le remboursement dépasse le montant encaissé ('.Money::format($order->netPaid()).').']);
                }
                $payment = $order->payments()->create([
                    'type' => $type, 'method' => $method, 'amount' => (string) $amt,
                    'status' => 'completed', 'reference' => $extra['reference'] ?? null,
                    'provider' => $extra['provider'] ?? null, 'provider_reference' => $extra['provider_reference'] ?? null,
                    'idempotency_key' => $idempotencyKey, 'recorded_by' => auth()->id(),
                    'notes' => $extra['notes'] ?? null, 'paid_at' => $extra['paid_at'] ?? now(),
                ]);
                $old = $order->only(['amount_paid', 'amount_refunded', 'payment_status']);
                if ($type === 'refund') {
                    $order->amount_refunded = Money::str(Money::of($order->amount_refunded)->plus($amt));
                } else {
                    $order->amount_paid = Money::str(Money::of($order->amount_paid)->plus($amt));
                }
                $order->payment_status = $this->statusFor($order);
                if ($type === 'payment' && $order->status === 'pending') {
                    $order->status = 'confirmed';
                }
                $order->save();
                AuditLogger::log($type === 'refund' ? 'payment.refund' : 'payment.recorded', $order, $old,
                    $order->only(['amount_paid', 'amount_refunded', 'payment_status']) + ['payment_id' => $payment->id, 'method' => $method, 'amount' => (string) $amt],
                    $extra['notes'] ?? null);

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            return Payment::where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    public function statusFor(Order $order): string
    {
        $net = Money::of($order->amount_paid)->minus($order->amount_refunded);
        $refunded = Money::of($order->amount_refunded)->isPositive();
        if (! $net->isPositive()) {
            return $refunded ? 'refunded' : 'unpaid';
        }
        if ($net->isLessThan($order->total)) {
            return $refunded ? 'partially_refunded' : 'partial';
        }

        return 'paid';
    }

    /**
     * Trusted provider event (server-to-server callback, signature already verified
     * by the gateway adapter). Returns 'processed' | 'duplicate' | 'ignored'.
     */
    public function handleProviderEvent(string $provider, string $eventId, Order $order, string $amount, bool $success, array $payload, ?string $providerRef = null): string
    {
        try {
            $event = PaymentEvent::create(['provider' => $provider, 'event_id' => $eventId, 'order_id' => $order->id, 'payload' => $payload]);
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }
        if (! $success) {
            $event->update(['result' => 'failed', 'processed_at' => now()]);

            return 'ignored';
        }
        $this->record($order, 'payment', 'online', $amount, $provider.':'.$eventId, ['provider' => $provider, 'provider_reference' => $providerRef, 'reference' => $providerRef]);
        $event->update(['result' => 'paid', 'processed_at' => now()]);

        return 'processed';
    }
}
