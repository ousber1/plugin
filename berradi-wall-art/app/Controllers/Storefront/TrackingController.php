<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Services\RateLimiter;
use App\Services\Sales\OrderService;
use App\Services\SeoService;

/**
 * Public order tracking: order number + e-mail/phone, or the private link sent by e-mail.
 * Shows status and shipment only (no address or items) to limit exposure of personal data.
 */
final class TrackingController extends Controller
{
    public function form(): Response
    {
        return $this->view('storefront/track/index', ['order' => null, 'seo' => SeoService::page(__('track.title'), __('track.intro'), ['canonical' => '/track'])]);
    }

    public function lookup(): Response
    {
        $data = $this->validate(['number' => 'required|max:30', 'contact' => 'required|max:191'], ['number' => __('track.number'), 'contact' => __('track.contact')]);
        $key = RateLimiter::key('track', $this->request->ip());
        if (RateLimiter::tooManyAttempts($key, $this->request->ip(), 10, 15)) {
            throw new \App\Core\HttpException(429, __('common.too_many_requests'));
        }
        $order = OrderService::findForTracking((string) $data['number'], (string) $data['contact']);
        if ($order === null) {
            RateLimiter::hit($key, $this->request->ip());
            throw new \App\Core\ValidationException(['number' => __('track.not_found')], $this->request->all());
        }
        return $this->show($order);
    }

    public function token(string $number, string $token): Response
    {
        $order = OrderService::findForTracking($number, null, $token);
        if ($order === null) {
            $this->flash('danger', __('track.not_found'));
            return $this->redirectRoute('order.track');
        }
        return $this->show($order);
    }

    /** @param array<string,mixed> $order */
    private function show(array $order): Response
    {
        $shipments = Database::instance()->fetchAll(
            'SELECT courier, tracking_number, tracking_url, status, shipped_at, delivered_at FROM shipments WHERE order_id = ? ORDER BY id DESC',
            [(int) $order['id']]
        );
        return $this->view('storefront/track/index', [
            'order' => $order,
            'history' => OrderService::history((int) $order['id']),
            'shipments' => $shipments,
            'seo' => SeoService::page(__('track.title'), null, ['noindex' => true]),
        ]);
    }
}
