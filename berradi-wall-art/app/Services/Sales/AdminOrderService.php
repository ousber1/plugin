<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\CatalogService;
use App\Services\SequenceService;

/**
 * Orders taken by staff (phone, WhatsApp, showroom): catalogue lines at the current shop price
 * (or a negotiated price) and free custom lines, same totals/tax rules as the storefront.
 */
final class AdminOrderService
{
    public const SOURCES = ['phone', 'whatsapp', 'admin'];

    /**
     * @param array<string,mixed> $d customer/shipping/payment fields (validated by the controller)
     * @param list<array<string,mixed>> $lines [{kind:'catalog',product_id,variant_id,quantity,unit_price?} | {kind:'custom',title,width_cm,height_cm,material_name,frame_name,quantity,unit_price,unit_cost}]
     * @return array<string,mixed> the order row
     */
    public static function create(array $d, array $lines, int $userId): array
    {
        $prepared = self::prepareLines($lines);
        $subtotal = Money::add('0', ...array_map(fn ($l) => $l['line_total'], $prepared));
        $discount = Money::normalize((string) (($d['discount'] ?? '') ?: '0'));
        if (Money::compare($discount, $subtotal) > 0) {
            throw new ValidationException(['discount' => __('orders.err_discount')]);
        }
        $after = Money::sub($subtotal, $discount);
        if (($d['shipping'] ?? '') !== '' && $d['shipping'] !== null) {
            $shipping = ['price' => Money::normalize((string) $d['shipping'])];
        } else {
            $shipping = ShippingService::quote((string) $d['city'], $after);
        }
        $base = Money::add($after, $shipping['price']);
        $taxEnabled = (bool) setting('tax_enabled', true);
        $rate = $taxEnabled ? (string) setting('tax_default_rate', '20') : '0';
        if (!$taxEnabled || Money::toCents($base) === 0) {
            $tax = '0.00';
            $grand = $base;
        } elseif ((bool) setting('tax_prices_include_tax', true)) {
            $tax = Money::sub($base, Money::htFromTtc($base, $rate));
            $grand = $base;
        } else {
            $tax = Money::percent($base, $rate);
            $grand = Money::add($base, $tax);
        }
        $db = Database::instance();
        $order = $db->transaction(function (Database $db) use ($d, $prepared, $subtotal, $discount, $shipping, $tax, $grand, $rate, $userId): array {
            foreach ($prepared as $l) {
                if ($l['kind'] === 'catalog' && $l['track_stock']) {
                    $stock = (int) $db->value('SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE', [$l['product_id']]);
                    if ($l['variant_id'] !== null) {
                        $stock = min($stock, (int) $db->value('SELECT stock_quantity FROM product_variants WHERE id = ? FOR UPDATE', [$l['variant_id']]));
                    }
                    if ($stock < $l['quantity']) {
                        throw new ValidationException(['lines' => __('cart.stock_left', ['count' => max(0, $stock)]) . ' — ' . $l['title']]);
                    }
                    $db->query('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?', [$l['quantity'], $l['product_id']]);
                    if ($l['variant_id'] !== null) {
                        $db->query('UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ?', [$l['quantity'], $l['variant_id']]);
                    }
                }
            }
            $customerId = self::resolveCustomer($db, $d);
            $cost = 0;
            foreach ($prepared as $l) {
                $cost += Money::toCents($l['unit_cost']) * $l['quantity'];
            }
            $token = bin2hex(random_bytes(24));
            $orderId = $db->insert('orders', [
                'order_number' => SequenceService::next('order'), 'customer_id' => $customerId, 'status' => OrderStatus::CONFIRMED,
                'payment_status' => 'unpaid', 'payment_method_id' => !empty($d['payment_method_id']) ? (int) $d['payment_method_id'] : null,
                'source' => in_array($d['source'] ?? '', self::SOURCES, true) ? $d['source'] : 'admin', 'locale' => $d['locale'] ?? 'fr',
                'currency' => (string) setting('currency_code', 'MAD'),
                'customer_name' => $d['full_name'], 'customer_email' => ($d['email'] ?? '') !== '' ? mb_strtolower((string) $d['email']) : null, 'customer_phone' => $d['phone'],
                'shipping_country' => 'MA', 'shipping_city' => $d['city'], 'shipping_address' => $d['address'],
                'shipping_postal_code' => $d['postal_code'] ?? null, 'delivery_instructions' => $d['delivery_instructions'] ?? null,
                'shipping_zone_id' => $shipping['zone_id'] ?? null, 'shipping_rate_id' => $shipping['rate_id'] ?? null,
                'estimated_delivery_min' => $shipping['date_min'] ?? null, 'estimated_delivery_max' => $shipping['date_max'] ?? null,
                'subtotal' => $subtotal, 'discount_total' => $discount, 'shipping_total' => $shipping['price'], 'tax_total' => $tax,
                'grand_total' => $grand, 'amount_paid' => '0.00', 'balance_due' => $grand, 'cost_total' => Money::fromCents($cost),
                'customer_notes' => $d['customer_notes'] ?? null, 'internal_notes' => $d['internal_notes'] ?? null,
                'access_token' => hash('sha256', $token), 'confirmed_at' => date('Y-m-d H:i:s'), 'created_by' => $userId,
            ]);
            foreach ($prepared as $l) {
                $db->insert('order_items', [
                    'order_id' => $orderId, 'product_id' => $l['product_id'], 'product_variant_id' => $l['variant_id'], 'sku' => $l['sku'],
                    'title' => mb_substr($l['title'], 0, 191), 'product_type' => $l['product_type'], 'width_cm' => $l['width_cm'], 'height_cm' => $l['height_cm'],
                    'material_name' => $l['material_name'], 'frame_name' => $l['frame_name'], 'panels' => $l['panels'], 'quantity' => $l['quantity'],
                    'unit_price' => $l['unit_price'], 'unit_cost' => $l['unit_cost'], 'tax_rate' => $rate, 'line_total' => $l['line_total'],
                    'options' => json_encode($l['options'], JSON_UNESCAPED_UNICODE), 'preview_path' => $l['image'],
                ]);
            }
            $db->insert('order_status_history', [
                'order_id' => $orderId, 'from_status' => null, 'to_status' => OrderStatus::CONFIRMED,
                'comment' => __('orders.created_by_staff', ['source' => __('orders.source_' . (in_array($d['source'] ?? '', self::SOURCES, true) ? $d['source'] : 'admin'))]),
                'changed_by' => $userId,
            ]);
            return $db->fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        });
        ActivityLogger::log('order.created_admin', 'order', (int) $order['id'], (string) $order['order_number']);
        return $order;
    }

    /**
     * @param list<array<string,mixed>> $lines
     * @return list<array<string,mixed>>
     */
    private static function prepareLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $qty = (int) ($l['quantity'] ?? 0);
            if ($qty < 1 || $qty > 999) {
                throw new ValidationException(['lines' => __('orders.err_line_qty', ['n' => $n])]);
            }
            $price = trim(str_replace([' ', ','], ['', '.'], (string) ($l['unit_price'] ?? '')));
            if ($price !== '' && !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price)) {
                throw new ValidationException(['lines' => __('orders.err_line_price', ['n' => $n])]);
            }
            if (($l['kind'] ?? '') === 'catalog') {
                $p = CatalogService::purchasable((int) ($l['product_id'] ?? 0), !empty($l['variant_id']) ? (int) $l['variant_id'] : null);
                if ($p === null) {
                    throw new ValidationException(['lines' => __('orders.err_line_product', ['n' => $n])]);
                }
                $unit = $price !== '' ? Money::normalize($price) : $p['unit_price'];
                $out[] = [
                    'kind' => 'catalog', 'product_id' => $p['product_id'], 'variant_id' => $p['variant_id'], 'sku' => $p['sku'],
                    'title' => $p['title'], 'product_type' => $p['product_type'], 'width_cm' => $p['width_cm'], 'height_cm' => $p['height_cm'],
                    'material_name' => $p['material_name'], 'frame_name' => $p['frame_name'], 'panels' => $p['panels'], 'quantity' => $qty,
                    'unit_price' => $unit, 'unit_cost' => $p['unit_cost'], 'line_total' => Money::mul($unit, $qty), 'track_stock' => $p['track_stock'],
                    'image' => $p['image'], 'options' => ['variant' => $p['variant_name'], 'slug' => $p['slug']] + ($price !== '' ? ['negotiated_from' => $p['unit_price']] : []),
                ];
            } else {
                $title = trim((string) ($l['title'] ?? ''));
                if ($title === '' || $price === '') {
                    throw new ValidationException(['lines' => __('orders.err_line_custom', ['n' => $n])]);
                }
                $dim = fn ($v) => ($v = str_replace(',', '.', trim((string) $v))) !== '' && is_numeric($v) && (float) $v > 0 && (float) $v <= 1000 ? number_format((float) $v, 1, '.', '') : null;
                $cost = trim(str_replace(',', '.', (string) ($l['unit_cost'] ?? '')));
                $unit = Money::normalize($price);
                $out[] = [
                    'kind' => 'custom', 'product_id' => null, 'variant_id' => null, 'sku' => 'SUR-MESURE', 'title' => $title,
                    'product_type' => in_array($l['product_type'] ?? '', CatalogService::TYPES, true) ? $l['product_type'] : 'canvas',
                    'width_cm' => $dim($l['width_cm'] ?? ''), 'height_cm' => $dim($l['height_cm'] ?? ''),
                    'material_name' => ($m = trim((string) ($l['material_name'] ?? ''))) !== '' ? mb_substr($m, 0, 120) : null,
                    'frame_name' => ($f = trim((string) ($l['frame_name'] ?? ''))) !== '' ? mb_substr($f, 0, 120) : null,
                    'panels' => max(1, min(5, (int) ($l['panels'] ?? 1))), 'quantity' => $qty, 'unit_price' => $unit,
                    'unit_cost' => preg_match('/^\d{1,8}(\.\d{1,2})?$/', $cost) ? Money::normalize($cost) : '0.00',
                    'line_total' => Money::mul($unit, $qty), 'track_stock' => false, 'image' => null, 'options' => ['custom_line' => true],
                ];
            }
        }
        if ($out === []) {
            throw new ValidationException(['lines' => __('orders.err_no_lines')]);
        }
        return $out;
    }

    /** Existing customer (chosen, or matched by e-mail/phone) or a new guest customer. @param array<string,mixed> $d */
    private static function resolveCustomer(Database $db, array $d): int
    {
        if (!empty($d['customer_id'])) {
            $id = $db->value('SELECT id FROM customers WHERE id = ? AND deleted_at IS NULL', [(int) $d['customer_id']]);
            if ($id === null) {
                throw new ValidationException(['customer_id' => __('validation.exists', ['attribute' => 'client'])]);
            }
            return (int) $id;
        }
        $email = !empty($d['email']) ? mb_strtolower((string) $d['email']) : null;
        $id = $email !== null ? $db->value('SELECT id FROM customers WHERE email = ? AND deleted_at IS NULL', [$email]) : null;
        $id ??= $db->value('SELECT id FROM customers WHERE phone = ? AND deleted_at IS NULL ORDER BY is_guest, id LIMIT 1', [$d['phone']]);
        if ($id !== null) {
            return (int) $id;
        }
        [$first, $last] = OrderService::splitName((string) $d['full_name']);
        return $db->insert('customers', ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $d['phone'], 'is_guest' => 1, 'locale' => $d['locale'] ?? 'fr']);
    }

    /**
     * Correct contact / delivery details (until the parcel leaves) and internal notes (always).
     *
     * @param array<string,mixed> $d
     */
    public static function updateDetails(int $orderId, array $d, int $userId): void
    {
        $db = Database::instance();
        $o = $db->fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($o === null) {
            throw new WorkflowException(__('orders.err_not_found'));
        }
        $values = ['internal_notes' => ($d['internal_notes'] ?? '') !== '' ? $d['internal_notes'] : null];
        $editable = !in_array($o['status'], [OrderStatus::SHIPPED, OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::RETURNED], true);
        if ($editable) {
            foreach (['customer_name' => 'full_name', 'customer_phone' => 'phone', 'customer_email' => 'email', 'shipping_city' => 'city', 'shipping_address' => 'address', 'shipping_postal_code' => 'postal_code', 'delivery_instructions' => 'delivery_instructions'] as $col => $key) {
                if (array_key_exists($key, $d)) {
                    $values[$col] = $d[$key] !== '' ? $d[$key] : null;
                }
            }
            foreach (['customer_name', 'customer_phone', 'shipping_city', 'shipping_address'] as $required) {
                if (array_key_exists($required, $values) && $values[$required] === null) {
                    unset($values[$required]);
                }
            }
        }
        $db->update('orders', $values, ['id' => $orderId]);
        ActivityLogger::log('order.details_updated', 'order', $orderId, implode(',', array_keys($values)));
    }
}
