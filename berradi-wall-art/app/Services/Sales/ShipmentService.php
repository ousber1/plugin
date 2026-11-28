<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\ActivityLogger;

/**
 * Parcels handed to a courier. Creating a shipment can mark the order as shipped (with tracking e-mail);
 * delivery / return updates move the order accordingly. The real courier cost feeds the profit figures.
 */
final class ShipmentService
{
    public const STATUSES = ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'failed'];
    public const COURIERS = ['Amana', 'CTM Messagerie', 'Aramex', 'DHL', 'Sendit', 'Cathedis', 'Ozon Express', 'Livreur interne', 'Retrait atelier'];

    /** Order statuses from which a parcel may be created. */
    public const SHIPPABLE = [OrderStatus::READY_FOR_SHIPPING, OrderStatus::SHIPPED];

    /**
     * @param array{courier:string,tracking_number?:?string,tracking_url?:?string,shipping_cost?:?string,cod_amount?:?string,packages?:int,notes?:?string} $d
     * @return int shipment id
     */
    public static function create(int $orderId, array $d, ?int $userId, bool $markShipped = true, bool $notify = true): int
    {
        $db = Database::instance();
        $order = $db->fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            throw new WorkflowException(__('orders.err_not_found'));
        }
        if (!in_array($order['status'], self::SHIPPABLE, true)) {
            throw new WorkflowException(__('orders.err_not_shippable', ['status' => OrderStatus::label((string) $order['status'])]));
        }
        $url = trim((string) ($d['tracking_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https://#i', $url)) {
            throw new WorkflowException(__('orders.err_tracking_url'));
        }
        $cod = trim((string) ($d['cod_amount'] ?? ''));
        if ($cod !== '' && Money::compare(Money::normalize($cod), (string) $order['balance_due']) > 0) {
            throw new WorkflowException(__('orders.err_cod', ['max' => money($order['balance_due'])]));
        }
        $now = date('Y-m-d H:i:s');
        $row = [
            'order_id' => $orderId, 'courier' => mb_substr(trim($d['courier']), 0, 100),
            'tracking_number' => ($t = trim((string) ($d['tracking_number'] ?? ''))) !== '' ? mb_substr($t, 0, 100) : null,
            'tracking_url' => $url !== '' ? mb_substr($url, 0, 255) : null,
            'status' => $markShipped ? 'in_transit' : 'pending',
            'shipping_cost' => Money::normalize((string) (($d['shipping_cost'] ?? '') ?: '0')),
            'cod_amount' => Money::normalize($cod !== '' ? $cod : '0'),
            'packages' => max(1, min(50, (int) ($d['packages'] ?? 1))),
            'shipped_at' => $markShipped ? $now : null,
            'notes' => ($n = trim((string) ($d['notes'] ?? ''))) !== '' ? mb_substr($n, 0, 1000) : null,
            'created_by' => $userId,
        ];
        $id = $db->insert('shipments', $row);
        ActivityLogger::log('shipment.created', 'order', $orderId, $row['courier'] . ' ' . ($row['tracking_number'] ?? ''));
        if ($markShipped && $order['status'] !== OrderStatus::SHIPPED) {
            OrderWorkflow::change($orderId, OrderStatus::SHIPPED, [
                'comment' => __('orders.shipped_with', ['courier' => $row['courier']]) . ($row['tracking_number'] ? ' — ' . $row['tracking_number'] : ''),
                'notify' => $notify, 'user_id' => $userId, 'shipment' => $row,
            ]);
        }
        return $id;
    }

    /** Courier status update; delivery / return move the order. */
    public static function updateStatus(int $shipmentId, string $status, ?int $userId, bool $notify = true): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new WorkflowException(__('orders.err_unknown_status'));
        }
        $db = Database::instance();
        $s = $db->fetch('SELECT s.*, o.status AS order_status FROM shipments s JOIN orders o ON o.id = s.order_id WHERE s.id = ?', [$shipmentId]);
        if ($s === null) {
            throw new WorkflowException(__('orders.err_not_found'));
        }
        $values = ['status' => $status];
        if ($status === 'delivered') {
            $values['delivered_at'] = date('Y-m-d H:i:s');
        }
        if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery'], true) && $s['shipped_at'] === null) {
            $values['shipped_at'] = date('Y-m-d H:i:s');
        }
        $db->update('shipments', $values, ['id' => $shipmentId]);
        ActivityLogger::log('shipment.' . $status, 'order', (int) $s['order_id']);
        $orderId = (int) $s['order_id'];
        if ($status === 'delivered' && $s['order_status'] === OrderStatus::SHIPPED) {
            // the order is delivered when no other parcel is still on its way
            $open = (int) $db->value("SELECT COUNT(*) FROM shipments WHERE order_id = ? AND status NOT IN ('delivered','returned','failed')", [$orderId]);
            if ($open === 0) {
                OrderWorkflow::change($orderId, OrderStatus::DELIVERED, ['comment' => __('orders.delivered_by', ['courier' => $s['courier']]), 'notify' => $notify, 'user_id' => $userId]);
            }
        } elseif ($status === 'returned' && in_array($s['order_status'], [OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)) {
            OrderWorkflow::change($orderId, OrderStatus::RETURNED, ['comment' => __('orders.returned_by', ['courier' => $s['courier']]), 'notify' => false, 'user_id' => $userId]);
        } elseif (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery'], true) && $s['order_status'] === OrderStatus::READY_FOR_SHIPPING) {
            OrderWorkflow::change($orderId, OrderStatus::SHIPPED, ['comment' => __('orders.shipped_with', ['courier' => $s['courier']]), 'notify' => $notify, 'user_id' => $userId, 'shipment' => $s]);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function forOrder(int $orderId): array
    {
        return Database::instance()->fetchAll('SELECT * FROM shipments WHERE order_id = ? ORDER BY id', [$orderId]);
    }
}
