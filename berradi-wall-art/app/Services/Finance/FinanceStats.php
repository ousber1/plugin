<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\Admin\DashboardStats;

/**
 * Period figures for the finance page: sales, invoicing, cash in/out, VAT position, receivables, payables.
 * Every figure is a plain SQL sum over recorded documents (nothing estimated except the profit line, which says so).
 */
final class FinanceStats
{
    /** @return array{from:string,to:string,label:string} [from, to) */
    public static function period(string $key, ?string $from = null, ?string $to = null): array
    {
        $today = date('Y-m-d');
        return match ($key) {
            'prev_month' => ['from' => date('Y-m-01', strtotime('first day of last month')), 'to' => date('Y-m-01'), 'label' => $key],
            'quarter' => ['from' => date('Y') . '-' . str_pad((string) (intdiv((int) date('n') - 1, 3) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01',
                'to' => date('Y-m-d', strtotime($today . ' +1 day')), 'label' => $key],
            'year' => ['from' => date('Y-01-01'), 'to' => date('Y-m-d', strtotime($today . ' +1 day')), 'label' => $key],
            'custom' => ['from' => $from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01'),
                'to' => $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? date('Y-m-d', strtotime($to . ' +1 day')) : date('Y-m-d', strtotime($today . ' +1 day')), 'label' => $key],
            default => ['from' => date('Y-m-01'), 'to' => date('Y-m-d', strtotime($today . ' +1 day')), 'label' => 'month'],
        };
    }

    /** @return array<string,mixed> */
    public static function summary(string $from, string $to): array
    {
        $db = Database::instance();
        $sum = fn (string $sql, array $p) => Money::normalize((string) $db->value($sql, $p));
        $dFrom = substr($from, 0, 10);
        $dTo = substr($to, 0, 10);
        $sales = DashboardStats::sales($from, $to);
        $invoiced = $db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN invoice_type = 'invoice' THEN total_ttc ELSE -total_ttc END), 0) AS ttc,
                    COALESCE(SUM(CASE WHEN invoice_type = 'invoice' THEN tax_total ELSE -tax_total END), 0) AS tax,
                    SUM(invoice_type = 'invoice') AS n_inv, SUM(invoice_type = 'credit_note') AS n_cn
             FROM invoices WHERE status <> 'draft' AND issue_date >= ? AND issue_date < ?",
            [$dFrom, $dTo]
        );
        $in = $sum("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed' AND payment_type = 'payment' AND paid_at >= ? AND paid_at < ?", [$from, $to]);
        $refunds = $sum("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed' AND payment_type = 'refund' AND paid_at >= ? AND paid_at < ?", [$from, $to]);
        $expenses = $sum('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= ? AND expense_date < ?', [$dFrom, $dTo]);
        $expensesTax = $sum('SELECT COALESCE(SUM(tax_amount), 0) FROM expenses WHERE expense_date >= ? AND expense_date < ?', [$dFrom, $dTo]);
        $supplierPaid = $sum('SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE paid_at >= ? AND paid_at < ?', [$dFrom, $dTo]);
        $purchasesTax = $sum("SELECT COALESCE(SUM(tax_total), 0) FROM purchases WHERE status = 'received' AND purchase_date >= ? AND purchase_date < ?", [$dFrom, $dTo]);
        $vatCollected = Money::normalize((string) $invoiced['tax']);
        $vatDeductible = Money::add($expensesTax, $purchasesTax);
        $out = Money::add($expenses, $supplierPaid, $refunds);
        return [
            'sales' => $sales['sales'], 'sales_ht' => $sales['sales_ht'], 'orders' => $sales['orders'],
            'invoiced' => Money::normalize((string) $invoiced['ttc']), 'invoices' => (int) $invoiced['n_inv'], 'credit_notes' => (int) $invoiced['n_cn'],
            'cash_in' => $in, 'refunds' => $refunds, 'expenses' => $expenses, 'supplier_paid' => $supplierPaid,
            'cash_out' => $out, 'net_cash' => Money::sub($in, $out),
            'vat_collected' => $vatCollected, 'vat_deductible' => $vatDeductible, 'vat_due' => Money::sub($vatCollected, $vatDeductible),
            'profit' => DashboardStats::profit($from, $to),
            'expenses_by_category' => $db->fetchAll('SELECT category, COUNT(*) AS n, SUM(amount) AS ttc, SUM(amount - tax_amount) AS ht FROM expenses WHERE expense_date >= ? AND expense_date < ? GROUP BY category ORDER BY ttc DESC', [$dFrom, $dTo]),
            'by_method' => $db->fetchAll(
                "SELECT COALESCE(m.name, '—') AS name, SUM(CASE WHEN p.payment_type = 'payment' THEN p.amount ELSE -p.amount END) AS total, COUNT(*) AS n
                 FROM payments p LEFT JOIN payment_methods m ON m.id = p.payment_method_id WHERE p.status = 'confirmed' AND p.paid_at >= ? AND p.paid_at < ? GROUP BY m.name ORDER BY total DESC",
                [$from, $to]
            ),
        ];
    }

    /** Money customers still owe: open order balances + standalone invoices. @return array<string,mixed> */
    public static function receivables(): array
    {
        $db = Database::instance();
        $orders = Money::normalize((string) $db->value("SELECT COALESCE(SUM(balance_due), 0) FROM orders WHERE status NOT IN ('cancelled','returned')"));
        $inv = '0.00';
        $overdue = [];
        foreach ($db->fetchAll("SELECT * FROM invoices WHERE invoice_type = 'invoice' AND status IN ('issued','partially_paid') ORDER BY due_date") as $i) {
            $left = InvoiceService::balance($i);
            if (Money::toCents($left) === 0) {
                continue;
            }
            if ($i['order_id'] === null) {
                $inv = Money::add($inv, $left);
            }
            if ($i['due_date'] !== null && $i['due_date'] < date('Y-m-d')) {
                $overdue[] = $i + ['left' => $left, 'days' => (int) ((time() - strtotime((string) $i['due_date'])) / 86400)];
            }
        }
        return ['orders' => $orders, 'invoices' => $inv, 'total' => Money::add($orders, $inv), 'overdue' => $overdue];
    }

    /** Owed to suppliers (received or ordered purchases not fully paid). */
    public static function payables(): string
    {
        return Money::normalize((string) Database::instance()->value("SELECT COALESCE(SUM(total - amount_paid), 0) FROM purchases WHERE status IN ('ordered','received')"));
    }

    /** Monthly cash in / out (last N months). @return list<array{month:string,in:string,out:string,net:string}> */
    public static function cashflow(int $months = 12): array
    {
        $out = [];
        $start = date('Y-m-01', strtotime(date('Y-m-01') . ' -' . ($months - 1) . ' months'));
        for ($i = 0; $i < $months; $i++) {
            $f = date('Y-m-01', strtotime($start . " +{$i} months"));
            $t = date('Y-m-01', strtotime($f . ' +1 month'));
            $s = self::summary($f, $t);
            $out[] = ['month' => substr($f, 0, 7), 'in' => $s['cash_in'], 'out' => $s['cash_out'], 'net' => $s['net_cash']];
        }
        return $out;
    }
}
