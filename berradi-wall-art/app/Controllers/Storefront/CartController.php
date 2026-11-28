<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Response;
use App\Services\Sales\CartService;
use App\Services\Sales\CheckoutCalculator;
use App\Services\Sales\ShippingService;
use App\Services\SeoService;

final class CartController extends Controller
{
    public function index(): Response
    {
        $cart = CartService::current(false);
        $lines = $cart ? CartService::lines($cart) : [];
        $city = (string) ($this->request->query('city') ?? \App\Core\Session::get('cart_city', ''));
        $totals = $cart ? CheckoutCalculator::totals($cart, $lines, $city !== '' ? $city : null) : null;
        if ($totals !== null && $totals['coupon_error'] !== null) {
            CartService::removeCoupon();
            $this->flash('warning', $totals['coupon_error']);
            $totals = CheckoutCalculator::totals(CartService::current(false), $lines, $city !== '' ? $city : null);
        }
        return $this->view('storefront/cart/index', [
            'lines' => $lines,
            'totals' => $totals,
            'city' => $city,
            'cities' => ShippingService::cities(),
            'seo' => SeoService::page(__('cart.title'), null, ['noindex' => true]),
        ]);
    }

    private function done(array $result, string $successMessage): Response
    {
        if ($this->request->isAjax()) {
            return $this->json($result + ['count' => CartService::count(), 'message' => $result['ok'] ? $successMessage : ($result['error'] ?? '')], $result['ok'] ? 200 : 422);
        }
        $this->flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $successMessage : (string) ($result['error'] ?? ''));
        return $result['ok'] ? $this->redirectRoute('cart.index') : $this->back();
    }

    public function add(): Response
    {
        $qty = max(1, $this->request->int('quantity', 1));
        $design = $this->request->str('design');
        $productId = $this->request->int('product_id');
        $variantId = $this->request->int('variant_id');
        // personalisation of gifts / laser products (invalid or missing fields: back to the product with the errors)
        $perso = $this->request->input('perso', []);
        try {
            $result = $design !== '' ? CartService::addDesign($design, $qty) : CartService::addProduct($productId, $variantId > 0 ? $variantId : null, $qty, is_array($perso) ? $perso : []);
        } catch (\App\Core\ValidationException $e) {
            throw new \App\Core\ValidationException($e->errors, $this->request->all());
        }
        if ($result['ok']) {
            // analytics: the line just added (fired on the next page)
            $cart = CartService::current(false);
            foreach ($cart !== null ? CartService::lines($cart) : [] as $l) {
                if (($design !== '' && $l['kind'] === 'design' && ($l['design_uuid'] ?? '') === $design)
                    || ($design === '' && $l['kind'] === 'product' && $l['product_id'] === $productId && ($variantId <= 0 || $l['variant_id'] === $variantId))) {
                    $item = \App\Services\Tracking\TrackingService::cartItems([$l])[0] ?? null;
                    if ($item !== null) {
                        $item['quantity'] = $qty;
                        \App\Services\Tracking\TrackingService::queue('add_to_cart', ['items' => [$item]]);
                    }
                    break;
                }
            }
        }
        return $this->done($result, __('cart.added'));
    }

    public function update(string $id): Response
    {
        return $this->done(CartService::updateQuantity((int) $id, $this->request->int('quantity', 1)), __('cart.updated'));
    }

    public function remove(string $id): Response
    {
        return $this->done(CartService::remove((int) $id), __('cart.removed'));
    }

    public function coupon(): Response
    {
        if ($this->request->bool('remove')) {
            CartService::removeCoupon();
            return $this->done(['ok' => true], __('cart.coupon_removed'));
        }
        return $this->done(CartService::applyCoupon($this->request->str('code')), __('cart.coupon_applied'));
    }

    /** Shipping estimate for a city (cart page selector, checkout live update). */
    public function shipping(): Response
    {
        $city = mb_substr($this->request->str('city'), 0, 100);
        \App\Core\Session::put('cart_city', $city);
        $cart = CartService::current(false);
        if ($cart === null) {
            return $this->json(['ok' => false], 404);
        }
        $t = CheckoutCalculator::totals($cart, CartService::lines($cart), $city);
        return $this->json([
            'ok' => true,
            'shipping' => $t['shipping'] ? ($t['shipping']['free'] ? __('cart.free') : money($t['shipping_total'])) : '—',
            'estimate' => $t['shipping'] ? __('cart.delivery_between', ['min' => format_date($t['shipping']['date_min']), 'max' => format_date($t['shipping']['date_max'])]) : '',
            'tax' => money($t['tax_total']),
            'total' => money($t['grand_total']),
            'free_hint' => $t['shipping'] && !$t['shipping']['free'] && $t['shipping']['threshold'] !== null
                ? __('cart.free_from', ['amount' => money($t['shipping']['threshold'])]) : '',
        ]);
    }
}
