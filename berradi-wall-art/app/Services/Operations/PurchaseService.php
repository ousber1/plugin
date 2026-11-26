<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\Database;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\SequenceService;

/**
 * Supplier purchases: draft → ordered → received (stock entries at purchase cost, updating the WAC), or cancelled.
 * Payments to suppliers are recorded separately and can never exceed the purchase total.
 */
final class PurchaseService
{
    public const STATUSES = ['draft', 'ordered', 'received', 'cancelled'];

    /**
     * @param array{supplier_id:int,purchase_date:string,supplier_invoice_ref?:?string,notes?:?string,status?:string} $head
     * @param list<array{material_id:?int,description:string,quantity:string,unit_cost:string,tax_rate:string}> $lines
     */
    public static function save(?int $id, array $head, array $lines, ?int $userId): int
    {
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['description'] ?? '')) !== '' || !empty($l['material_id'])));
        if ($lines === []) {
            throw new ValidationException(['lines' => __('inventory.err_no_lines')]);
        }
        $db = Database::instance();
        $clean = [];
        $sub = 0;
        $tax = 0;
        foreach ($lines as $i => $l) {
            $q = str_replace(',', '.', trim((string) $l['quantity']));
            $c = str_replace(',', '.', trim((string) $l['unit_cost']));
            $r = str_replace(',', '.', trim((string) ($l['tax_rate'] ?? '0')));
            if (!preg_match('/^\d{1,10}(\.\d{1,3})?$/', $q) || InventoryService::toMilli($q) <= 0 || !preg_match('/^\d{1,8}(\.\d{1,4})?$/', $c) || !preg_match('/^\d{1,2}(\.\d{1,2})?$/', $r)) {
                throw new ValidationException(['lines' => __('inventory.err_line', ['n' => $i + 1])]);
            }
            $desc = trim((string) $l['description']);
            $mat = !empty($l['material_id']) ? (int) $l['material_id'] : null;
            if ($mat !== null) {
                $name = $db->value('SELECT name FROM materials WHERE id = ?', [$mat]);
                if ($name === null) {
                    throw new ValidationException(['lines' => __('inventory.err_line', ['n' => $i + 1])]);
                }
                $desc = $desc !== '' ? $desc : (string) $name;
            }
            // line HT in cents = qty (milli) × unit cost (1/10000) / 100000
            $lineCents = intdiv(InventoryService::toMilli($q) * InventoryService::costToMicro($c) + 50000, 100000);
            $lineTax = intdiv($lineCents * (int) round((float) $r * 100) + 5000, 10000);
            $sub += $lineCents;
            $tax += $lineTax;
            $clean[] = ['material_id' => $mat, 'description' => mb_substr($desc, 0, 255), 'quantity' => InventoryService::fromMilli(InventoryService::toMilli($q)),
                'unit_cost' => InventoryService::microToCost(InventoryService::costToMicro($c)), 'tax_rate' => number_format((float) $r / 100, 4, '.', ''), 'line_total' => Money::fromCents($lineCents)];
        }
        return $db->transaction(function (Database $db) use ($id, $head, $clean, $sub, $tax, $userId): int {
            $values = [
                'supplier_id' => (int) $head['supplier_id'], 'purchase_date' => $head['purchase_date'],
                'supplier_invoice_ref' => ($head['supplier_invoice_ref'] ?? '') !== '' ? $head['supplier_invoice_ref'] : null,
                'notes' => ($head['notes'] ?? '') !== '' ? $head['notes'] : null,
                'status' => in_array($head['status'] ?? 'draft', ['draft', 'ordered'], true) ? ($head['status'] ?? 'draft') : 'draft',
                'subtotal' => Money::fromCents($sub), 'tax_total' => Money::fromCents($tax), 'total' => Money::fromCents($sub + $tax),
            ];
            if ($id === null) {
                $id = $db->insert('purchases', $values + ['purchase_number' => SequenceService::next('purchase'), 'created_by' => $userId]);
            } else {
                $p = $db->fetch('SELECT status, amount_paid FROM purchases WHERE id = ? FOR UPDATE', [$id]);
                if ($p === null || !in_array($p['status'], ['draft', 'ordered'], true)) {
                    throw new ValidationException(['lines' => __('inventory.err_locked')]);
                }
                if (Money::compare((string) $p['amount_paid'], $values['total']) > 0) {
                    throw new ValidationException(['lines' => __('inventory.err_total_below_paid')]);
                }
                $db->update('purchases', $values, ['id' => $id]);
                $db->delete('purchase_items', ['purchase_id' => $id]);
            }
            foreach ($clean as $l) {
                $db->insert('purchase_items', $l + ['purchase_id' => $id]);
            }
            ActivityLogger::log('purchase.saved', 'purchase', $id, Money::fromCents($sub + $tax));
            return $id;
        });
    }

    /** Receive the goods: one stock entry per material line, valued at the purchase unit cost (HT). */
    public static function receive(int $id, ?int $userId): int
    {
        $n = 0;
        Database::instance()->transaction(function (Database $db) use ($id, $userId, &$n): void {
            $p = $db->fetch('SELECT * FROM purchases WHERE id = ? FOR UPDATE', [$id]);
            if ($p === null || !in_array($p['status'], ['draft', 'ordered'], true)) {
                throw new ValidationException(['status' => __('inventory.err_locked')]);
            }
            foreach ($db->fetchAll('SELECT * FROM purchase_items WHERE purchase_id = ? AND material_id IS NOT NULL', [$id]) as $l) {
                InventoryService::move((int) $l['material_id'], 'in', (string) $l['quantity'], (string) $l['unit_cost'], [
                    'purchase_id' => $id, 'user_id' => $userId, 'notes' => $p['purchase_number'],
                ]);
                $n++;
            }
            $db->update('purchases', ['status' => 'received', 'received_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        });
        ActivityLogger::log('purchase.received', 'purchase', $id, (string) $n);
        return $n;
    }

    public static function cancel(int $id): void
    {
        $db = Database::instance();
        $p = $db->fetch('SELECT status, amount_paid FROM purchases WHERE id = ?', [$id]);
        if ($p === null || !in_array($p['status'], ['draft', 'ordered'], true) || Money::toCents((string) $p['amount_paid']) > 0) {
            throw new ValidationException(['status' => __('inventory.err_cancel')]);
        }
        $db->update('purchases', ['status' => 'cancelled'], ['id' => $id]);
        ActivityLogger::log('purchase.cancelled', 'purchase', $id);
    }

    /** Record a payment to the supplier of a purchase. */
    public static function pay(int $id, string $amount, string $method, string $paidAt, ?string $reference, ?int $userId): int
    {
        return Database::instance()->transaction(function (Database $db) use ($id, $amount, $method, $paidAt, $reference, $userId): int {
            $p = $db->fetch('SELECT * FROM purchases WHERE id = ? FOR UPDATE', [$id]);
            if ($p === null || $p['status'] === 'cancelled') {
                throw new ValidationException(['amount' => __('inventory.err_locked')]);
            }
            $amount = Money::normalize($amount);
            $remaining = Money::sub((string) $p['total'], (string) $p['amount_paid']);
            if (Money::toCents($amount) <= 0 || Money::compare($amount, $remaining) > 0) {
                throw new ValidationException(['amount' => __('inventory.err_pay', ['max' => money($remaining)])]);
            }
            $pid = $db->insert('supplier_payments', [
                'supplier_id' => (int) $p['supplier_id'], 'purchase_id' => $id, 'amount' => $amount, 'payment_method' => $method,
                'reference' => $reference, 'paid_at' => $paidAt, 'created_by' => $userId,
            ]);
            $db->update('purchases', ['amount_paid' => Money::add((string) $p['amount_paid'], $amount)], ['id' => $id]);
            ActivityLogger::log('purchase.paid', 'purchase', $id, $amount);
            return $pid;
        });
    }
}
