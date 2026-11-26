<?php

declare(strict_types=1);

namespace App\Services\Sales;

/**
 * Order lifecycle (stored in orders.status). Labels live in resources/lang/{fr,ar}/orders.php.
 */
final class OrderStatus
{
    public const NEW = 'new';
    public const AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const CONFIRMED = 'confirmed';
    public const AWAITING_ARTWORK_APPROVAL = 'awaiting_artwork_approval';
    public const APPROVED_FOR_PRODUCTION = 'approved_for_production';
    public const PRINTING = 'printing';
    public const FRAMING = 'framing';
    public const QUALITY_CONTROL = 'quality_control';
    public const READY_FOR_SHIPPING = 'ready_for_shipping';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';
    public const RETURNED = 'returned';

    /** Ordered list used for timelines and admin selects. */
    public const ALL = [
        self::NEW, self::AWAITING_CONFIRMATION, self::CONFIRMED, self::AWAITING_ARTWORK_APPROVAL, self::APPROVED_FOR_PRODUCTION,
        self::PRINTING, self::FRAMING, self::QUALITY_CONTROL, self::READY_FOR_SHIPPING, self::SHIPPED, self::DELIVERED,
        self::CANCELLED, self::RETURNED,
    ];

    /** Customer-facing milestones (the detailed workshop steps are grouped). */
    public const TIMELINE = [
        'received' => [self::NEW, self::AWAITING_CONFIRMATION],
        'confirmed' => [self::CONFIRMED, self::AWAITING_ARTWORK_APPROVAL, self::APPROVED_FOR_PRODUCTION],
        'production' => [self::PRINTING, self::FRAMING, self::QUALITY_CONTROL, self::READY_FOR_SHIPPING],
        'shipped' => [self::SHIPPED],
        'delivered' => [self::DELIVERED],
    ];

    public static function label(string $status): string
    {
        return __('orders.status_' . $status);
    }

    /** Statuses in which the customer may still cancel (admin setting, safe default). @return list<string> */
    public static function cancellable(): array
    {
        $v = setting('order_cancel_allowed_statuses', [self::NEW, self::AWAITING_CONFIRMATION, self::CONFIRMED]);
        $v = is_array($v) ? $v : [self::NEW, self::AWAITING_CONFIRMATION];
        // production has started from these statuses on: never cancellable by the customer
        return array_values(array_intersect($v, [self::NEW, self::AWAITING_CONFIRMATION, self::CONFIRMED, self::AWAITING_ARTWORK_APPROVAL]));
    }

    /** Index of the customer milestone reached (-1 when cancelled/returned). */
    public static function milestone(string $status): int
    {
        $i = 0;
        foreach (self::TIMELINE as $statuses) {
            if (in_array($status, $statuses, true)) {
                return $i;
            }
            $i++;
        }
        return -1;
    }
}
