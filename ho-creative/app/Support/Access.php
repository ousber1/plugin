<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Quote;
use App\Models\User;

/**
 * Record-level access rules for customer data. Used by controllers and the
 * protected file endpoint so copying a direct URL never bypasses them.
 */
class Access
{
    public const STAFF_ORDER_PERMS = ['orders.view', 'orders.manage', 'artwork.manage', 'proofs.manage', 'production.view', 'production.manage', 'delivery.manage', 'finance.view'];

    public static function staffCan(?User $u, array $perms): bool
    {
        if (! $u || ! $u->is_staff || ! $u->is_active) {
            return false;
        }
        foreach ($perms as $p) {
            if ($u->can($p)) {
                return true;
            }
        }

        return false;
    }

    public static function canViewOrder(?User $u, Order $order): bool
    {
        if (self::staffCan($u, self::STAFF_ORDER_PERMS)) {
            return true;
        }
        if ($u && ! $u->is_staff && ($order->user_id === $u->id || ($u->customer_id && $order->customer_id === $u->customer_id))) {
            return true;
        }

        // guest who opened the secret tracking link in this session
        return $order->guest_token && in_array($order->guest_token, (array) session('guest_tokens', []), true);
    }

    public static function canViewQuote(?User $u, Quote $quote): bool
    {
        if (self::staffCan($u, ['quotes.manage', 'finance.view'])) {
            return true;
        }
        if ($u && $u->customer_id && $quote->customer_id === $u->customer_id) {
            return true;
        }

        return in_array($quote->token, (array) session('quote_tokens', []), true);
    }
}
