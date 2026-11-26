<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Sales\OrderMailer;
use App\Services\Sales\OrderStatus;
use App\Services\SequenceService;

/**
 * Quotations: draft → sent (customer link) → accepted / rejected / expired → converted into an order.
 * Editing a sent quotation puts it back to draft: the customer must receive the new version.
 */
final class QuotationService
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted', 'cancelled'];
    public const EDITABLE = ['draft', 'sent'];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM quotations WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $id): array
    {
        return Database::instance()->fetchAll('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id', [$id]);
    }

    /**
     * @param array<string,mixed> $head customer_*, issue_date?, valid_until?, terms, notes, locale
     * @param list<array<string,mixed>> $raw lines priced HT
     */
    public static function save(?int $id, array $head, array $raw, ?int $userId): int
    {
        $calc = DocumentLines::compute($raw);
        $s = fn (string $k, int $max) => ($v = trim((string) ($head[$k] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;
        $name = $s('customer_name', 150);
        if ($name === null) {
            throw new ValidationException(['customer_name' => __('validation.required', ['attribute' => __('finance.customer')])]);
        }
        $issue = !empty($head['issue_date']) ? date('Y-m-d', (int) strtotime((string) $head['issue_date'])) : date('Y-m-d');
        $valid = !empty($head['valid_until']) ? date('Y-m-d', (int) strtotime((string) $head['valid_until']))
            : date('Y-m-d', strtotime($issue . ' +' . max(1, (int) setting('quotation_validity_days', 15)) . ' days'));
        if ($valid < $issue) {
            throw new ValidationException(['valid_until' => __('finance.err_validity')]);
        }
        $values = [
            'customer_id' => !empty($head['customer_id']) ? (int) $head['customer_id'] : null,
            'customer_name' => $name, 'customer_company' => $s('customer_company', 150), 'customer_ice' => $s('customer_ice', 30),
            'customer_email' => ($e = $s('customer_email', 191)) !== null ? mb_strtolower($e) : null, 'customer_phone' => $s('customer_phone', 30),
            'customer_address' => $s('customer_address', 500), 'issue_date' => $issue, 'valid_until' => $valid,
            'locale' => in_array($head['locale'] ?? 'fr', ['fr', 'ar'], true) ? ($head['locale'] ?? 'fr') : 'fr',
            'subtotal' => $calc['subtotal'], 'discount_total' => $calc['discount'], 'tax_total' => $calc['tax'], 'total' => $calc['total'],
            'terms' => $s('terms', 5000), 'notes' => $s('notes', 5000),
        ];
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($id, $values, $calc, $userId): int {
            if ($id === null) {
                $id = $db->insert('quotations', $values + ['quotation_number' => SequenceService::next('quotation'), 'status' => 'draft',
                    'currency' => (string) setting('currency_code', 'MAD'), 'created_by' => $userId]);
            } else {
                $q = $db->fetch('SELECT status FROM quotations WHERE id = ? FOR UPDATE', [$id]);
                if ($q === null || !in_array($q['status'], self::EDITABLE, true)) {
                    throw new ValidationException(['lines' => __('finance.err_quote_locked')]);
                }
                // a modified quotation must be sent again; the old link stops working
                $db->update('quotations', $values + ['status' => 'draft', 'access_token' => null], ['id' => $id]);
                $db->delete('quotation_items', ['quotation_id' => $id]);
            }
            foreach ($calc['lines'] as $i => $l) {
                $db->insert('quotation_items', [
                    'quotation_id' => $id, 'product_id' => $l['product_id'], 'description' => $l['description'],
                    'width_cm' => null, 'height_cm' => null, 'material_name' => $l['specifications'], 'quantity' => $l['quantity'],
                    'unit_price' => $l['unit_price'], 'discount_amount' => $l['discount'], 'tax_rate' => $l['tax_rate'],
                    'line_total_ht' => $l['ht'], 'line_tax' => $l['tax'], 'line_total_ttc' => $l['ttc'], 'sort_order' => $i,
                ]);
            }
            ActivityLogger::log('quotation.saved', 'quotation', $id);
            return $id;
        });
    }

    /** E-mail (or hand over) a link to view, download and answer the quotation. Returns the link. */
    public static function send(int $id, ?int $userId, string $message = ''): string
    {
        $q = self::find($id);
        if ($q === null || !in_array($q['status'], ['draft', 'sent'], true)) {
            throw new ValidationException(['quotation' => __('finance.err_quote_locked')]);
        }
        if ($q['valid_until'] < date('Y-m-d')) {
            throw new ValidationException(['quotation' => __('finance.err_quote_expired')]);
        }
        $raw = bin2hex(random_bytes(32));
        Database::instance()->update('quotations', ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'access_token' => hash('sha256', $raw)], ['id' => $id]);
        $url = absolute_url('quote/' . $raw);
        if (!empty($q['customer_email'])) {
            OrderMailer::document($q + ['order_number' => $q['quotation_number'], 'id' => $id], 'quotation', 'quotation_sent', ['url' => $url, 'message' => $message, 'quote' => $q]);
        }
        ActivityLogger::log('quotation.sent', 'quotation', $id, (string) $q['quotation_number']);
        return $url;
    }

    /** @return array<string,mixed>|null */
    public static function findByToken(string $raw): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return null;
        }
        return Database::instance()->fetch('SELECT * FROM quotations WHERE access_token = ?', [hash('sha256', $raw)]);
    }

    /** Customer accepts or declines from the link (only while sent and valid). */
    public static function respond(string $raw, bool $accept, ?string $ip, string $comment = ''): bool
    {
        $q = self::findByToken($raw);
        if ($q === null || $q['status'] !== 'sent' || $q['valid_until'] < date('Y-m-d')) {
            return false;
        }
        $values = $accept ? ['status' => 'accepted', 'accepted_at' => date('Y-m-d H:i:s')] : ['status' => 'rejected', 'rejected_at' => date('Y-m-d H:i:s')];
        $values['response_ip'] = $ip;
        if (trim($comment) !== '') {
            $values['notes'] = trim(($q['notes'] ? $q['notes'] . "\n" : '') . __('finance.customer_comment') . ' : ' . mb_substr(trim($comment), 0, 1000));
        }
        Database::instance()->update('quotations', $values, ['id' => (int) $q['id']]);
        ActivityLogger::log($accept ? 'quotation.accepted' : 'quotation.rejected', 'quotation', (int) $q['id'], 'customer');
        OrderMailer::internal($q + ['order_number' => $q['quotation_number']], $accept ? 'quotation_accepted' : 'quotation_rejected',
            __($accept ? 'finance.mail_quote_accepted' : 'finance.mail_quote_rejected', ['number' => $q['quotation_number'], 'name' => $q['customer_name']])
            . (trim($comment) !== '' ? "\n\n« " . trim($comment) . ' »' : ''));
        return true;
    }

    /** Staff records the customer's answer (phone, WhatsApp, signed paper). */
    public static function setStatus(int $id, string $status, ?int $userId): void
    {
        $q = self::find($id);
        $allowed = [
            'accepted' => ['sent', 'draft', 'expired'], 'rejected' => ['sent', 'draft', 'expired'], 'cancelled' => ['draft', 'sent', 'accepted', 'expired', 'rejected'],
        ];
        if ($q === null || !isset($allowed[$status]) || !in_array($q['status'], $allowed[$status], true)) {
            throw new ValidationException(['quotation' => __('finance.err_quote_status')]);
        }
        $values = ['status' => $status, 'access_token' => $status === 'cancelled' ? null : $q['access_token']];
        if ($status === 'accepted') {
            $values['accepted_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'rejected') {
            $values['rejected_at'] = date('Y-m-d H:i:s');
        }
        Database::instance()->update('quotations', $values, ['id' => $id]);
        ActivityLogger::log('quotation.' . $status, 'quotation', $id, 'staff');
    }

    /** Cron: sent quotations past their validity date become expired. */
    public static function expireDue(): int
    {
        return Database::instance()->query("UPDATE quotations SET status = 'expired' WHERE status = 'sent' AND valid_until < CURDATE()")->rowCount();
    }

    /** Copy as a new draft (new number, today's dates). */
    public static function duplicate(int $id, ?int $userId): int
    {
        $q = self::find($id);
        if ($q === null) {
            throw new ValidationException(['quotation' => __('finance.err_quote_status')]);
        }
        $raw = array_map(fn ($l) => [
            'product_id' => $l['product_id'], 'description' => $l['description'], 'specifications' => $l['material_name'], 'quantity' => $l['quantity'],
            'unit_price' => $l['unit_price'], 'discount' => $l['discount_amount'], 'tax_rate' => $l['tax_rate'],
        ], self::items($id));
        $head = $q;
        unset($head['issue_date'], $head['valid_until']);
        return self::save(null, $head, $raw, $userId);
    }

    /**
     * Turn an accepted (or sent) quotation into a confirmed order with the same amounts.
     *
     * @param array{city:string,address:string,phone:string,payment_method_id?:?int} $delivery
     */
    public static function convert(int $id, array $delivery, ?int $userId): array
    {
        $db = Database::instance();
        $order = $db->transaction(function (Database $db) use ($id, $delivery, $userId): array {
            $q = $db->fetch('SELECT * FROM quotations WHERE id = ? FOR UPDATE', [$id]);
            if ($q === null || !in_array($q['status'], ['accepted', 'sent'], true)) {
                throw new ValidationException(['quotation' => __('finance.err_quote_convert')]);
            }
            $items = self::items($id);
            foreach ($items as $i => $it) {
                if (fmod((float) $it['quantity'], 1.0) !== 0.0) {
                    throw new ValidationException(['quotation' => __('finance.err_quote_qty', ['n' => $i + 1])]);
                }
            }
            $token = bin2hex(random_bytes(24));
            $rate = (string) ($items[0]['tax_rate'] ?? '0');
            $orderId = $db->insert('orders', [
                'order_number' => SequenceService::next('order'), 'customer_id' => $q['customer_id'], 'status' => OrderStatus::CONFIRMED,
                'payment_status' => 'unpaid', 'payment_method_id' => $delivery['payment_method_id'] ?? null, 'source' => 'quotation', 'quotation_id' => $id,
                'locale' => $q['locale'], 'currency' => $q['currency'], 'customer_name' => $q['customer_name'], 'customer_email' => $q['customer_email'],
                'customer_phone' => $delivery['phone'], 'shipping_country' => 'MA', 'shipping_city' => $delivery['city'], 'shipping_address' => $delivery['address'],
                'subtotal' => $q['total'], 'discount_total' => '0.00', 'shipping_total' => '0.00', 'tax_total' => $q['tax_total'], 'grand_total' => $q['total'],
                'amount_paid' => '0.00', 'balance_due' => $q['total'], 'cost_total' => '0.00', 'access_token' => hash('sha256', $token),
                'confirmed_at' => date('Y-m-d H:i:s'), 'created_by' => $userId,
            ]);
            foreach ($items as $it) {
                $qty = (int) $it['quantity'];
                $db->insert('order_items', [
                    'order_id' => $orderId, 'product_id' => $it['product_id'], 'sku' => 'DEVIS', 'title' => mb_substr((string) $it['description'], 0, 191),
                    'product_type' => 'canvas', 'material_name' => $it['material_name'] !== null ? mb_substr((string) $it['material_name'], 0, 120) : null,
                    'quantity' => $qty, 'unit_price' => Money::fromCents(intdiv(Money::toCents((string) $it['line_total_ttc']) + intdiv($qty, 2), max(1, $qty))),
                    'unit_cost' => '0.00', 'tax_rate' => $it['tax_rate'] ?: $rate, 'line_total' => $it['line_total_ttc'],
                    'options' => json_encode(['quotation' => $q['quotation_number']], JSON_UNESCAPED_UNICODE),
                ]);
            }
            $db->insert('order_status_history', ['order_id' => $orderId, 'to_status' => OrderStatus::CONFIRMED,
                'comment' => __('finance.converted_from', ['number' => $q['quotation_number']]), 'changed_by' => $userId]);
            $db->update('quotations', ['status' => 'converted', 'converted_order_id' => $orderId], ['id' => $id]);
            return $db->fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        });
        ActivityLogger::log('quotation.converted', 'quotation', $id, (string) $order['order_number']);
        return $order;
    }
}
