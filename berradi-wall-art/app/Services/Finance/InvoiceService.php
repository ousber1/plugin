<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\SequenceService;

/**
 * Invoices and credit notes.
 *
 * - Drafts carry no number and can be edited or deleted.
 * - Issuing assigns the next number of the year (no gaps: the sequence is taken inside the issuing transaction),
 *   freezes the company identity and stores a SHA-256 integrity hash of the content.
 * - An issued document is never edited or deleted: it is corrected by a credit note (full or partial).
 */
final class InvoiceService
{
    public const ACTIVE = ['issued', 'partially_paid', 'paid'];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $id): array
    {
        return Database::instance()->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
    }

    /** Sum of issued credit notes against an invoice. */
    public static function credited(int $invoiceId): string
    {
        return Money::normalize((string) Database::instance()->value(
            "SELECT COALESCE(SUM(total_ttc), 0) FROM invoices WHERE original_invoice_id = ? AND invoice_type = 'credit_note' AND status <> 'draft'",
            [$invoiceId]
        ));
    }

    /** Invoice of an order still in force (not fully credited), if any. @return array<string,mixed>|null */
    public static function activeForOrder(int $orderId): ?array
    {
        foreach (Database::instance()->fetchAll("SELECT * FROM invoices WHERE order_id = ? AND invoice_type = 'invoice' AND status <> 'cancelled' ORDER BY id", [$orderId]) as $inv) {
            if ($inv['status'] === 'draft' || Money::compare(self::credited((int) $inv['id']), (string) $inv['total_ttc']) < 0) {
                return $inv;
            }
        }
        return null;
    }

    /**
     * Draft invoice from an order: HT lines derived from the order snapshot so that the invoice total equals
     * exactly what the customer owes (TTC prices: HT = TTC / (1 + rate); HT prices: VAT added).
     */
    public static function fromOrder(int $orderId, ?int $userId): int
    {
        $db = Database::instance();
        $o = $db->fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($o === null) {
            throw new ValidationException(['invoice' => __('finance.err_order')]);
        }
        if ($o['status'] === 'cancelled') {
            throw new ValidationException(['invoice' => __('finance.err_order_cancelled')]);
        }
        if (self::activeForOrder($orderId) !== null) {
            throw new ValidationException(['invoice' => __('finance.err_already_invoiced')]);
        }
        $items = $db->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
        $tax = (string) $o['tax_total'];
        $rate = Money::toCents($tax) > 0 ? (string) ($items[0]['tax_rate'] ?? setting('tax_default_rate', '20')) : '0';
        $base = Money::add(Money::sub((string) $o['subtotal'], (string) $o['discount_total']), (string) $o['shipping_total']);
        $included = Money::toCents($tax) === 0 || Money::compare($base, (string) $o['grand_total']) === 0;
        $toHt = fn (string $v) => $included ? Money::htFromTtc($v, $rate) : Money::normalize($v);
        $lines = [];
        $goods = '0.00';
        foreach ($items as $i => $it) {
            $ht = $toHt((string) $it['line_total']);
            $goods = Money::add($goods, $ht);
            $lt = $included ? Money::sub((string) $it['line_total'], $ht) : Money::percent($ht, $rate);
            $spec = array_filter([
                $it['width_cm'] ? cm($it['width_cm']) . ' × ' . cm($it['height_cm']) . ' cm' : null,
                $it['material_name'], $it['frame_name'], (int) $it['panels'] > 1 ? $it['panels'] . ' panneaux' : null, $it['sku'],
                // personalised gift / laser line: what was written on it
                \App\Services\Catalog\Personalization::summary((json_decode((string) ($it['options'] ?? ''), true) ?: [])['personalization'] ?? [], 200) ?: null,
            ]);
            $lines[] = [
                'order_item_id' => (int) $it['id'], 'description' => mb_substr((string) $it['title'], 0, 500), 'specifications' => $spec ? implode(' · ', $spec) : null,
                'quantity' => number_format((int) $it['quantity'], 2, '.', ''),
                'unit_price_ht' => Money::fromCents(intdiv(Money::toCents($ht) + intdiv((int) $it['quantity'], 2), max(1, (int) $it['quantity']))),
                'discount_amount' => '0.00', 'tax_rate' => number_format((float) $rate, 4, '.', ''), 'total_ht' => $ht, 'total_tax' => $lt,
                'total_ttc' => Money::add($ht, $lt), 'sort_order' => $i,
            ];
        }
        $discountHt = $toHt((string) $o['discount_total']);
        $shippingHt = $toHt((string) $o['shipping_total']);
        $totalHt = Money::add(Money::sub($goods, $discountHt), $shippingHt);
        $total = (string) $o['grand_total'];
        $customer = $o['customer_id'] ? $db->fetch('SELECT company_name, ice FROM customers WHERE id = ?', [(int) $o['customer_id']]) : null;
        $id = $db->transaction(function (Database $db) use ($o, $lines, $goods, $discountHt, $shippingHt, $totalHt, $total, $customer, $userId): int {
            $id = $db->insert('invoices', [
                'invoice_type' => 'invoice', 'order_id' => (int) $o['id'], 'customer_id' => $o['customer_id'], 'status' => 'draft',
                'locale' => $o['locale'], 'currency' => $o['currency'],
                'customer_name' => $o['customer_name'], 'customer_company' => $customer['company_name'] ?? null, 'customer_ice' => $customer['ice'] ?? null,
                'customer_email' => $o['customer_email'], 'customer_phone' => $o['customer_phone'],
                'customer_address' => mb_substr(trim($o['shipping_address'] . ', ' . ($o['shipping_postal_code'] ? $o['shipping_postal_code'] . ' ' : '') . $o['shipping_city']), 0, 500),
                'subtotal_ht' => $goods, 'discount_total' => $discountHt, 'shipping_ht' => $shippingHt,
                'tax_total' => Money::sub($total, $totalHt), 'total_ttc' => $total,
                'payment_terms' => (string) setting('invoice_payment_terms', ''), 'created_by' => $userId,
            ]);
            foreach ($lines as $l) {
                $db->insert('invoice_items', $l + ['invoice_id' => $id]);
            }
            return $id;
        });
        ActivityLogger::log('invoice.draft_from_order', 'invoice', $id, (string) $o['order_number']);
        return $id;
    }

    /**
     * Draft typed by staff (B2B, services without an online order).
     *
     * @param array<string,mixed> $head customer_* fields, notes, payment_terms, locale
     * @param list<array<string,mixed>> $raw lines priced HT
     */
    public static function saveManual(?int $id, array $head, array $raw, ?int $userId): int
    {
        $calc = DocumentLines::compute($raw);
        $db = Database::instance();
        if ($id !== null) {
            $inv = self::find($id);
            if ($inv === null || $inv['status'] !== 'draft') {
                throw new ValidationException(['lines' => __('finance.err_locked')]);
            }
            if ($inv['order_id'] !== null) {
                throw new ValidationException(['lines' => __('finance.err_order_lines')]);
            }
        }
        $values = self::headValues($head) + [
            'subtotal_ht' => $calc['ht'], 'discount_total' => '0.00', 'shipping_ht' => '0.00', 'tax_total' => $calc['tax'], 'total_ttc' => $calc['total'],
        ];
        return $db->transaction(function (Database $db) use ($id, $values, $calc, $userId): int {
            if ($id === null) {
                $id = $db->insert('invoices', $values + ['invoice_type' => 'invoice', 'status' => 'draft', 'currency' => (string) setting('currency_code', 'MAD'), 'created_by' => $userId]);
            } else {
                $db->update('invoices', $values, ['id' => $id]);
                $db->delete('invoice_items', ['invoice_id' => $id]);
            }
            foreach ($calc['lines'] as $i => $l) {
                $db->insert('invoice_items', [
                    'invoice_id' => $id, 'description' => $l['description'], 'specifications' => $l['specifications'], 'quantity' => $l['quantity'],
                    'unit_price_ht' => $l['unit_price'], 'discount_amount' => $l['discount'], 'tax_rate' => $l['tax_rate'],
                    'total_ht' => $l['ht'], 'total_tax' => $l['tax'], 'total_ttc' => $l['ttc'], 'sort_order' => $i,
                ]);
            }
            ActivityLogger::log('invoice.draft_saved', 'invoice', $id);
            return $id;
        });
    }

    /** Header fields editable on any draft (customer identity, terms). @param array<string,mixed> $head @return array<string,mixed> */
    private static function headValues(array $head): array
    {
        $s = fn (string $k, int $max) => ($v = trim((string) ($head[$k] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;
        $name = $s('customer_name', 150);
        if ($name === null) {
            throw new ValidationException(['customer_name' => __('validation.required', ['attribute' => __('finance.customer')])]);
        }
        return [
            'customer_id' => !empty($head['customer_id']) ? (int) $head['customer_id'] : null,
            'customer_name' => $name, 'customer_company' => $s('customer_company', 150), 'customer_ice' => $s('customer_ice', 30),
            'customer_email' => ($e = $s('customer_email', 191)) !== null ? mb_strtolower($e) : null, 'customer_phone' => $s('customer_phone', 30),
            'customer_address' => $s('customer_address', 500), 'payment_terms' => $s('payment_terms', 500), 'notes' => $s('notes', 5000),
            'locale' => in_array($head['locale'] ?? 'fr', ['fr', 'ar'], true) ? ($head['locale'] ?? 'fr') : 'fr',
        ];
    }

    /** Edit the customer block / terms of a draft created from an order (lines stay tied to the order). @param array<string,mixed> $head */
    public static function updateDraftHead(int $id, array $head): void
    {
        $inv = self::find($id);
        if ($inv === null || $inv['status'] !== 'draft') {
            throw new ValidationException(['customer_name' => __('finance.err_locked')]);
        }
        $values = self::headValues($head);
        unset($values['customer_id']);
        Database::instance()->update('invoices', $values, ['id' => $id]);
    }

    public static function deleteDraft(int $id): void
    {
        $inv = self::find($id);
        if ($inv === null || $inv['status'] !== 'draft' || $inv['invoice_number'] !== null) {
            throw new ValidationException(['invoice' => __('finance.err_delete_issued')]);
        }
        Database::instance()->delete('invoices', ['id' => $id]); // items cascade; a draft never had a number
        ActivityLogger::log('invoice.draft_deleted', 'invoice', $id);
    }

    /** Issue: number, dates, frozen company identity, integrity hash. */
    public static function issue(int $id, ?int $userId): array
    {
        $db = Database::instance();
        $inv = $db->transaction(function (Database $db) use ($id, $userId): array {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$id]);
            if ($inv === null || $inv['status'] !== 'draft') {
                throw new ValidationException(['invoice' => __('finance.err_locked')]);
            }
            if (self::items($id) === []) {
                throw new ValidationException(['invoice' => __('finance.err_no_lines')]);
            }
            $type = $inv['invoice_type'] === 'credit_note' ? 'credit_note' : 'invoice';
            $today = date('Y-m-d');
            $values = [
                'invoice_number' => SequenceService::next($type),
                'status' => 'issued', 'issue_date' => $today,
                'due_date' => $type === 'invoice' ? date('Y-m-d', strtotime('+' . max(0, (int) setting('invoice_due_days', 0)) . ' days')) : null,
                'company_snapshot' => json_encode(CompanyInfo::snapshot(), JSON_UNESCAPED_UNICODE),
                'issued_at' => date('Y-m-d H:i:s'), 'issued_by' => $userId,
            ];
            $db->update('invoices', $values, ['id' => $id]);
            $fresh = $db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
            $db->update('invoices', ['integrity_hash' => self::hash($fresh, self::items($id))], ['id' => $id]);
            return $db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
        });
        ActivityLogger::log($inv['invoice_type'] . '.issued', 'invoice', $id, (string) $inv['invoice_number']);
        if ($inv['invoice_type'] === 'invoice') {
            self::syncPaid($id);
        }
        return $inv;
    }

    /**
     * Credit note against an issued invoice: full (copy of every line) or partial (one line for an amount TTC).
     * When the invoice is fully credited it is marked cancelled, with the reason — it is never deleted.
     */
    public static function creditNote(int $invoiceId, string $reason, ?int $userId, ?string $amountTtc = null): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new ValidationException(['reason' => __('finance.err_reason')]);
        }
        $db = Database::instance();
        $id = $db->transaction(function (Database $db) use ($invoiceId, $reason, $userId, $amountTtc): int {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if ($inv === null || $inv['invoice_type'] !== 'invoice' || !in_array($inv['status'], self::ACTIVE, true)) {
                throw new ValidationException(['reason' => __('finance.err_not_creditable')]);
            }
            $remaining = Money::sub((string) $inv['total_ttc'], self::credited($invoiceId));
            $full = $amountTtc === null || $amountTtc === '' || Money::compare(Money::normalize($amountTtc), $remaining) === 0 && Money::compare($remaining, (string) $inv['total_ttc']) === 0;
            $head = array_intersect_key($inv, array_flip(['order_id', 'customer_id', 'locale', 'currency', 'customer_name', 'customer_company', 'customer_ice', 'customer_email', 'customer_phone', 'customer_address']));
            if ($full) {
                if (Money::compare($remaining, (string) $inv['total_ttc']) !== 0) {
                    throw new ValidationException(['amount' => __('finance.err_full_after_partial', ['max' => money($remaining)])]);
                }
                $cid = $db->insert('invoices', $head + [
                    'invoice_type' => 'credit_note', 'original_invoice_id' => $invoiceId, 'status' => 'draft',
                    'subtotal_ht' => $inv['subtotal_ht'], 'discount_total' => $inv['discount_total'], 'shipping_ht' => $inv['shipping_ht'],
                    'tax_total' => $inv['tax_total'], 'total_ttc' => $inv['total_ttc'], 'notes' => $reason, 'created_by' => $userId,
                ]);
                foreach (self::items($invoiceId) as $it) {
                    unset($it['id']);
                    $it['invoice_id'] = $cid;
                    $db->insert('invoice_items', $it);
                }
            } else {
                $amount = Money::normalize(str_replace([' ', ','], ['', '.'], $amountTtc));
                if (Money::toCents($amount) <= 0 || Money::compare($amount, $remaining) > 0) {
                    throw new ValidationException(['amount' => __('finance.err_credit_amount', ['max' => money($remaining)])]);
                }
                $rate = (string) ($db->value('SELECT tax_rate FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order LIMIT 1', [$invoiceId]) ?? '0');
                $ht = Money::htFromTtc($amount, $rate);
                $cid = $db->insert('invoices', $head + [
                    'invoice_type' => 'credit_note', 'original_invoice_id' => $invoiceId, 'status' => 'draft',
                    'subtotal_ht' => $ht, 'tax_total' => Money::sub($amount, $ht), 'total_ttc' => $amount, 'notes' => $reason, 'created_by' => $userId,
                ]);
                $db->insert('invoice_items', [
                    'invoice_id' => $cid, 'description' => mb_substr(__('finance.partial_credit_line', ['number' => $inv['invoice_number'], 'reason' => $reason]), 0, 500),
                    'quantity' => '1.00', 'unit_price_ht' => $ht, 'tax_rate' => $rate, 'total_ht' => $ht, 'total_tax' => Money::sub($amount, $ht), 'total_ttc' => $amount,
                ]);
            }
            return $cid;
        });
        $note = self::issue($id, $userId);
        $inv = self::find($invoiceId);
        if (Money::compare(self::credited($invoiceId), (string) $inv['total_ttc']) >= 0) {
            $db->update('invoices', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => $userId, 'cancel_reason' => mb_substr($reason, 0, 500)], ['id' => $invoiceId]);
            ActivityLogger::log('invoice.cancelled_by_credit_note', 'invoice', $invoiceId, (string) $note['invoice_number']);
        } else {
            self::syncPaid($invoiceId);
        }
        return $note;
    }

    /** Amount still owed on an invoice after credit notes and payments. */
    public static function balance(array $inv): string
    {
        $net = Money::sub((string) $inv['total_ttc'], self::credited((int) $inv['id']));
        return Money::toCents($net) > Money::toCents((string) $inv['amount_paid']) ? Money::sub($net, (string) $inv['amount_paid']) : '0.00';
    }

    /**
     * Payments known for an invoice: those of its order (online/offline order payments) or those recorded on the invoice itself.
     */
    public static function syncPaid(int $id): void
    {
        $db = Database::instance();
        $inv = $db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
        if ($inv === null || $inv['invoice_type'] !== 'invoice' || !in_array($inv['status'], self::ACTIVE, true)) {
            return;
        }
        $due = Money::sub((string) $inv['total_ttc'], self::credited($id));
        if ($inv['order_id'] !== null) {
            $paid = (string) $db->value('SELECT amount_paid FROM orders WHERE id = ?', [(int) $inv['order_id']]);
        } else {
            $paid = Money::normalize((string) $db->value(
                "SELECT COALESCE(SUM(CASE WHEN payment_type = 'payment' THEN amount ELSE -amount END), 0) FROM payments WHERE invoice_id = ? AND status = 'confirmed'",
                [$id]
            ));
        }
        $paid = Money::toCents($paid) < 0 ? '0.00' : (Money::compare($paid, $due) > 0 ? $due : Money::normalize($paid));
        $status = match (true) {
            Money::toCents($due) > 0 && Money::compare($paid, $due) >= 0 => 'paid',
            Money::toCents($paid) > 0 => 'partially_paid',
            default => 'issued',
        };
        $db->update('invoices', ['amount_paid' => $paid, 'status' => $status], ['id' => $id]);
    }

    public static function syncForOrder(int $orderId): void
    {
        foreach (Database::instance()->fetchAll("SELECT id FROM invoices WHERE order_id = ? AND invoice_type = 'invoice' AND status IN ('issued','partially_paid','paid')", [$orderId]) as $r) {
            self::syncPaid((int) $r['id']);
        }
    }

    /**
     * SHA-256 of the legally relevant content (number, dates, parties, lines, totals).
     *
     * @param array<string,mixed> $inv
     * @param list<array<string,mixed>> $items
     */
    public static function hash(array $inv, array $items): string
    {
        $content = [
            'number' => $inv['invoice_number'], 'type' => $inv['invoice_type'], 'original' => $inv['original_invoice_id'],
            'issue_date' => $inv['issue_date'], 'due_date' => $inv['due_date'],
            'customer' => [$inv['customer_name'], $inv['customer_company'], $inv['customer_ice'], $inv['customer_address']],
            'company' => $inv['company_snapshot'] !== null ? json_decode((string) $inv['company_snapshot'], true) : null,
            'lines' => array_map(fn ($l) => [$l['description'], $l['specifications'], (string) $l['quantity'], (string) $l['unit_price_ht'], (string) $l['discount_amount'],
                (string) $l['tax_rate'], (string) $l['total_ht'], (string) $l['total_tax'], (string) $l['total_ttc']], $items),
            'totals' => [(string) $inv['subtotal_ht'], (string) $inv['discount_total'], (string) $inv['shipping_ht'], (string) $inv['tax_total'], (string) $inv['total_ttc']],
        ];
        return hash('sha256', (string) json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** True when the stored content still matches the hash taken at issue time (null for drafts). @param array<string,mixed> $inv */
    public static function verify(array $inv): ?bool
    {
        if ($inv['integrity_hash'] === null) {
            return null;
        }
        return hash_equals((string) $inv['integrity_hash'], self::hash($inv, self::items((int) $inv['id'])));
    }

    /** New secret download link for the customer (only the hash is kept). */
    public static function newAccessToken(int $id): string
    {
        $raw = bin2hex(random_bytes(32));
        Database::instance()->update('invoices', ['access_token' => hash('sha256', $raw)], ['id' => $id]);
        return $raw;
    }

    /** @return array<string,mixed>|null */
    public static function findByToken(int $id, string $raw): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return null;
        }
        $inv = self::find($id);
        if ($inv === null || $inv['status'] === 'draft' || $inv['access_token'] === null || !hash_equals((string) $inv['access_token'], hash('sha256', $raw))) {
            return null;
        }
        return $inv;
    }
}
