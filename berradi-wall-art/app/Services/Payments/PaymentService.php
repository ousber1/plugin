<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\SequenceService;

/**
 * Records confirmed payments and keeps the order balance in sync.
 * Used by verified online-gateway callbacks and (Phase 7) by staff confirming offline payments.
 */
final class PaymentService
{
    /**
     * @param array<string,mixed>|null $payload provider data kept for audit
     * @return int payment id (existing id if the provider transaction was already recorded: idempotent)
     */
    public static function recordConfirmed(int $orderId, string $amount, int $paymentMethodId, ?string $providerTxId = null, ?string $reference = null, ?array $payload = null, ?int $userId = null): int
    {
        if (Money::toCents($amount) <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive');
        }
        $db = Database::instance();
        if ($providerTxId !== null) {
            $existing = $db->value('SELECT id FROM payments WHERE provider_transaction_id = ?', [$providerTxId]);
            if ($existing !== null) {
                return (int) $existing; // webhook retries must not double count
            }
        }
        return $db->transaction(function (Database $db) use ($orderId, $amount, $paymentMethodId, $providerTxId, $reference, $payload, $userId): int {
            $order = $db->fetch('SELECT id, customer_id, grand_total, order_number FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if ($order === null) {
                throw new \RuntimeException('Order not found');
            }
            $id = $db->insert('payments', [
                'payment_number' => SequenceService::next('payment'),
                'payment_type' => 'payment',
                'order_id' => $orderId,
                'customer_id' => $order['customer_id'],
                'payment_method_id' => $paymentMethodId,
                'amount' => Money::normalize($amount),
                'currency' => (string) setting('currency_code', 'MAD'),
                'status' => 'confirmed',
                'reference' => $reference,
                'provider_transaction_id' => $providerTxId,
                'provider_payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'paid_at' => date('Y-m-d H:i:s'),
                'confirmed_by' => $userId,
            ]);
            self::syncOrder($db, $orderId);
            ActivityLogger::log('payment.confirmed', 'order', $orderId, (string) $order['order_number'], ['amount' => $amount]);
            return $id;
        });
    }

    /** Recompute amount_paid / balance_due / payment_status from confirmed payments and refunds. */
    public static function syncOrder(Database $db, int $orderId): void
    {
        $paid = Money::toCents((string) $db->value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ? AND status = 'confirmed' AND payment_type = 'payment'", [$orderId]));
        $refunded = Money::toCents((string) $db->value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ? AND status = 'confirmed' AND payment_type = 'refund'", [$orderId]));
        $total = Money::toCents((string) $db->value('SELECT grand_total FROM orders WHERE id = ?', [$orderId]));
        $net = $paid - $refunded;
        $status = match (true) {
            $refunded > 0 && $net <= 0 => 'refunded',
            $refunded > 0 => 'partially_refunded',
            $net >= $total && $total > 0 => 'paid',
            $net > 0 => 'partial',
            default => 'unpaid',
        };
        $db->update('orders', [
            'amount_paid' => Money::fromCents($net),
            'balance_due' => Money::fromCents(max(0, $total - $net)),
            'payment_status' => $status,
        ], ['id' => $orderId]);
    }
}
