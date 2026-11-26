<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\Sales\OrderStatus;

/**
 * Business figures for the admin dashboard — computed from real records only.
 *
 * Definitions (kept explicit so nothing is counted twice):
 *  - Active orders  : every order except cancelled / returned.
 *  - Sales (TTC)    : Σ grand_total of active orders, by order date.
 *  - Sales (HT)     : Σ (grand_total − tax_total).
 *  - Collected      : confirmed payments − confirmed refunds, by payment date (cash view).
 *  - Outstanding    : Σ balance_due of active orders not fully paid.
 *  - Estimated profit (period) = Sales HT − production cost snapshots (orders.cost_total)
 *                                 − actual courier costs (shipments.shipping_cost)
 *                                 − operating expenses HT (expenses.amount − tax_amount).
 *    Material purchases are NOT subtracted: materials reach the result through the production cost,
 *    subtracting purchases as well would count them twice.
 */
final class DashboardStats
{
    private const INACTIVE = [OrderStatus::CANCELLED, OrderStatus::RETURNED];
    public const PENDING = [OrderStatus::NEW, OrderStatus::AWAITING_CONFIRMATION];
    public const PRODUCTION = [OrderStatus::AWAITING_ARTWORK_APPROVAL, OrderStatus::APPROVED_FOR_PRODUCTION, OrderStatus::PRINTING,
        OrderStatus::FRAMING, OrderStatus::QUALITY_CONTROL, OrderStatus::READY_FOR_SHIPPING];

    private static function db(): Database
    {
        return Database::instance();
    }

    private static function active(string $alias = 'o'): string
    {
        return "{$alias}.status NOT IN ('" . implode("','", self::INACTIVE) . "')";
    }

    private static function in(array $values): string
    {
        return "('" . implode("','", $values) . "')";
    }

    /** @return array{sales:string,sales_ht:string,orders:int} */
    public static function sales(string $from, string $to): array
    {
        $r = self::db()->fetch(
            'SELECT COALESCE(SUM(grand_total), 0) AS ttc, COALESCE(SUM(grand_total - tax_total), 0) AS ht, COUNT(*) AS n
             FROM orders o WHERE ' . self::active() . ' AND o.placed_at >= ? AND o.placed_at < ?',
            [$from, $to]
        );
        return ['sales' => Money::normalize((string) $r['ttc']), 'sales_ht' => Money::normalize((string) $r['ht']), 'orders' => (int) $r['n']];
    }

    public static function collected(string $from, string $to): string
    {
        $r = self::db()->fetch(
            "SELECT COALESCE(SUM(CASE WHEN payment_type = 'payment' THEN amount ELSE -amount END), 0) AS net
             FROM payments WHERE status = 'confirmed' AND paid_at >= ? AND paid_at < ?",
            [$from, $to]
        );
        return Money::normalize((string) $r['net']);
    }

    public static function expensesHt(string $from, string $to): string
    {
        return Money::normalize((string) self::db()->value(
            'SELECT COALESCE(SUM(amount - tax_amount), 0) FROM expenses WHERE expense_date >= ? AND expense_date < ?',
            [substr($from, 0, 10), substr($to, 0, 10)]
        ));
    }

    /** @return array{sales_ht:string,production_cost:string,shipping_cost:string,expenses:string,profit:string} */
    public static function profit(string $from, string $to): array
    {
        $s = self::sales($from, $to);
        $cost = Money::normalize((string) self::db()->value('SELECT COALESCE(SUM(cost_total), 0) FROM orders o WHERE ' . self::active() . ' AND o.placed_at >= ? AND o.placed_at < ?', [$from, $to]));
        $ship = Money::normalize((string) self::db()->value(
            'SELECT COALESCE(SUM(s.shipping_cost), 0) FROM shipments s JOIN orders o ON o.id = s.order_id WHERE ' . self::active() . ' AND o.placed_at >= ? AND o.placed_at < ?',
            [$from, $to]
        ));
        $exp = self::expensesHt($from, $to);
        return [
            'sales_ht' => $s['sales_ht'], 'production_cost' => $cost, 'shipping_cost' => $ship, 'expenses' => $exp,
            'profit' => Money::sub(Money::sub(Money::sub($s['sales_ht'], $cost), $ship), $exp),
        ];
    }

    /** @return array<string,mixed> headline figures */
    public static function kpis(?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
        $monthStart = date('Y-m-01', strtotime($today));
        $nextMonth = date('Y-m-01', strtotime($monthStart . ' +1 month'));
        $prevMonth = date('Y-m-01', strtotime($monthStart . ' -1 month'));
        $db = self::db();
        $count = fn (array $statuses) => (int) $db->value('SELECT COUNT(*) FROM orders WHERE status IN ' . self::in($statuses));
        $all = self::sales('2000-01-01', $tomorrow . ' 00:00:00');
        $month = self::sales($monthStart, $nextMonth);
        $prev = self::sales($prevMonth, $monthStart);
        return [
            'revenue_total' => $all['sales'],
            'revenue_today' => self::sales($today, $tomorrow)['sales'],
            'revenue_month' => $month['sales'],
            'revenue_prev_month' => $prev['sales'],
            'orders_month' => $month['orders'],
            'orders_total' => (int) $db->value('SELECT COUNT(*) FROM orders'),
            'orders_pending' => $count(self::PENDING),
            'orders_production' => $count(self::PRODUCTION),
            'orders_completed' => $count([OrderStatus::DELIVERED]),
            'customers' => (int) $db->value('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL'),
            'customers_month' => (int) $db->value('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND created_at >= ?', [$monthStart]),
            'outstanding' => Money::normalize((string) $db->value(
                "SELECT COALESCE(SUM(balance_due), 0) FROM orders o WHERE " . self::active() . " AND payment_status IN ('unpaid','partial')"
            )),
            'collected_month' => self::collected($monthStart, $nextMonth),
            'expenses_month' => self::expensesHt($monthStart, $nextMonth),
            'profit_month' => self::profit($monthStart, $nextMonth),
            'average_order' => $month['orders'] > 0 ? Money::fromCents(intdiv(Money::toCents($month['sales']), $month['orders'])) : '0.00',
        ];
    }

    /** Last N months, oldest first: [{month: 'YYYY-MM', sales, sales_ht, orders, profit}] @return list<array<string,mixed>> */
    public static function monthly(int $months = 12, ?string $today = null): array
    {
        $start = date('Y-m-01', strtotime(date('Y-m-01', strtotime($today ?? 'now')) . ' -' . ($months - 1) . ' months'));
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $from = date('Y-m-01', strtotime($start . " +{$i} months"));
            $to = date('Y-m-01', strtotime($from . ' +1 month'));
            $s = self::sales($from, $to);
            $p = self::profit($from, $to);
            $out[] = ['month' => substr($from, 0, 7), 'sales' => $s['sales'], 'sales_ht' => $s['sales_ht'], 'orders' => $s['orders'], 'profit' => $p['profit']];
        }
        return $out;
    }

    /** @return list<array{status:string,count:int}> current pipeline (every status, even 0) */
    public static function ordersByStatus(): array
    {
        $rows = [];
        foreach (self::db()->fetchAll('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) {
            $rows[$r['status']] = (int) $r['n'];
        }
        return array_map(fn ($s) => ['status' => $s, 'count' => $rows[$s] ?? 0], OrderStatus::ALL);
    }

    /** Sales (line totals) by category over the period; custom designs grouped separately. @return list<array{label:string,amount:string}> */
    public static function revenueByCategory(string $from, string $to, int $limit = 8): array
    {
        $rows = self::db()->fetchAll(
            "SELECT COALESCE(c.id, 0) AS cid, c.name, MAX(oi.custom_design_id IS NOT NULL AND oi.product_id IS NULL) AS custom, SUM(oi.line_total) AS amount
             FROM order_items oi JOIN orders o ON o.id = oi.order_id
             LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
             WHERE " . self::active() . " AND o.placed_at >= ? AND o.placed_at < ?
             GROUP BY COALESCE(c.id, 0), c.name ORDER BY amount DESC LIMIT " . $limit,
            [$from, $to]
        );
        return array_map(fn ($r) => [
            'label' => $r['name'] ?? __('admin.uncategorized_custom'),
            'amount' => Money::normalize((string) $r['amount']),
        ], $rows);
    }

    /** @return list<array{title:string,quantity:int,amount:string,product_id:?int}> */
    public static function bestSellers(string $from, string $to, int $limit = 8): array
    {
        return array_map(fn ($r) => ['title' => $r['title'], 'quantity' => (int) $r['qty'], 'amount' => Money::normalize((string) $r['amount']), 'product_id' => $r['product_id'] !== null ? (int) $r['product_id'] : null],
            self::db()->fetchAll(
                "SELECT oi.product_id, MIN(oi.title) AS title, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS amount
                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE " . self::active() . " AND o.placed_at >= ? AND o.placed_at < ?
                 GROUP BY oi.product_id, CASE WHEN oi.product_id IS NULL THEN oi.title END
                 ORDER BY qty DESC, amount DESC LIMIT " . $limit,
                [$from, $to]
            ));
    }

    /** @return list<array<string,mixed>> */
    public static function latestOrders(int $limit = 8): array
    {
        return self::db()->fetchAll('SELECT order_number, customer_name, status, payment_status, grand_total, placed_at FROM orders ORDER BY id DESC LIMIT ' . $limit);
    }

    /** @return list<array<string,mixed>> tracked products under 5 units */
    public static function lowStock(int $limit = 6): array
    {
        return self::db()->fetchAll(
            'SELECT id, title, sku, stock_quantity FROM products WHERE track_stock = 1 AND is_active = 1 AND deleted_at IS NULL AND stock_quantity <= 5 ORDER BY stock_quantity LIMIT ' . $limit
        );
    }
}
