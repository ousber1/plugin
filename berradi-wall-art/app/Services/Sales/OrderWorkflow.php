<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Services\ActivityLogger;
use App\Services\Operations\ProductionService;

/**
 * Staff-driven order lifecycle: allowed transitions, timestamps, history, side effects
 * (production jobs, stock/coupon release on cancellation) and customer notification.
 */
final class OrderWorkflow
{
    /** status => statuses it may move to */
    public const TRANSITIONS = [
        OrderStatus::NEW => [OrderStatus::AWAITING_CONFIRMATION, OrderStatus::CONFIRMED, OrderStatus::CANCELLED],
        OrderStatus::AWAITING_CONFIRMATION => [OrderStatus::CONFIRMED, OrderStatus::CANCELLED],
        OrderStatus::CONFIRMED => [OrderStatus::AWAITING_ARTWORK_APPROVAL, OrderStatus::APPROVED_FOR_PRODUCTION, OrderStatus::CANCELLED],
        OrderStatus::AWAITING_ARTWORK_APPROVAL => [OrderStatus::APPROVED_FOR_PRODUCTION, OrderStatus::CONFIRMED, OrderStatus::CANCELLED],
        OrderStatus::APPROVED_FOR_PRODUCTION => [OrderStatus::PRINTING, OrderStatus::CANCELLED],
        OrderStatus::PRINTING => [OrderStatus::FRAMING, OrderStatus::QUALITY_CONTROL, OrderStatus::CANCELLED],
        OrderStatus::FRAMING => [OrderStatus::QUALITY_CONTROL, OrderStatus::PRINTING, OrderStatus::CANCELLED],
        OrderStatus::QUALITY_CONTROL => [OrderStatus::READY_FOR_SHIPPING, OrderStatus::PRINTING, OrderStatus::CANCELLED],
        OrderStatus::READY_FOR_SHIPPING => [OrderStatus::SHIPPED, OrderStatus::QUALITY_CONTROL, OrderStatus::CANCELLED],
        OrderStatus::SHIPPED => [OrderStatus::DELIVERED, OrderStatus::RETURNED],
        OrderStatus::DELIVERED => [OrderStatus::RETURNED],
        OrderStatus::CANCELLED => [],
        OrderStatus::RETURNED => [],
    ];

    /** Workshop chain: the production board may only push an order forward along it. */
    public const PRODUCTION_CHAIN = [
        OrderStatus::APPROVED_FOR_PRODUCTION, OrderStatus::PRINTING, OrderStatus::FRAMING, OrderStatus::QUALITY_CONTROL, OrderStatus::READY_FOR_SHIPPING,
    ];

    /** @return list<string> */
    public static function allowed(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::allowed($from), true);
    }

    /**
     * Change the status of an order.
     *
     * @param array{comment?:string,notify?:bool,user_id?:?int,force?:bool,shipment?:array<string,mixed>|null} $opts
     * @throws WorkflowException when the transition is not allowed
     */
    public static function change(int $orderId, string $to, array $opts = []): array
    {
        if (!in_array($to, OrderStatus::ALL, true)) {
            throw new WorkflowException(__('orders.err_unknown_status'));
        }
        $comment = trim((string) ($opts['comment'] ?? ''));
        $userId = $opts['user_id'] ?? null;
        $db = Database::instance();
        $order = $db->transaction(function (Database $db) use ($orderId, $to, $comment, $userId, $opts): array {
            $o = $db->fetch('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if ($o === null) {
                throw new WorkflowException(__('orders.err_not_found'));
            }
            $from = (string) $o['status'];
            if ($from === $to) {
                throw new WorkflowException(__('orders.err_same_status'));
            }
            if (empty($opts['force']) && !self::canMove($from, $to)) {
                throw new WorkflowException(__('orders.err_transition', ['from' => OrderStatus::label($from), 'to' => OrderStatus::label($to)]));
            }
            if ($to === OrderStatus::APPROVED_FOR_PRODUCTION) {
                $pending = (int) $db->value(
                    "SELECT COUNT(*) FROM order_customizations oc JOIN order_items oi ON oi.id = oc.order_item_id WHERE oi.order_id = ? AND oc.approval_status <> 'approved'",
                    [$orderId]
                );
                if ($pending > 0 && empty($opts['force'])) {
                    throw new WorkflowException(__('orders.err_artwork_pending', ['n' => $pending]));
                }
            }
            $now = date('Y-m-d H:i:s');
            $values = ['status' => $to];
            match ($to) {
                OrderStatus::CONFIRMED => $values['confirmed_at'] = $o['confirmed_at'] ?? $now,
                OrderStatus::SHIPPED => $values['shipped_at'] = $now,
                OrderStatus::DELIVERED => $values['delivered_at'] = $now,
                OrderStatus::CANCELLED => $values += ['cancelled_at' => $now],
                default => null,
            };
            if ($to === OrderStatus::CANCELLED) {
                // nothing more is owed: what was paid stays recorded (refund handled in payments)
                $values['balance_due'] = '0.00';
                OrderService::releaseForCancellation($db, $orderId);
                $db->query("UPDATE production_jobs SET status = 'cancelled' WHERE order_id = ? AND status IN ('pending','in_progress','on_hold')", [$orderId]);
            }
            if (in_array($from, [OrderStatus::NEW, OrderStatus::AWAITING_CONFIRMATION], true) && $to === OrderStatus::CONFIRMED && $o['confirmed_at'] === null) {
                $values['confirmed_at'] = $now;
            }
            $db->update('orders', $values, ['id' => $orderId]);
            $db->insert('order_status_history', [
                'order_id' => $orderId, 'from_status' => $from, 'to_status' => $to,
                'comment' => $comment !== '' ? mb_substr($comment, 0, 1000) : null,
                'customer_notified' => !empty($opts['notify']) && !empty($o['customer_email']) ? 1 : 0,
                'changed_by' => $userId,
            ]);
            if ($to === OrderStatus::APPROVED_FOR_PRODUCTION) {
                ProductionService::createJobsForOrder($orderId, $userId);
            }
            if ($to === OrderStatus::SHIPPED) {
                ProductionService::completeStage($orderId, 'ship');
            }
            return array_merge($o, $values);
        });
        ActivityLogger::log('order.status', 'order', $orderId, $order['order_number'] . ' → ' . $to);
        if (!empty($opts['notify'])) {
            OrderMailer::statusChanged($order, $comment, $opts['shipment'] ?? null);
        }
        return $order;
    }

    /**
     * Production progress: move the order forward along the workshop chain only (never backwards,
     * never out of a status that is not part of the chain).
     */
    public static function advanceProduction(int $orderId, string $to, ?int $userId = null): bool
    {
        $current = (string) Database::instance()->value('SELECT status FROM orders WHERE id = ?', [$orderId]);
        $from = array_search($current, self::PRODUCTION_CHAIN, true);
        $target = array_search($to, self::PRODUCTION_CHAIN, true);
        if ($from === false || $target === false || $target <= $from) {
            return false;
        }
        self::change($orderId, $to, ['comment' => __('orders.auto_production'), 'user_id' => $userId, 'force' => true]);
        return true;
    }
}
