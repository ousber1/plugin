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
        $order = $db->fetch('SELECT grand_total, status FROM orders WHERE id = ?', [$orderId]);
        $total = Money::toCents((string) $order['grand_total']);
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
            // a cancelled order owes nothing; what was paid stays visible until refunded
            'balance_due' => $order['status'] === 'cancelled' ? '0.00' : Money::fromCents(max(0, $total - $net)),
            'payment_status' => $status,
        ], ['id' => $orderId]);
        \App\Services\Finance\InvoiceService::syncForOrder($orderId);
    }

    /**
     * Money given back to a customer (never more than what was received). Recorded as a negative movement,
     * the original payment stays untouched.
     */
    public static function refund(int $orderId, string $amount, int $paymentMethodId, string $reason, ?int $userId = null): int
    {
        $amount = Money::normalize($amount);
        if (Money::toCents($amount) <= 0) {
            throw new \InvalidArgumentException(__('finance.err_amount'));
        }
        return Database::instance()->transaction(function (Database $db) use ($orderId, $amount, $paymentMethodId, $reason, $userId): int {
            $o = $db->fetch('SELECT id, customer_id, amount_paid, order_number FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if ($o === null) {
                throw new \RuntimeException('Order not found');
            }
            if (Money::compare($amount, (string) $o['amount_paid']) > 0) {
                throw new \InvalidArgumentException(__('finance.err_refund_max', ['max' => money($o['amount_paid'])]));
            }
            $id = $db->insert('payments', [
                'payment_number' => SequenceService::next('payment'), 'payment_type' => 'refund', 'order_id' => $orderId, 'customer_id' => $o['customer_id'],
                'payment_method_id' => $paymentMethodId, 'amount' => $amount, 'currency' => (string) setting('currency_code', 'MAD'), 'status' => 'confirmed',
                'paid_at' => date('Y-m-d H:i:s'), 'confirmed_by' => $userId, 'notes' => mb_substr($reason, 0, 1000),
            ]);
            self::syncOrder($db, $orderId);
            ActivityLogger::log('payment.refunded', 'order', $orderId, (string) $o['order_number'], ['amount' => $amount]);
            return $id;
        });
    }

    /** Payment received for an invoice that has no online order (B2B, services). */
    public static function recordForInvoice(int $invoiceId, string $amount, int $paymentMethodId, ?string $reference, ?int $userId): int
    {
        $amount = Money::normalize($amount);
        return Database::instance()->transaction(function (Database $db) use ($invoiceId, $amount, $paymentMethodId, $reference, $userId): int {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if ($inv === null || $inv['invoice_type'] !== 'invoice' || $inv['order_id'] !== null || !in_array($inv['status'], ['issued', 'partially_paid'], true)) {
                throw new \InvalidArgumentException(__('finance.err_invoice_payment'));
            }
            $left = \App\Services\Finance\InvoiceService::balance($inv);
            if (Money::toCents($amount) <= 0 || Money::compare($amount, $left) > 0) {
                throw new \InvalidArgumentException(__('finance.err_pay_max', ['max' => money($left)]));
            }
            $id = $db->insert('payments', [
                'payment_number' => SequenceService::next('payment'), 'payment_type' => 'payment', 'invoice_id' => $invoiceId, 'customer_id' => $inv['customer_id'],
                'payment_method_id' => $paymentMethodId, 'amount' => $amount, 'currency' => (string) setting('currency_code', 'MAD'), 'status' => 'confirmed',
                'reference' => $reference, 'paid_at' => date('Y-m-d H:i:s'), 'confirmed_by' => $userId,
            ]);
            \App\Services\Finance\InvoiceService::syncPaid($invoiceId);
            ActivityLogger::log('payment.confirmed', 'invoice', $invoiceId, (string) $inv['invoice_number'], ['amount' => $amount]);
            return $id;
        });
    }
}
