<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Core\Database;
use App\Services\ActivityLogger;
use App\Services\Operations\ArtworkService;

/**
 * Data minimisation run by cron. Financial records (orders, invoices, payments) are never removed;
 * only technical or personal data that is no longer needed is deleted, after the delays set in settings.
 */
final class RetentionService
{
    /** @return array<string,int> what was removed */
    public static function run(): array
    {
        $db = Database::instance();
        $days = fn (string $k, int $d) => max(7, (int) setting($k, $d));
        $out = [];
        $out['logs'] = $db->query('DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL ? DAY', [$days('retention_logs_days', 365)])->rowCount();
        $n = $days('retention_notifications_days', 180);
        // read team alerts go; e-mails keep their metadata (who/when/status) but lose their body (personal data)
        $out['alerts'] = $db->query("DELETE FROM notifications WHERE channel = 'internal' AND status = 'read' AND created_at < NOW() - INTERVAL ? DAY", [$n])->rowCount();
        $out['email_bodies'] = $db->query("UPDATE notifications SET body = NULL WHERE channel = 'email' AND body IS NOT NULL AND status <> 'queued' AND created_at < NOW() - INTERVAL ? DAY", [$n])->rowCount();
        $out['carts'] = $db->query("DELETE FROM carts WHERE status IN ('abandoned','converted') AND updated_at < NOW() - INTERVAL ? DAY", [$days('retention_carts_days', 90)])->rowCount();
        // print-ready files of finished orders, after the same delay as ordered photos
        $out['print_files'] = 0;
        foreach ($db->fetchAll(
            "SELECT oc.id, oc.print_file_path FROM order_customizations oc JOIN order_items oi ON oi.id = oc.order_item_id JOIN orders o ON o.id = oi.order_id
             WHERE oc.print_file_path IS NOT NULL AND o.status IN ('delivered','cancelled','returned')
               AND COALESCE(o.delivered_at, o.cancelled_at, o.updated_at) < NOW() - INTERVAL ? DAY LIMIT 200",
            [$days('retention_ordered_days', 365)]
        ) as $f) {
            $path = ArtworkService::printDir() . '/' . $f['print_file_path'];
            if (!str_contains((string) $f['print_file_path'], '..') && is_file($path)) {
                unlink($path);
            }
            $db->update('order_customizations', ['print_file_path' => null], ['id' => (int) $f['id']]);
            $out['print_files']++;
        }
        $out['backups'] = BackupService::rotate((int) setting('backup_keep', 14));
        if (array_sum($out) > 0) {
            ActivityLogger::log('retention.purged', null, null, json_encode($out) ?: '');
        }
        return $out;
    }

    /**
     * Everything stored about a customer, for a data-access request (loi 09-08 / GDPR style), as JSON.
     *
     * @return array<string,mixed>
     */
    public static function exportCustomer(int $customerId): array
    {
        $db = Database::instance();
        $c = $db->fetch('SELECT id, first_name, last_name, email, phone, is_guest, company_name, ice, locale, marketing_opt_in, is_active, notes, email_verified_at, last_login_at, created_at, updated_at, deleted_at FROM customers WHERE id = ?', [$customerId]);
        if ($c === null) {
            throw new \RuntimeException('Customer not found');
        }
        $orders = $db->fetchAll('SELECT id, order_number, status, payment_status, placed_at, customer_name, customer_email, customer_phone, shipping_city, shipping_address, shipping_postal_code, delivery_instructions, subtotal, discount_total, shipping_total, tax_total, grand_total, amount_paid, customer_notes FROM orders WHERE customer_id = ? ORDER BY id', [$customerId]);
        foreach ($orders as &$o) {
            $o['items'] = $db->fetchAll('SELECT title, sku, width_cm, height_cm, material_name, frame_name, panels, quantity, unit_price, line_total FROM order_items WHERE order_id = ?', [(int) $o['id']]);
            unset($o['id']);
        }
        unset($o);
        $out = [
            'generated_at' => date('c'), 'controller' => (string) setting('company_legal_name', '') ?: (string) setting('company_name', ''),
            'customer' => $c,
            'addresses' => $db->fetchAll('SELECT label, full_name, phone, city, address_line1, address_line2, postal_code, is_default, created_at FROM customer_addresses WHERE customer_id = ?', [$customerId]),
            'orders' => $orders,
            'invoices' => $db->fetchAll("SELECT invoice_number, invoice_type, status, issue_date, total_ttc FROM invoices WHERE customer_id = ? AND status <> 'draft'", [$customerId]),
            'quotations' => $db->fetchAll('SELECT quotation_number, status, issue_date, total FROM quotations WHERE customer_id = ?', [$customerId]),
            'payments' => $db->fetchAll("SELECT payment_number, payment_type, amount, paid_at FROM payments WHERE customer_id = ? AND status = 'confirmed'", [$customerId]),
            'designs' => $db->fetchAll('SELECT uuid, width_cm, height_cm, panels, status, created_at FROM custom_designs WHERE customer_id = ?', [$customerId]),
            'photos' => $db->fetchAll('SELECT uuid, original_name, width_px, height_px, status, retention_until, created_at FROM uploaded_images WHERE customer_id = ?', [$customerId]),
            'newsletter' => $c['email'] ? $db->fetch('SELECT status, subscribed_at, unsubscribed_at FROM newsletter_subscribers WHERE email = ?', [$c['email']]) : null,
            'reviews' => $db->fetchAll('SELECT rating, title, body, is_approved, created_at FROM reviews WHERE customer_id = ?', [$customerId]),
        ];
        ActivityLogger::log('customer.exported', 'customer', $customerId);
        return $out;
    }
}
