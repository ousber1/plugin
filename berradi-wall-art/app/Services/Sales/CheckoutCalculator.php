<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\Auth;

/**
 * Order totals, identical for the cart page, the checkout page and order creation.
 *
 *   subtotal  = Σ line totals (current prices)
 *   discount  = coupon (re-validated every time)
 *   shipping  = ShippingService (city, threshold on subtotal − discount, free-shipping coupon)
 *   tax (TVA) = on (subtotal − discount + shipping)
 *               prices entered TTC (default): tax is the included part, grand total unchanged
 *               prices entered HT: tax is added on top
 */
final class CheckoutCalculator
{
    /**
     * @param list<array<string,mixed>> $lines
     * @return array{subtotal:string,discount:string,shipping:array<string,mixed>|null,shipping_total:string,tax_rate:string,tax_total:string,
     *               tax_included:bool,grand_total:string,coupon:?array<string,mixed>,coupon_error:?string,item_count:int,has_unavailable:bool}
     */
    public static function totals(array $cart, array $lines, ?string $city = null, ?string $email = null): array
    {
        $available = array_values(array_filter($lines, fn ($l) => $l['available']));
        $subtotal = Money::add('0', ...array_map(fn ($l) => $l['line_total'], $available));
        $discount = '0.00';
        $coupon = null;
        $couponError = null;
        $freeShip = false;
        if (!empty($cart['coupon_id'])) {
            $code = (string) Database::instance()->value('SELECT code FROM coupons WHERE id = ?', [(int) $cart['coupon_id']]);
            $customer = Auth::customer();
            $r = CouponService::evaluate($code, $available, $subtotal, $customer['id'] ?? null, $email ?? ($customer['email'] ?? $cart['email'] ?? null));
            if ($r['ok']) {
                $coupon = $r['coupon'];
                $discount = $r['discount'];
                $freeShip = $r['free_shipping'];
            } else {
                $couponError = $r['error'];
            }
        }
        $afterDiscount = Money::sub($subtotal, $discount);
        $shipping = $city !== null && trim($city) !== '' ? ShippingService::quote($city, $afterDiscount, $freeShip) : null;
        $shippingTotal = $shipping['price'] ?? '0.00';
        $base = Money::add($afterDiscount, $shippingTotal);

        $taxEnabled = (bool) setting('tax_enabled', true);
        $rate = $taxEnabled ? (string) setting('tax_default_rate', '20') : '0';
        $included = (bool) setting('tax_prices_include_tax', true);
        if (!$taxEnabled || Money::toCents($base) === 0) {
            $tax = '0.00';
            $grand = $base;
        } elseif ($included) {
            $tax = Money::sub($base, Money::htFromTtc($base, $rate));
            $grand = $base;
        } else {
            $tax = Money::percent($base, $rate);
            $grand = Money::add($base, $tax);
        }
        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'shipping_total' => $shippingTotal,
            'tax_rate' => $rate,
            'tax_total' => $tax,
            'tax_included' => $included,
            'grand_total' => $grand,
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'item_count' => array_sum(array_map(fn ($l) => (int) $l['quantity'], $available)),
            'has_unavailable' => count($available) !== count($lines),
        ];
    }
}
