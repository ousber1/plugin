<?php

namespace App\Services\Pricing;

use App\Models\Coupon;
use App\Models\ShippingZone;
use App\Support\Money;
use App\Support\Settings;
use Brick\Math\BigDecimal;

/**
 * Document-level totals, applied after line pricing:
 *   9.  subtotal HT = Σ line totals (after manual line discounts)
 *   10. coupon discount (percent of eligible lines, or fixed capped at eligible amount)
 *   11. extra manual discount (staff, recorded with a reason)
 *   12. shipping (zone price; free when discounted subtotal ≥ free_over; pickup = 0)
 *   13. tax = rate × (subtotal − discounts + shipping if taxable), rounded HALF_UP to 2 decimals
 *   14. total TTC = subtotal − discounts + shipping + tax
 */
class Totals
{
    public function __construct(protected Settings $settings) {}

    /**
     * @param  array<int, array{total:string, product_id:?int}>  $lines
     */
    public function compute(array $lines, ?Coupon $coupon = null, ?ShippingZone $zone = null, string $shippingMethod = 'pickup', mixed $manualDiscount = 0): array
    {
        $subtotal = Money::sum(array_column($lines, 'total'));
        $couponDiscount = BigDecimal::zero();
        $couponError = null;

        if ($coupon) {
            if (! $coupon->isUsable()) {
                $couponError = __('Ce code promo n\'est plus valable.');
            } elseif ($coupon->min_subtotal && $subtotal->isLessThan($coupon->min_subtotal)) {
                $couponError = __('Montant minimum requis pour ce code : :min', ['min' => Money::format($coupon->min_subtotal)]);
            } else {
                $eligible = Money::sum(array_map(fn ($l) => (! $coupon->product_ids || in_array($l['product_id'] ?? null, $coupon->product_ids)) ? $l['total'] : 0, $lines));
                $couponDiscount = $coupon->type === 'fixed'
                    ? Money::min($coupon->value, $eligible)
                    : Money::percent($eligible, $coupon->value);
                $couponDiscount = Money::round($couponDiscount);
            }
        }
        $manual = Money::min(Money::round($manualDiscount), $subtotal->minus($couponDiscount));
        $discount = $couponDiscount->plus($manual);
        $afterDiscount = $subtotal->minus($discount);

        $shipping = BigDecimal::zero();
        if ($shippingMethod === 'delivery' && $zone) {
            $shipping = Money::of($zone->price);
            if ($zone->free_over !== null && $afterDiscount->isGreaterThanOrEqualTo($zone->free_over)) {
                $shipping = BigDecimal::zero();
            }
        }

        $rate = $this->settings->bool('tax.enabled') ? Money::of($this->settings->get('tax.rate', 0)) : BigDecimal::zero();
        $taxBase = $afterDiscount->plus($this->settings->bool('tax.shipping_taxable') ? $shipping : 0);
        $tax = Money::round(Money::percent($taxBase, $rate));
        $total = $afterDiscount->plus($shipping)->plus($tax);

        return [
            'subtotal' => Money::str($subtotal),
            'coupon_discount' => Money::str($couponDiscount),
            'manual_discount' => Money::str($manual),
            'discount_total' => Money::str($discount),
            'shipping_total' => Money::str($shipping),
            'tax_rate' => Money::str($rate),
            'tax_total' => Money::str($tax),
            'total' => Money::str($total),
            'coupon_error' => $couponError,
        ];
    }
}
