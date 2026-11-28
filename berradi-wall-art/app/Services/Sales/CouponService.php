<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Helpers\Money;

/**
 * Discount coupons: percent, fixed amount, free shipping; optional product / category scope,
 * minimum order, maximum discount, global and per-customer usage limits, validity dates.
 */
final class CouponService
{
    /**
     * @param list<array<string,mixed>> $lines cart lines with product_id, category_id, line_total
     * @return array{ok:bool,error?:string,coupon?:array<string,mixed>,discount?:string,free_shipping?:bool}
     */
    public static function evaluate(string $code, array $lines, string $subtotal, ?int $customerId = null, ?string $email = null): array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{2,50}$/', $code)) {
            return ['ok' => false, 'error' => __('cart.coupon_invalid')];
        }
        $c = Database::instance()->fetch('SELECT * FROM coupons WHERE UPPER(code) = ? AND is_active = 1', [$code]);
        if ($c === null) {
            return ['ok' => false, 'error' => __('cart.coupon_invalid')];
        }
        $now = time();
        if (($c['starts_at'] !== null && strtotime((string) $c['starts_at']) > $now) || ($c['ends_at'] !== null && strtotime((string) $c['ends_at']) < $now)) {
            return ['ok' => false, 'error' => __('cart.coupon_expired')];
        }
        if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
            return ['ok' => false, 'error' => __('cart.coupon_exhausted')];
        }
        if ($c['min_order_amount'] !== null && Money::compare($subtotal, (string) $c['min_order_amount']) < 0) {
            return ['ok' => false, 'error' => __('cart.coupon_min', ['amount' => money((string) $c['min_order_amount'])])];
        }
        if ($c['usage_limit_per_customer'] !== null && ($customerId !== null || ($email !== null && $email !== ''))) {
            $used = (int) Database::instance()->value(
                'SELECT COUNT(*) FROM coupon_redemptions cr JOIN orders o ON o.id = cr.order_id
                 WHERE cr.coupon_id = ? AND o.status <> ? AND (' . ($customerId !== null ? 'cr.customer_id = ? OR ' : '') . 'LOWER(o.customer_email) = ?)',
                array_merge([(int) $c['id'], OrderStatus::CANCELLED], $customerId !== null ? [$customerId] : [], [mb_strtolower((string) $email)])
            );
            if ($used >= (int) $c['usage_limit_per_customer']) {
                return ['ok' => false, 'error' => __('cart.coupon_used')];
            }
        }

        // eligible base: whole cart, or only lines of the product / category
        $eligible = 0;
        foreach ($lines as $l) {
            $okProduct = $c['product_id'] === null || (int) ($l['product_id'] ?? 0) === (int) $c['product_id'];
            $okCategory = $c['category_id'] === null || (int) ($l['category_id'] ?? 0) === (int) $c['category_id'];
            if ($okProduct && $okCategory) {
                $eligible += Money::toCents((string) $l['line_total']);
            }
        }
        if ($eligible === 0 && $c['discount_type'] !== 'free_shipping') {
            return ['ok' => false, 'error' => __('cart.coupon_not_applicable')];
        }
        $discount = match ($c['discount_type']) {
            'percent' => Money::toCents(Money::percent(Money::fromCents($eligible), (string) $c['discount_value'])),
            'fixed' => Money::toCents((string) $c['discount_value']),
            default => 0,
        };
        if ($c['max_discount_amount'] !== null) {
            $discount = min($discount, Money::toCents((string) $c['max_discount_amount']));
        }
        $discount = max(0, min($discount, $eligible));
        return [
            'ok' => true,
            'coupon' => $c,
            'discount' => Money::fromCents($discount),
            'free_shipping' => $c['discount_type'] === 'free_shipping',
        ];
    }

    /**
     * Record the use inside the order transaction. The conditional UPDATE makes the usage limit safe
     * under concurrency (two orders cannot both take the last use).
     */
    public static function redeem(Database $db, int $couponId, int $orderId, ?int $customerId, string $discount): bool
    {
        $ok = $db->query(
            'UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)',
            [$couponId]
        )->rowCount() === 1;
        if ($ok) {
            $db->insert('coupon_redemptions', ['coupon_id' => $couponId, 'order_id' => $orderId, 'customer_id' => $customerId, 'discount_amount' => $discount]);
        }
        return $ok;
    }

    /** Give the use back (order cancelled). */
    public static function release(Database $db, int $orderId): void
    {
        foreach ($db->fetchAll('SELECT coupon_id FROM coupon_redemptions WHERE order_id = ?', [$orderId]) as $r) {
            $db->query('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [(int) $r['coupon_id']]);
        }
        $db->query('DELETE FROM coupon_redemptions WHERE order_id = ?', [$orderId]);
    }
}
