<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Response;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Notifications\StaffNotifier;
use App\Services\Payments\PaymentRegistry;
use App\Services\Payments\PaymentService;

/**
 * Server-to-server notifications from online payment providers (no session, no CSRF: the provider's
 * signature is the authentication). Only a verified AND approved notification records a payment;
 * retries of the same transaction are idempotent.
 */
final class PaymentWebhookController extends Controller
{
    public function handle(string $provider): Response
    {
        $gateway = PaymentRegistry::gateway($provider);
        if ($gateway === null) {
            throw new HttpException(404);
        }
        $result = $gateway->verifyCallback($this->request);
        if (!$result['verified']) {
            Logger::warning('Rejected payment callback', ['provider' => $provider, 'ip' => $this->request->ip(), 'reason' => $result['error'] ?? '']);
            return $this->text($gateway->reply(false, false), 400);
        }
        $db = Database::instance();
        $order = $db->fetch('SELECT id, order_number, grand_total, balance_due, payment_method_id, status FROM orders WHERE order_number = ?', [(string) ($result['order_number'] ?? '')]);
        if ($order === null) {
            Logger::warning('Payment callback for an unknown order', ['provider' => $provider, 'oid' => $result['order_number'] ?? '']);
            return $this->text($gateway->reply(false, false), 404);
        }
        if (!$result['approved']) {
            ActivityLogger::log('payment.declined', 'order', (int) $order['id'], (string) $order['order_number'], ['provider' => $provider, 'code' => $result['raw']['ProcReturnCode'] ?? null, 'message' => $result['raw']['ErrMsg'] ?? null]);
            return $this->text($gateway->reply(true, false));
        }
        $amount = (string) $result['amount'];
        if (Money::toCents($amount) <= 0) {
            return $this->text($gateway->reply(false, false), 400);
        }
        $methodId = (int) $db->value('SELECT id FROM payment_methods WHERE provider = ?', [$provider]);
        $existing = $db->value('SELECT id FROM payments WHERE provider_transaction_id = ?', [(string) $result['transaction_id']]);
        PaymentService::recordConfirmed((int) $order['id'], $amount, $methodId ?: (int) $order['payment_method_id'], (string) $result['transaction_id'], (string) ($result['raw']['AuthCode'] ?? '') ?: null, $result['raw'] ?? null);
        if ($existing === null) {
            $unexpected = Money::compare($amount, (string) $order['balance_due']) !== 0 || in_array($order['status'], ['cancelled', 'returned'], true);
            StaffNotifier::notify(
                $unexpected ? 'payment.check' : 'payment.received',
                'payments.view',
                __($unexpected ? 'notifications.card_payment_check' : 'notifications.card_payment', ['number' => $order['order_number'], 'amount' => money($amount)]),
                'orders/' . (int) $order['id'],
                'order',
                (int) $order['id']
            );
        }
        return $this->text($gateway->reply(true, true));
    }

    private function text(string $body, int $status = 200): Response
    {
        return (new Response($body, $status))->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
