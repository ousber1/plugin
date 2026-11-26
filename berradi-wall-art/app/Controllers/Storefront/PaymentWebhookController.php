<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Response;
use App\Helpers\Money;
use App\Services\Payments\PaymentRegistry;
use App\Services\Payments\PaymentService;

/**
 * Server-to-server notifications from online payment providers (CSRF-exempt, signature-verified by the gateway).
 * With no gateway registered (default), every call returns 404.
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
            Logger::warning('Rejected payment callback', ['provider' => $provider, 'ip' => $this->request->ip()]);
            return $this->json(['ok' => false], 400);
        }
        $db = Database::instance();
        $order = $db->fetch('SELECT id, grand_total, payment_method_id FROM orders WHERE order_number = ?', [(string) ($result['order_number'] ?? '')]);
        if ($order === null || Money::toCents((string) ($result['amount'] ?? '0')) <= 0) {
            return $this->json(['ok' => false], 404);
        }
        $methodId = (int) $db->value('SELECT id FROM payment_methods WHERE provider = ?', [$provider]);
        PaymentService::recordConfirmed((int) $order['id'], (string) $result['amount'], $methodId ?: (int) $order['payment_method_id'], (string) ($result['transaction_id'] ?? ''), null, $result['raw'] ?? null);
        return $this->json(['ok' => true]);
    }
}
