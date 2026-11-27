<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Csp;
use App\Services\Payments\PaymentRegistry;
use App\Services\Sales\OrderService;
use App\Services\SeoService;

/**
 * Card payment pages. Both work from the order's secret tracking token, without a session:
 *  - pay(): signed form auto-submitted to the bank's hosted payment page;
 *  - returned(): where the bank sends the browser afterwards. It NEVER records a payment — only the
 *    server-to-server callback (PaymentWebhookController) does, after checking the bank's signature.
 */
final class PaymentController extends Controller
{
    public function pay(string $number, string $token): Response
    {
        $order = OrderService::findForTracking($number, null, $token);
        if ($order === null) {
            throw new HttpException(404);
        }
        $method = PaymentRegistry::describe((int) $order['payment_method_id']);
        $trackUrl = route('order.track.token', ['number' => $order['order_number'], 'token' => $token]);
        if (CheckoutController::payUrl($order, $method, $token) === null) {
            // already paid, cancelled, or the gateway was switched off meanwhile
            return $this->redirect($trackUrl);
        }
        CheckoutController::trackPurchase($order, OrderService::items((int) $order['id']));
        $gateway = PaymentRegistry::gateway((string) $method['provider']);
        $form = $gateway->start($order, $token);
        $host = (string) preg_replace('#^(https://[^/]+).*$#', '$1', $form['action']);
        Csp::allow('form-action', $host);
        ActivityLogger::log('payment.redirected', 'order', (int) $order['id'], (string) $order['order_number'], ['provider' => $gateway->code(), 'amount' => $form['fields']['amount'] ?? null]);
        return $this->view('storefront/checkout/pay', [
            'order' => $order,
            'method' => $method,
            'form' => $form,
            'trackUrl' => $trackUrl,
            'seo' => SeoService::page(__('payment.title'), null, ['noindex' => true]),
        ]);
    }

    public function returned(string $provider, string $result, string $number, string $token): Response
    {
        $order = OrderService::findForTracking($number, null, $token);
        if ($order === null || !PaymentRegistry::hasIntegration($provider) || !in_array($result, ['ok', 'fail'], true)) {
            throw new HttpException(404);
        }
        // 303: the bank POSTs here; the browser continues with a plain GET on the tracking page
        return Response::redirect(route('order.track.token', ['number' => $order['order_number'], 'token' => $token]) . '?payment=' . $result, 303);
    }
}
