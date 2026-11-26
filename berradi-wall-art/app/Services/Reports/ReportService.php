<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Core\Database;
use App\Helpers\Money;

/**
 * Business reports over a period [from, to). Every report returns columns + rows of plain values
 * (strings / numbers) so the same data feeds the HTML table, the chart and the CSV export.
 * Orders count from their placement date; cancelled and returned orders are excluded from sales.
 */
final class ReportService
{
    public const REPORTS = ['sales', 'products', 'categories', 'customers', 'cities', 'channels', 'production', 'inventory', 'expenses', 'catalogue'];
    private const ACTIVE = "o.status NOT IN ('cancelled','returned')";

    /**
     * @return array{columns:list<array{key:string,label:string,type:string}>,rows:list<array<string,mixed>>,chart:?array{label:string,value:string},totals:array<string,mixed>}
     */
    public static function run(string $report, string $from, string $to, array $opt = []): array
    {
        return match ($report) {
            'sales' => self::sales($from, $to, (string) ($opt['group'] ?? 'day')),
            'products' => self::products($from, $to),
            'categories' => self::categories($from, $to),
            'customers' => self::customers($from, $to),
            'cities' => self::cities($from, $to),
            'channels' => self::channels($from, $to),
            'production' => self::production($from, $to),
            'inventory' => self::inventory($from, $to),
            'expenses' => self::expenses($from, $to),
            'catalogue' => self::catalogue(),
            default => throw new \InvalidArgumentException('Unknown report'),
        };
    }

    private static function db(): Database
    {
        return Database::instance();
    }

    /** @param list<array{0:string,1:string,2:string}> $cols key, label key, type @return list<array{key:string,label:string,type:string}> */
    private static function cols(array $cols): array
    {
        return array_map(fn ($c) => ['key' => $c[0], 'label' => __('reports.col_' . $c[1]), 'type' => $c[2]], $cols);
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $keys @return array<string,mixed> */
    private static function sum(array $rows, array $keys): array
    {
        $t = [];
        foreach ($keys as $k) {
            $isMoney = $rows !== [] && is_string($rows[0][$k] ?? null) && str_contains((string) $rows[0][$k], '.');
            $t[$k] = $isMoney ? Money::add('0', ...array_map(fn ($r) => (string) $r[$k], $rows)) : array_sum(array_map(fn ($r) => (float) $r[$k], $rows));
        }
        return $t;
    }

    private static function money(mixed $v): string
    {
        return Money::normalize((string) ($v ?? '0'));
    }

    private static function sales(string $from, string $to, string $group): array
    {
        $fmt = match ($group) {
            'month' => '%Y-%m',
            'week' => '%x-S%v',
            default => '%Y-%m-%d',
        };
        $rows = array_map(fn ($r) => [
            'period' => $r['period'], 'orders' => (int) $r['orders'], 'items' => (int) $r['items'],
            'sales' => self::money($r['sales']), 'sales_ht' => self::money($r['sales_ht']), 'discounts' => self::money($r['discounts']),
            'shipping' => self::money($r['shipping']), 'avg' => (int) $r['orders'] > 0 ? Money::fromCents(intdiv(Money::toCents((string) $r['sales']), (int) $r['orders'])) : '0.00',
        ], self::db()->fetchAll(
            "SELECT DATE_FORMAT(o.placed_at, '{$fmt}') AS period, COUNT(*) AS orders, SUM((SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = o.id)) AS items,
                    SUM(o.grand_total) AS sales, SUM(o.grand_total - o.tax_total) AS sales_ht, SUM(o.discount_total) AS discounts, SUM(o.shipping_total) AS shipping
             FROM orders o WHERE " . self::ACTIVE . " AND o.placed_at >= ? AND o.placed_at < ? GROUP BY period ORDER BY period",
            [$from, $to]
        ));
        $totals = self::sum($rows, ['orders', 'items', 'sales', 'sales_ht', 'discounts', 'shipping']);
        $totals['avg'] = $totals['orders'] > 0 ? Money::fromCents(intdiv(Money::toCents((string) $totals['sales']), (int) $totals['orders'])) : '0.00';
        return [
            'columns' => self::cols([['period', 'period', 'text'], ['orders', 'orders', 'int'], ['items', 'items', 'int'], ['sales', 'sales_ttc', 'money'],
                ['sales_ht', 'sales_ht', 'money'], ['discounts', 'discounts', 'money'], ['shipping', 'shipping', 'money'], ['avg', 'avg_order', 'money']]),
            'rows' => $rows, 'chart' => ['label' => 'period', 'value' => 'sales'], 'totals' => $totals,
        ];
    }

    private static function products(string $from, string $to): array
    {
        $rows = array_map(fn ($r) => [
            'product' => $r['title'], 'sku' => (string) $r['sku'], 'qty' => (int) $r['qty'], 'orders' => (int) $r['orders'],
            'revenue' => self::money($r['revenue']), 'cost' => self::money($r['cost']), 'margin' => Money::sub(self::money($r['revenue']), self::money($r['cost'])),
        ], self::db()->fetchAll(
            "SELECT COALESCE(p.title, CASE WHEN oi.custom_design_id IS NOT NULL THEN ? ELSE oi.title END) AS title,
                    CASE WHEN oi.custom_design_id IS NOT NULL THEN 'CUSTOM' ELSE COALESCE(p.sku, oi.sku) END AS sku,
                    SUM(oi.quantity) AS qty, COUNT(DISTINCT o.id) AS orders, SUM(oi.line_total) AS revenue, SUM(oi.unit_cost * oi.quantity) AS cost
             FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id AND oi.custom_design_id IS NULL
             WHERE " . self::ACTIVE . " AND o.placed_at >= ? AND o.placed_at < ?
             GROUP BY title, sku ORDER BY revenue DESC LIMIT 200",
            [__('reports.custom_designs'), $from, $to]
        ));
        return [
            'columns' => self::cols([['product', 'product', 'text'], ['sku', 'sku', 'text'], ['qty', 'qty', 'int'], ['orders', 'orders', 'int'],
                ['revenue', 'revenue_ttc', 'money'], ['cost', 'cost', 'money'], ['margin', 'gross_margin', 'money']]),
            'rows' => $rows, 'chart' => ['label' => 'product', 'value' => 'revenue'], 'totals' => self::sum($rows, ['qty', 'revenue', 'cost', 'margin']),
        ];
    }

    private static function categories(string $from, string $to): array
    {
        $rows = array_map(fn ($r) => ['category' => $r['name'] ?? __('admin.uncategorized_custom'), 'qty' => (int) $r['qty'], 'revenue' => self::money($r['revenue'])],
            self::db()->fetchAll(
                "SELECT c.name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
                 FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
                 WHERE " . self::ACTIVE . " AND o.placed_at >= ? AND o.placed_at < ? GROUP BY c.id, c.name ORDER BY revenue DESC",
                [$from, $to]
            ));
        $total = Money::add('0', ...array_map(fn ($r) => $r['revenue'], $rows));
        foreach ($rows as &$r) {
            $r['share'] = Money::toCents($total) > 0 ? round(Money::toCents($r['revenue']) * 100 / Money::toCents($total), 1) : 0;
        }
        unset($r);
        return [
            'columns' => self::cols([['category', 'category', 'text'], ['qty', 'qty', 'int'], ['revenue', 'revenue_ttc', 'money'], ['share', 'share', 'percent']]),
            'rows' => $rows, 'chart' => ['label' => 'category', 'value' => 'revenue'], 'totals' => self::sum($rows, ['qty', 'revenue']),
        ];
    }

    private static function customers(string $from, string $to): array
    {
        $rows = array_map(fn ($r) => [
            'customer' => $r['name'], 'company' => (string) $r['company_name'], 'city' => (string) $r['city'], 'orders' => (int) $r['orders'],
            'revenue' => self::money($r['revenue']), 'first' => substr((string) $r['first_order'], 0, 10), 'last' => substr((string) $r['last_order'], 0, 10),
        ], self::db()->fetchAll(
            "SELECT COALESCE(CONCAT(c.first_name, ' ', c.last_name), MAX(o.customer_name)) AS name, c.company_name, MAX(o.shipping_city) AS city,
                    COUNT(*) AS orders, SUM(o.grand_total) AS revenue, MIN(o.placed_at) AS first_order, MAX(o.placed_at) AS last_order
             FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
             WHERE " . self::ACTIVE . " AND o.placed_at >= ? AND o.placed_at < ?
             GROUP BY COALESCE(CAST(o.customer_id AS CHAR), o.customer_phone), c.first_name, c.last_name, c.company_name ORDER BY revenue DESC LIMIT 200",
            [$from, $to]
        ));
        $repeat = count(array_filter($rows, fn ($r) => $r['orders'] > 1));
        $totals = self::sum($rows, ['orders', 'revenue']) + ['customers' => count($rows), 'repeat' => $repeat];
        return [
            'columns' => self::cols([['customer', 'customer', 'text'], ['company', 'company', 'text'], ['city', 'city', 'text'], ['orders', 'orders', 'int'],
                ['revenue', 'revenue_ttc', 'money'], ['first', 'first_order', 'date'], ['last', 'last_order', 'date']]),
            'rows' => $rows, 'chart' => null, 'totals' => $totals,
        ];
    }

    private static function cities(string $from, string $to): array
    {
        $rows = array_map(fn ($r) => ['city' => $r['city'], 'orders' => (int) $r['orders'], 'revenue' => self::money($r['revenue']), 'shipping' => self::money($r['shipping']), 'courier_cost' => self::money($r['courier_cost'])],
            self::db()->fetchAll(
                "SELECT o.shipping_city AS city, COUNT(*) AS orders, SUM(o.grand_total) AS revenue, SUM(o.shipping_total) AS shipping,
                        COALESCE(SUM((SELECT SUM(shipping_cost) FROM shipments s WHERE s.order_id = o.id)), 0) AS courier_cost
                 FROM orders o WHERE " . self::ACTIVE . " AND o.placed_at >= ? AND o.placed_at < ? GROUP BY o.shipping_city ORDER BY revenue DESC",
                [$from, $to]
            ));
        return [
            'columns' => self::cols([['city', 'city', 'text'], ['orders', 'orders', 'int'], ['revenue', 'revenue_ttc', 'money'], ['shipping', 'shipping_billed', 'money'], ['courier_cost', 'courier_cost', 'money']]),
            'rows' => $rows, 'chart' => ['label' => 'city', 'value' => 'orders'], 'totals' => self::sum($rows, ['orders', 'revenue', 'shipping', 'courier_cost']),
        ];
    }

    /** Order source × payment method, plus the cancellation rate. */
    private static function channels(string $from, string $to): array
    {
        $rows = array_map(fn ($r) => [
            'source' => __('orders.source_' . $r['source']), 'method' => (string) ($r['method'] ?? '—'), 'orders' => (int) $r['orders'],
            'cancelled' => (int) $r['cancelled'], 'cancel_rate' => (int) $r['orders'] > 0 ? round((int) $r['cancelled'] * 100 / (int) $r['orders'], 1) : 0,
            'revenue' => self::money($r['revenue']),
        ], self::db()->fetchAll(
            "SELECT o.source, m.name AS method, COUNT(*) AS orders, SUM(o.status IN ('cancelled','returned')) AS cancelled,
                    SUM(CASE WHEN " . self::ACTIVE . " THEN o.grand_total ELSE 0 END) AS revenue
             FROM orders o LEFT JOIN payment_methods m ON m.id = o.payment_method_id
             WHERE o.placed_at >= ? AND o.placed_at < ? GROUP BY o.source, m.name ORDER BY orders DESC",
            [$from, $to]
        ));
        return [
            'columns' => self::cols([['source', 'source', 'text'], ['method', 'payment_method', 'text'], ['orders', 'orders', 'int'], ['cancelled', 'cancelled', 'int'],
                ['cancel_rate', 'cancel_rate', 'percent'], ['revenue', 'revenue_ttc', 'money']]),
            'rows' => $rows, 'chart' => null, 'totals' => self::sum($rows, ['orders', 'cancelled', 'revenue']),
        ];
    }

    /** Jobs completed in the period: lead time, lateness and real costs per stage owner. */
    private static function production(string $from, string $to): array
    {
        $rows = array_map(fn ($r) => [
            'job' => $r['job_number'], 'order' => $r['order_number'], 'item' => (string) $r['title'],
            'started' => substr((string) $r['started_at'], 0, 16), 'completed' => substr((string) $r['completed_at'], 0, 16),
            'hours' => $r['started_at'] ? round((strtotime((string) $r['completed_at']) - strtotime((string) $r['started_at'])) / 3600, 1) : null,
            'late' => $r['deadline_at'] !== null && $r['completed_at'] > $r['deadline_at'] ? __('common.yes') : __('common.no'),
            'cost' => Money::add(self::money($r['material_cost']), self::money($r['labor_cost']), self::money($r['machine_cost']), self::money($r['other_cost'])),
        ], self::db()->fetchAll(
            "SELECT j.*, o.order_number, oi.title FROM production_jobs j JOIN orders o ON o.id = j.order_id LEFT JOIN order_items oi ON oi.id = j.order_item_id
             WHERE j.status = 'completed' AND j.completed_at >= ? AND j.completed_at < ? ORDER BY j.completed_at",
            [$from, $to]
        ));
        $hours = array_values(array_filter(array_map(fn ($r) => $r['hours'], $rows), fn ($h) => $h !== null));
        $totals = ['jobs' => count($rows), 'late' => count(array_filter($rows, fn ($r) => $r['late'] === __('common.yes'))),
            'avg_hours' => $hours !== [] ? round(array_sum($hours) / count($hours), 1) : 0, 'cost' => Money::add('0', ...array_map(fn ($r) => $r['cost'], $rows))];
        return [
            'columns' => self::cols([['job', 'job', 'text'], ['order', 'order', 'text'], ['item', 'item', 'text'], ['started', 'started', 'date'], ['completed', 'completed', 'date'],
                ['hours', 'lead_hours', 'number'], ['late', 'late', 'text'], ['cost', 'real_cost', 'money']]),
            'rows' => $rows, 'chart' => null, 'totals' => $totals,
        ];
    }

    /** Stock per material: opening, in, out (production, waste, other), closing, value. */
    private static function inventory(string $from, string $to): array
    {
        $rows = [];
        foreach (self::db()->fetchAll(
            "SELECT m.id, m.sku, m.name, m.unit, m.stock_quantity, m.unit_cost,
                    COALESCE(SUM(CASE WHEN s.created_at >= ? AND s.created_at < ? AND s.quantity > 0 THEN s.quantity END), 0) AS qin,
                    COALESCE(SUM(CASE WHEN s.created_at >= ? AND s.created_at < ? AND s.movement_type = 'production' THEN -s.quantity END), 0) AS qprod,
                    COALESCE(SUM(CASE WHEN s.created_at >= ? AND s.created_at < ? AND s.movement_type = 'waste' THEN -s.quantity END), 0) AS qwaste,
                    COALESCE(SUM(CASE WHEN s.created_at >= ? AND s.created_at < ? AND s.quantity < 0 AND s.movement_type NOT IN ('production','waste') THEN -s.quantity END), 0) AS qout,
                    COALESCE(SUM(CASE WHEN s.created_at >= ? THEN s.quantity END), 0) AS after_from,
                    COALESCE(SUM(CASE WHEN s.created_at >= ? THEN s.quantity END), 0) AS after_to,
                    COALESCE(SUM(CASE WHEN s.created_at >= ? AND s.created_at < ? AND s.movement_type = 'production' THEN s.total_cost END), 0) AS cprod
             FROM materials m LEFT JOIN stock_movements s ON s.material_id = m.id GROUP BY m.id ORDER BY m.name",
            [$from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to]
        ) as $r) {
            $q = fn ($v) => round((float) $v, 3);
            $rows[] = [
                'material' => $r['name'], 'sku' => $r['sku'], 'unit' => __('inventory.unit_' . $r['unit']),
                'opening' => $q((float) $r['stock_quantity'] - (float) $r['after_from']), 'in' => $q($r['qin']), 'production' => $q($r['qprod']),
                'waste' => $q($r['qwaste']), 'out' => $q($r['qout']), 'closing' => $q((float) $r['stock_quantity'] - (float) $r['after_to']),
                'consumed_cost' => self::money($r['cprod']), 'value' => Money::normalize(number_format(max(0, (float) $r['stock_quantity']) * (float) $r['unit_cost'], 2, '.', '')),
            ];
        }
        return [
            'columns' => self::cols([['material', 'material', 'text'], ['sku', 'sku', 'text'], ['unit', 'unit', 'text'], ['opening', 'opening', 'number'], ['in', 'in', 'number'],
                ['production', 'production', 'number'], ['waste', 'waste', 'number'], ['out', 'out', 'number'], ['closing', 'closing', 'number'],
                ['consumed_cost', 'consumed_cost', 'money'], ['value', 'stock_value_now', 'money']]),
            'rows' => $rows, 'chart' => null, 'totals' => self::sum($rows, ['consumed_cost', 'value']),
        ];
    }

    private static function expenses(string $from, string $to): array
    {
        $rows = array_map(fn ($r) => ['category' => __('finance.ec_' . $r['category']), 'count' => (int) $r['n'], 'ttc' => self::money($r['ttc']), 'tax' => self::money($r['tax']), 'ht' => self::money($r['ht'])],
            self::db()->fetchAll(
                'SELECT category, COUNT(*) AS n, SUM(amount) AS ttc, SUM(tax_amount) AS tax, SUM(amount - tax_amount) AS ht FROM expenses
                 WHERE expense_date >= ? AND expense_date < ? GROUP BY category ORDER BY ttc DESC',
                [substr($from, 0, 10), substr($to, 0, 10)]
            ));
        return [
            'columns' => self::cols([['category', 'category', 'text'], ['count', 'count', 'int'], ['ttc', 'amount_ttc', 'money'], ['tax', 'vat', 'money'], ['ht', 'amount_ht', 'money']]),
            'rows' => $rows, 'chart' => ['label' => 'category', 'value' => 'ttc'], 'totals' => self::sum($rows, ['count', 'ttc', 'tax', 'ht']),
        ];
    }

    /** Catalogue quality for SEO and conversion: what each active product still lacks. */
    private static function catalogue(): array
    {
        $rows = [];
        foreach (self::db()->fetchAll(
            "SELECT p.id, p.title, p.sku, p.main_image, p.description, p.seo_description, p.category_id,
                    (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id) AS images,
                    (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id AND (i.alt IS NULL OR i.alt = '')) AS no_alt,
                    (SELECT value FROM translations t WHERE t.entity_type = 'product' AND t.entity_id = p.id AND t.locale = 'ar' AND t.field = 'title') AS ar_title,
                    (SELECT value FROM translations t WHERE t.entity_type = 'product' AND t.entity_id = p.id AND t.locale = 'ar' AND t.field = 'description') AS ar_desc
             FROM products p WHERE p.is_active = 1 AND p.deleted_at IS NULL ORDER BY p.title"
        ) as $p) {
            $issues = array_filter([
                empty($p['main_image']) ? 'no_image' : null,
                (int) $p['images'] < 2 ? 'few_images' : null,
                (int) $p['no_alt'] > 0 ? 'missing_alt' : null,
                mb_strlen(strip_tags((string) $p['description'])) < 120 ? 'short_description' : null,
                trim((string) $p['seo_description']) === '' ? 'no_meta_description' : null,
                trim((string) $p['ar_title']) === '' ? 'no_ar_title' : null,
                trim((string) $p['ar_desc']) === '' ? 'no_ar_description' : null,
                $p['category_id'] === null ? 'no_category' : null,
            ]);
            if ($issues !== []) {
                $rows[] = ['product' => $p['title'], 'sku' => $p['sku'], 'score' => (int) round((8 - count($issues)) * 100 / 8),
                    'issues' => implode(', ', array_map(fn ($i) => __('reports.issue_' . $i), $issues)), 'id' => (int) $p['id']];
            }
        }
        usort($rows, fn ($a, $b) => $a['score'] <=> $b['score']);
        $active = (int) self::db()->value('SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL');
        return [
            'columns' => self::cols([['product', 'product', 'text'], ['sku', 'sku', 'text'], ['score', 'score', 'percent'], ['issues', 'to_improve', 'text']]),
            'rows' => $rows, 'chart' => null, 'totals' => ['active' => $active, 'complete' => $active - count($rows)],
        ];
    }

    /**
     * CSV for Excel (UTF-8 with BOM, semicolon, decimal comma). Cells that could be read as formulas are neutralised.
     *
     * @param array{columns:list<array{key:string,label:string,type:string}>,rows:list<array<string,mixed>>} $data
     */
    public static function csv(array $data): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        $safe = function (mixed $v, string $type): string {
            $s = (string) ($v ?? '');
            if (in_array($type, ['money', 'number', 'percent'], true) && is_numeric($s)) {
                return str_replace('.', ',', $s);
            }
            return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
        };
        fputcsv($fh, array_map(fn ($c) => $c['label'], $data['columns']), ';', '"', '');
        foreach ($data['rows'] as $r) {
            fputcsv($fh, array_map(fn ($c) => $safe($r[$c['key']] ?? '', $c['type']), $data['columns']), ';', '"', '');
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);
        return $out;
    }
}
