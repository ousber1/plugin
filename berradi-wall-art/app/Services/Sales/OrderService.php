<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Core\Translator;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\Payments\PaymentRegistry;
use App\Services\SequenceService;

/**
 * Turns a cart into an order, atomically.
 * Nothing from the browser is trusted: lines, prices, coupon, shipping and taxes are recomputed here,
 * stock is locked (SELECT … FOR UPDATE) and the order is created in a single transaction.
 * No payment is ever recorded at checkout: offline methods are paid later and confirmed by staff,
 * online methods only through a verified provider callback.
 */
final class OrderService
{
    /**
     * @param array{full_name:string,phone:string,email:?string,country:string,city:string,address:string,postal_code:?string,
     *              delivery_instructions:?string,payment_method:string,create_account?:bool,password?:?string,save_address?:bool,customer_notes?:?string} $d
     * @return array{order:array<string,mixed>,token:string,account_created:bool}
     * @throws ValidationException
     */
    public static function place(array $d, ?string $ip = null): array
    {
        $cart = CartService::current(false);
        if ($cart === null) {
            throw new ValidationException(['cart' => __('cart.empty')]);
        }
        $lines = CartService::lines($cart);
        if ($lines === []) {
            throw new ValidationException(['cart' => __('cart.empty')]);
        }
        foreach ($lines as $l) {
            if (!$l['available']) {
                throw new ValidationException(['cart' => __('checkout.cart_changed')]);
            }
        }
        $email = $d['email'] !== null && $d['email'] !== '' ? mb_strtolower(trim($d['email'])) : null;
        $totals = CheckoutCalculator::totals($cart, $lines, $d['city'], $email);
        if ($totals['coupon_error'] !== null) {
            CartService::removeCoupon();
            throw new ValidationException(['coupon' => $totals['coupon_error']]);
        }
        $method = PaymentRegistry::find($d['payment_method']);
        if ($method === null) {
            throw new ValidationException(['payment_method' => __('checkout.payment_invalid')]);
        }

        $customer = Auth::customer();
        $token = bin2hex(random_bytes(24));
        $accountCreated = false;
        $db = Database::instance();

        $order = $db->transaction(function (Database $db) use ($d, $cart, $lines, $totals, $method, $customer, $email, $token, $ip, &$accountCreated): array {
            // ---- stock (locked)
            foreach ($lines as $l) {
                if ($l['kind'] !== 'product' || !$l['snapshot']['track_stock']) {
                    continue;
                }
                $stock = (int) $db->value('SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE', [$l['product_id']]);
                if ($l['variant_id'] !== null) {
                    $stock = min($stock, (int) $db->value('SELECT stock_quantity FROM product_variants WHERE id = ? FOR UPDATE', [$l['variant_id']]));
                }
                if ($stock < $l['quantity']) {
                    throw new ValidationException(['cart' => __('cart.stock_left', ['count' => max(0, $stock)]) . ' — ' . $l['title']]);
                }
                $db->query('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?', [$l['quantity'], $l['product_id']]);
                if ($l['variant_id'] !== null) {
                    $db->query('UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ?', [$l['quantity'], $l['variant_id']]);
                }
            }

            // ---- customer
            $customerId = $customer !== null ? (int) $customer['id'] : null;
            if ($customerId === null && $email !== null) {
                $existing = $db->fetch('SELECT id, is_guest FROM customers WHERE email = ? AND deleted_at IS NULL', [$email]);
                [$first, $last] = self::splitName($d['full_name']);
                if (!empty($d['create_account']) && !empty($d['password'])) {
                    if ($existing !== null && (int) $existing['is_guest'] === 0) {
                        throw new ValidationException(['email' => __('auth.email_taken')]);
                    }
                    $values = ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $d['phone'],
                        'password_hash' => Auth::hashPassword((string) $d['password']), 'is_guest' => 0, 'locale' => Translator::locale()];
                    if ($existing !== null) {
                        $db->update('customers', $values, ['id' => (int) $existing['id']]);
                        $customerId = (int) $existing['id'];
                    } else {
                        $customerId = $db->insert('customers', $values);
                    }
                    $accountCreated = true;
                } elseif ($existing === null) {
                    $customerId = $db->insert('customers', ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $d['phone'], 'is_guest' => 1, 'locale' => Translator::locale()]);
                } elseif ((int) $existing['is_guest'] === 1) {
                    $customerId = (int) $existing['id'];
                }
                // e-mail of a registered account while not logged in: the order stays a guest order (no access to that account)
            } elseif ($customerId === null && !empty($d['create_account'])) {
                throw new ValidationException(['email' => __('checkout.email_for_account')]);
            }

            // ---- order
            $shipping = $totals['shipping'];
            $number = SequenceService::next('order');
            $costTotal = 0;
            foreach ($lines as $l) {
                $costTotal += Money::toCents((string) $l['unit_cost']) * (int) $l['quantity'];
            }
            $orderId = $db->insert('orders', [
                'order_number' => $number,
                'customer_id' => $customerId,
                'status' => OrderStatus::NEW,
                'payment_status' => 'unpaid',
                'payment_method_id' => (int) $method['id'],
                'source' => 'storefront',
                'locale' => Translator::locale(),
                'currency' => (string) setting('currency_code', 'MAD'),
                'customer_name' => $d['full_name'],
                'customer_email' => $email,
                'customer_phone' => $d['phone'],
                'shipping_country' => $d['country'],
                'shipping_city' => $d['city'],
                'shipping_address' => $d['address'],
                'shipping_postal_code' => $d['postal_code'],
                'delivery_instructions' => $d['delivery_instructions'],
                'shipping_zone_id' => $shipping['zone_id'] ?? null,
                'shipping_rate_id' => $shipping['rate_id'] ?? null,
                'estimated_delivery_min' => $shipping['date_min'] ?? null,
                'estimated_delivery_max' => $shipping['date_max'] ?? null,
                'coupon_id' => $totals['coupon']['id'] ?? null,
                'coupon_code' => $totals['coupon']['code'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'shipping_total' => $totals['shipping_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'amount_paid' => '0.00',
                'balance_due' => $totals['grand_total'],
                'cost_total' => Money::fromCents($costTotal),
                'customer_notes' => $d['customer_notes'] ?? null,
                'access_token' => hash('sha256', $token),
                'ip' => $ip,
            ]);

            // ---- items & customizations (snapshots: later catalogue changes never alter an order)
            $retention = date('Y-m-d H:i:s', time() + max(30, (int) setting('retention_ordered_days', 365)) * 86400);
            foreach ($lines as $l) {
                if ($l['kind'] === 'design') {
                    $c = $l['snapshot']['config'];
                    $q = $l['snapshot']['quote'];
                    $design = $l['snapshot']['design'];
                    $type = match ($q['material']['material_type']) {
                        'poster' => 'poster',
                        'photo_paper' => $q['frame'] ? 'framed' : 'photo_print',
                        default => (int) $q['panels'] > 1 ? 'multi_panel' : 'canvas',
                    };
                    $itemId = $db->insert('order_items', [
                        'order_id' => $orderId, 'product_id' => $l['product_id'], 'custom_design_id' => $l['design_id'],
                        'sku' => 'CUSTOM-' . strtoupper(substr((string) $l['design_uuid'], 0, 8)),
                        'title' => mb_substr((string) $l['title'], 0, 191), 'product_type' => $type,
                        'width_cm' => $q['overall_width_cm'], 'height_cm' => $q['overall_height_cm'],
                        'material_name' => $q['material']['name'], 'frame_name' => $q['frame']['name'] ?? null, 'panels' => (int) $q['panels'],
                        'quantity' => (int) $l['quantity'], 'unit_price' => $l['unit_price'], 'unit_cost' => $l['unit_cost'],
                        'tax_rate' => $totals['tax_rate'], 'line_total' => $l['line_total'],
                        'options' => json_encode($c + ['quote_breakdown' => $q['breakdown']], JSON_UNESCAPED_UNICODE),
                        'preview_path' => $design['preview_path'],
                    ]);
                    foreach ($db->fetchAll('SELECT * FROM custom_design_images WHERE custom_design_id = ? ORDER BY panel_index', [$l['design_id']]) as $img) {
                        $db->insert('order_customizations', [
                            'order_item_id' => $itemId, 'uploaded_image_id' => (int) $img['uploaded_image_id'], 'panel_index' => (int) $img['panel_index'],
                            'crop' => $img['crop'], 'orientation' => $design['orientation'], 'effective_dpi' => $img['effective_dpi'],
                            'final_preview_path' => $design['preview_path'], 'approval_status' => 'pending',
                        ]);
                        $db->query("UPDATE uploaded_images SET status = 'ordered', retention_until = ?, customer_id = COALESCE(customer_id, ?) WHERE id = ?", [$retention, $customerId, (int) $img['uploaded_image_id']]);
                    }
                    $db->update('custom_designs', ['status' => 'ordered', 'customer_id' => $design['customer_id'] ?? $customerId], ['id' => $l['design_id']]);
                } else {
                    $s = $l['snapshot'];
                    $db->insert('order_items', [
                        'order_id' => $orderId, 'product_id' => $l['product_id'], 'product_variant_id' => $l['variant_id'],
                        'sku' => $s['sku'], 'title' => mb_substr((string) $s['title'], 0, 191), 'product_type' => $s['product_type'],
                        'width_cm' => $s['width_cm'], 'height_cm' => $s['height_cm'], 'material_name' => $s['material_name'], 'frame_name' => $s['frame_name'],
                        'panels' => (int) $s['panels'], 'quantity' => (int) $l['quantity'], 'unit_price' => $l['unit_price'], 'unit_cost' => $l['unit_cost'],
                        'tax_rate' => $totals['tax_rate'], 'line_total' => $l['line_total'],
                        'options' => json_encode(['variant' => $s['variant_name'], 'slug' => $s['slug']], JSON_UNESCAPED_UNICODE),
                        'preview_path' => $s['image'],
                    ]);
                }
            }

            if ($totals['coupon'] !== null && !CouponService::redeem($db, (int) $totals['coupon']['id'], $orderId, $customerId, $totals['discount'])) {
                throw new ValidationException(['coupon' => __('cart.coupon_exhausted')]);
            }
            $db->insert('order_status_history', [
                'order_id' => $orderId, 'from_status' => null, 'to_status' => OrderStatus::NEW,
                'comment' => 'Commande passée en ligne — ' . $method['name'],
            ]);

            // cart converted; a new cart token will be issued next time
            $db->query("UPDATE carts SET status = 'converted', customer_id = COALESCE(customer_id, ?), email = ?, phone = ?, token = ? WHERE id = ?",
                [$customerId, $email, $d['phone'], hash('sha256', 'converted|' . $cart['id'] . '|' . bin2hex(random_bytes(8))), (int) $cart['id']]);

            if (!empty($d['save_address']) && $customerId !== null && ($customer !== null || $accountCreated)) {
                $has = (int) $db->value('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$customerId]);
                $db->insert('customer_addresses', [
                    'customer_id' => $customerId, 'label' => null, 'full_name' => $d['full_name'], 'phone' => $d['phone'], 'country' => $d['country'],
                    'city' => $d['city'], 'address_line1' => $d['address'], 'postal_code' => $d['postal_code'], 'is_default' => $has === 0 ? 1 : 0,
                ]);
            }
            return (array) $db->fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        });

        CartService::setToken(null);
        if ($accountCreated && $order['customer_id'] !== null) {
            Auth::loginCustomer((int) $order['customer_id']);
        }
        ActivityLogger::log('order.placed', 'order', (int) $order['id'], (string) $order['order_number'], ['total' => $order['grand_total'], 'payment' => $method['code']]);
        OrderMailer::placed($order, $token);
        return ['order' => $order, 'token' => $token, 'account_created' => $accountCreated];
    }

    /** @return array{0:string,1:string} */
    public static function splitName(string $full): array
    {
        $parts = preg_split('/\s+/u', trim($full), 2) ?: [''];
        return [mb_substr($parts[0], 0, 100), mb_substr($parts[1] ?? '', 0, 100)];
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $orderId): array
    {
        return Database::instance()->fetchAll(
            'SELECT oi.*, cd.uuid AS design_uuid FROM order_items oi LEFT JOIN custom_designs cd ON cd.id = oi.custom_design_id WHERE oi.order_id = ? ORDER BY oi.id',
            [$orderId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function history(int $orderId): array
    {
        return Database::instance()->fetchAll('SELECT to_status, comment, created_at FROM order_status_history WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    /** @param array<string,mixed> $order */
    public static function isCancellable(array $order): bool
    {
        return in_array($order['status'], OrderStatus::cancellable(), true) && $order['payment_status'] === 'unpaid';
    }

    /**
     * Customer cancellation: restock, release coupon, free the designs, log the change.
     *
     * @param array<string,mixed> $order
     */
    public static function cancelByCustomer(array $order, string $reason = ''): bool
    {
        if (!self::isCancellable($order)) {
            return false;
        }
        $db = Database::instance();
        $done = $db->transaction(function (Database $db) use ($order, $reason): bool {
            $fresh = $db->fetch('SELECT status, payment_status FROM orders WHERE id = ? FOR UPDATE', [(int) $order['id']]);
            if ($fresh === null || !self::isCancellable($fresh + $order)) {
                return false;
            }
            $db->update('orders', ['status' => OrderStatus::CANCELLED, 'cancelled_at' => date('Y-m-d H:i:s'), 'balance_due' => '0.00'], ['id' => (int) $order['id']]);
            self::releaseForCancellation($db, (int) $order['id']);
            $db->insert('order_status_history', [
                'order_id' => (int) $order['id'], 'from_status' => $order['status'], 'to_status' => OrderStatus::CANCELLED,
                'comment' => 'Annulée par le client' . ($reason !== '' ? ' : ' . mb_substr($reason, 0, 500) : ''),
            ]);
            return true;
        });
        if ($done) {
            ActivityLogger::log('order.cancelled_by_customer', 'order', (int) $order['id'], (string) $order['order_number']);
            OrderMailer::cancelled(array_merge($order, ['status' => OrderStatus::CANCELLED]));
        }
        return $done;
    }

    /**
     * Give back what an order holds when it is cancelled: catalogue stock, coupon redemption,
     * customer designs/photos (back to normal retention). Must run inside the caller's transaction.
     */
    public static function releaseForCancellation(Database $db, int $orderId): void
    {
        foreach (self::items($orderId) as $it) {
            if ($it['product_id'] !== null && $it['custom_design_id'] === null) {
                $tracked = (int) $db->value('SELECT track_stock FROM products WHERE id = ?', [(int) $it['product_id']]) === 1;
                if ($tracked) {
                    $db->query('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?', [(int) $it['quantity'], (int) $it['product_id']]);
                    if ($it['product_variant_id'] !== null) {
                        $db->query('UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?', [(int) $it['quantity'], (int) $it['product_variant_id']]);
                    }
                }
            }
            if ($it['custom_design_id'] !== null) {
                $db->query("UPDATE custom_designs SET status = 'draft' WHERE id = ?", [(int) $it['custom_design_id']]);
                $db->query(
                    "UPDATE uploaded_images u JOIN order_customizations oc ON oc.uploaded_image_id = u.id
                     SET u.status = 'attached', u.retention_until = ? WHERE oc.order_item_id = ? AND u.status = 'ordered'",
                    [date('Y-m-d H:i:s', time() + (int) setting('retention_unordered_days', 30) * 86400), (int) $it['id']]
                );
            }
        }
        CouponService::release($db, $orderId);
    }

    /**
     * Public tracking lookup: order number + (e-mail or phone), or order number + secret token from the e-mail link.
     *
     * @return array<string,mixed>|null
     */
    public static function findForTracking(string $number, ?string $contact = null, ?string $token = null): ?array
    {
        $number = strtoupper(trim($number));
        if (!preg_match('/^[A-Z0-9]{1,10}-\d{4}-\d{4,8}$/', $number)) {
            return null;
        }
        $o = Database::instance()->fetch('SELECT * FROM orders WHERE order_number = ?', [$number]);
        if ($o === null) {
            return null;
        }
        if ($token !== null && $token !== '') {
            return preg_match('/^[a-f0-9]{48}$/', $token) && hash_equals((string) $o['access_token'], hash('sha256', $token)) ? $o : null;
        }
        $contact = trim((string) $contact);
        if ($contact === '') {
            return null;
        }
        if (str_contains($contact, '@')) {
            return $o['customer_email'] !== null && mb_strtolower($contact) === mb_strtolower((string) $o['customer_email']) ? $o : null;
        }
        $digits = fn (string $p) => substr((string) preg_replace('/\D+/', '', $p), -9);
        return strlen($digits($contact)) >= 9 && $digits($contact) === $digits((string) $o['customer_phone']) ? $o : null;
    }
}
