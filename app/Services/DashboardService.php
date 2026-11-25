<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Indicateurs du tableau de bord — toutes les valeurs proviennent de la base.
 */
final class DashboardService
{
    private Database $db;
    private \DateTimeImmutable $now;

    public function __construct()
    {
        $this->db = Database::instance();
        $this->now = new \DateTimeImmutable('today');
    }

    /** Bornes [début, fin] (Y-m-d) des périodes de référence. */
    public function periods(): array
    {
        $today = $this->now->format('Y-m-d');
        $weekStart = $this->now->modify('monday this week');
        $monthStart = $this->now->modify('first day of this month');
        $prevMonthStart = $monthStart->modify('-1 month');
        return [
            'today'      => [$today, $today],
            'yesterday'  => [$this->now->modify('-1 day')->format('Y-m-d'), $this->now->modify('-1 day')->format('Y-m-d')],
            'week'       => [$weekStart->format('Y-m-d'), $today],
            'prev_week'  => [$weekStart->modify('-7 days')->format('Y-m-d'), $this->now->modify('-7 days')->format('Y-m-d')],
            'month'      => [$monthStart->format('Y-m-d'), $today],
            // Même nombre de jours écoulés le mois précédent (comparaison équitable)
            'prev_month' => [$prevMonthStart->format('Y-m-d'), min(
                $prevMonthStart->modify('last day of this month')->format('Y-m-d'),
                $prevMonthStart->modify('+' . ((int) $this->now->format('j') - 1) . ' days')->format('Y-m-d')
            )],
            'year'       => [$this->now->format('Y-01-01'), $today],
            'prev_year'  => [$this->now->modify('-1 year')->format('Y-01-01'), $this->now->modify('-1 year')->format('Y-m-d')],
        ];
    }

    /** Chiffre d'affaires, dépenses et bénéfice par période, avec variation. */
    public function financialKpis(): array
    {
        $p = $this->periods();
        $kpis = [];
        foreach (['today' => 'yesterday', 'week' => 'prev_week', 'month' => 'prev_month', 'year' => 'prev_year'] as $cur => $prev) {
            [$rev] = Analytics::revenueBetween(...$p[$cur]);
            [$prevRev] = Analytics::revenueBetween(...$p[$prev]);
            $kpis['revenue_' . $cur] = ['value' => $rev, 'previous' => $prevRev, 'change' => self::change($rev, $prevRev)];
        }
        [$monthRev, $monthCost] = Analytics::revenueBetween(...$p['month']);
        $monthExp = Analytics::expensesBetween(...$p['month']);
        [$yearRev, $yearCost] = Analytics::revenueBetween(...$p['year']);
        $yearExp = Analytics::expensesBetween(...$p['year']);
        $kpis['expenses_month'] = $monthExp;
        $kpis['expenses_year'] = $yearExp;
        $kpis['profit_month'] = $monthRev - $monthCost - $monthExp;
        $kpis['profit_year'] = $yearRev - $yearCost - $yearExp;
        $kpis['margin_month'] = $monthRev > 0 ? ($monthRev - $monthCost) / $monthRev * 100 : null;
        return $kpis;
    }

    private static function change(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return null;
        }
        return ($current - $previous) / abs($previous) * 100;
    }

    /** Compteurs commerciaux et opérationnels. */
    public function counters(): array
    {
        [$monthFrom, $monthTo] = $this->periods()['month'];
        $sales = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(DATE(sale_date) BETWEEN ? AND ?) AS month
               FROM sales WHERE status IN ('completed','partially_refunded','refunded')",
            [$monthFrom, $monthTo]
        );
        $invoices = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'paid') AS paid,
                    SUM(status = 'partial') AS partial,
                    SUM(status IN ('validated','unpaid','overdue')) AS unpaid,
                    SUM(status = 'overdue' OR (status IN ('validated','unpaid','partial') AND due_date < CURDATE())) AS overdue,
                    COALESCE(SUM(CASE WHEN status IN ('validated','unpaid','partial','overdue') THEN balance_due END), 0) AS outstanding
               FROM invoices WHERE status NOT IN ('draft','cancelled')"
        );
        $quotes = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status IN ('sent','pending')) AS pending,
                    SUM(status IN ('accepted','converted')) AS accepted,
                    SUM(status = 'draft') AS draft,
                    COALESCE(SUM(CASE WHEN status IN ('sent','pending') THEN total END), 0) AS pending_amount
               FROM quotations WHERE deleted_at IS NULL"
        );
        $customers = $this->db->fetch(
            'SELECT COUNT(*) AS total, SUM(created_at >= ?) AS new_month
               FROM customers WHERE deleted_at IS NULL AND is_walk_in = 0',
            [$monthFrom . ' 00:00:00']
        );
        $production = $this->db->fetch(
            "SELECT SUM(status IN ('order','design','design_validation','production','finishing','quality_control')) AS in_progress,
                    SUM(status NOT IN ('completed','cancelled','ready','delivery') AND deadline IS NOT NULL AND deadline < NOW()) AS late,
                    SUM(status IN ('ready','delivery')) AS to_deliver
               FROM production_orders WHERE deleted_at IS NULL"
        );
        $deliveries = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM deliveries WHERE status IN ('to_prepare','ready','in_delivery','installation_planned')"
        );

        return [
            'sales_total'          => (int) ($sales['total'] ?? 0),
            'sales_month'          => (int) ($sales['month'] ?? 0),
            'invoices_total'       => (int) ($invoices['total'] ?? 0),
            'invoices_paid'        => (int) ($invoices['paid'] ?? 0),
            'invoices_partial'     => (int) ($invoices['partial'] ?? 0),
            'invoices_unpaid'      => (int) ($invoices['unpaid'] ?? 0),
            'invoices_overdue'     => (int) ($invoices['overdue'] ?? 0),
            'invoices_outstanding' => (float) ($invoices['outstanding'] ?? 0),
            'quotes_total'         => (int) ($quotes['total'] ?? 0),
            'quotes_pending'       => (int) ($quotes['pending'] ?? 0),
            'quotes_accepted'      => (int) ($quotes['accepted'] ?? 0),
            'quotes_pending_amount'=> (float) ($quotes['pending_amount'] ?? 0),
            'customers_total'      => (int) ($customers['total'] ?? 0),
            'customers_new'        => (int) ($customers['new_month'] ?? 0),
            'production_progress'  => (int) ($production['in_progress'] ?? 0),
            'production_late'      => (int) ($production['late'] ?? 0),
            'to_deliver'           => (int) ($production['to_deliver'] ?? 0) + $deliveries,
        ];
    }

    /** CA, dépenses et bénéfice des 12 derniers mois (mois courant inclus). */
    public function monthlySeries(): array
    {
        $start = $this->now->modify('first day of this month')->modify('-11 months');
        $from = $start->format('Y-m-d');
        $to = $this->now->format('Y-m-d');

        $rev = [];
        foreach ($this->db->fetchAll(
            "SELECT DATE_FORMAT(doc_date, '%Y-%m') AS ym, SUM(revenue_ht) AS revenue, SUM(cost) AS cost
               FROM " . Analytics::revenueSql() . " r WHERE doc_date BETWEEN ? AND ? GROUP BY ym",
            [$from, $to]
        ) as $r) {
            $rev[$r['ym']] = $r;
        }
        $exp = [];
        foreach ($this->db->fetchAll(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount - tax_amount) AS total
               FROM expenses WHERE deleted_at IS NULL AND expense_date BETWEEN ? AND ? GROUP BY ym",
            [$from, $to]
        ) as $r) {
            $exp[$r['ym']] = (float) $r['total'];
        }

        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $out = ['labels' => [], 'revenue' => [], 'expenses' => [], 'profit' => []];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->modify("+{$i} months");
            $ym = $m->format('Y-m');
            $revenue = (float) ($rev[$ym]['revenue'] ?? 0);
            $cost = (float) ($rev[$ym]['cost'] ?? 0);
            $expense = $exp[$ym] ?? 0.0;
            $out['labels'][] = $months[(int) $m->format('n') - 1] . ' ' . $m->format('y');
            $out['revenue'][] = round($revenue, 2);
            $out['expenses'][] = round($expense, 2);
            $out['profit'][] = round($revenue - $cost - $expense, 2);
        }
        return $out;
    }

    /** CA HT par jour sur les 30 derniers jours. */
    public function dailySeries(int $days = 30): array
    {
        $start = $this->now->modify('-' . ($days - 1) . ' days');
        $rows = [];
        foreach ($this->db->fetchAll(
            "SELECT doc_date, SUM(revenue_ht) AS revenue, SUM(source = 'sale') AS tickets
               FROM " . Analytics::revenueSql() . " r WHERE doc_date BETWEEN ? AND ? GROUP BY doc_date",
            [$start->format('Y-m-d'), $this->now->format('Y-m-d')]
        ) as $r) {
            $rows[$r['doc_date']] = $r;
        }
        $out = ['labels' => [], 'revenue' => [], 'tickets' => []];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->modify("+{$i} days");
            $key = $d->format('Y-m-d');
            $out['labels'][] = $d->format('d/m');
            $out['revenue'][] = round((float) ($rows[$key]['revenue'] ?? 0), 2);
            $out['tickets'][] = (int) ($rows[$key]['tickets'] ?? 0);
        }
        return $out;
    }

    /** CA HT de l'année par catégorie. */
    public function salesByCategory(): array
    {
        [$from, $to] = $this->periods()['year'];
        return $this->db->fetchAll(
            "SELECT COALESCE(c.name, 'Sans catégorie / sur mesure') AS label, SUM(l.revenue_ht) AS value
               FROM " . Analytics::soldLinesSql() . " l
               LEFT JOIN products p ON p.id = l.product_id
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE l.doc_date BETWEEN ? AND ?
              GROUP BY label HAVING value <> 0 ORDER BY value DESC LIMIT 8",
            [$from, $to]
        );
    }

    /** CA HT de l'année par machine (machine associée au produit vendu). */
    public function salesByMachine(): array
    {
        [$from, $to] = $this->periods()['year'];
        return $this->db->fetchAll(
            'SELECT m.name AS label, SUM(l.revenue_ht) AS value
               FROM ' . Analytics::soldLinesSql() . ' l
               JOIN products p ON p.id = l.product_id
               JOIN machines m ON m.id = p.machine_id
              WHERE l.doc_date BETWEEN ? AND ?
              GROUP BY m.id, m.name HAVING value <> 0 ORDER BY value DESC',
            [$from, $to]
        );
    }

    /** Produits (ou services) les plus vendus de l'année, en CA HT. */
    public function topItems(bool $services, int $limit = 5): array
    {
        [$from, $to] = $this->periods()['year'];
        $types = $services
            ? "('service','design','installation','digital')"
            : "('product','print_job','finished_good')";
        return $this->db->fetchAll(
            "SELECT p.name AS label, u.symbol AS unit, SUM(l.quantity) AS quantity, SUM(l.revenue_ht) AS value
               FROM " . Analytics::soldLinesSql() . " l
               JOIN products p ON p.id = l.product_id
               LEFT JOIN units u ON u.id = p.unit_id
              WHERE l.doc_date BETWEEN ? AND ? AND p.product_type IN {$types}
              GROUP BY p.id, p.name, u.symbol ORDER BY value DESC LIMIT " . (int) $limit,
            [$from, $to]
        );
    }

    /** Meilleurs clients de l'année (hors client comptoir). */
    public function topCustomers(int $limit = 5): array
    {
        [$from, $to] = $this->periods()['year'];
        return $this->db->fetchAll(
            "SELECT COALESCE(NULLIF(c.company_name, ''), c.full_name) AS label, SUM(r.revenue_ht) AS value, COUNT(*) AS documents
               FROM " . Analytics::revenueSql() . " r
               JOIN customers c ON c.id = r.customer_id
              WHERE r.doc_date BETWEEN ? AND ? AND c.is_walk_in = 0
              GROUP BY c.id, label HAVING value > 0 ORDER BY value DESC LIMIT " . (int) $limit,
            [$from, $to]
        );
    }

    /** Performance des machines sur le mois : heures, production, coût, travaux. */
    public function machinePerformance(): array
    {
        [$from, $to] = $this->periods()['month'];
        return $this->db->fetchAll(
            "SELECT m.id, m.name, m.status,
                    COALESCE(u.hours, 0) AS hours, COALESCE(u.units, 0) AS units, COALESCE(u.cost, 0) AS cost,
                    (SELECT COUNT(*) FROM production_orders po
                      WHERE po.machine_id = m.id AND po.deleted_at IS NULL
                        AND po.status NOT IN ('completed','cancelled')) AS active_jobs,
                    m.next_maintenance_at
               FROM machines m
               LEFT JOIN (SELECT machine_id, SUM(hours) AS hours, SUM(units_produced) AS units, SUM(cost) AS cost
                            FROM machine_usage WHERE usage_date BETWEEN ? AND ? GROUP BY machine_id) u ON u.machine_id = m.id
              WHERE m.deleted_at IS NULL AND m.is_active = 1
              ORDER BY hours DESC, m.name",
            [$from, $to]
        );
    }

    /** Articles et matières sous le seuil minimum. */
    public function lowStock(int $limit = 8): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM (
                SELECT 'Produit' AS kind, p.code, p.name, p.stock_quantity AS qty, p.min_stock, u.symbol
                  FROM products p JOIN units u ON u.id = p.unit_id
                 WHERE p.deleted_at IS NULL AND p.is_active = 1 AND p.track_stock = 1 AND p.stock_quantity <= p.min_stock
                UNION ALL
                SELECT 'Matière', m.code, m.name, m.stock_quantity, m.min_stock, u.symbol
                  FROM materials m JOIN units u ON u.id = m.unit_id
                 WHERE m.deleted_at IS NULL AND m.is_active = 1 AND m.min_stock > 0 AND m.stock_quantity <= m.min_stock
             ) s ORDER BY (qty - min_stock) ASC LIMIT " . (int) $limit
        );
    }

    /** Ordres de production en retard ou à échéance proche. */
    public function urgentProduction(int $limit = 6): array
    {
        return $this->db->fetchAll(
            "SELECT po.id, po.number, po.title, po.status, po.priority, po.deadline,
                    COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer
               FROM production_orders po JOIN customers c ON c.id = po.customer_id
              WHERE po.deleted_at IS NULL AND po.status NOT IN ('completed','cancelled')
                AND po.deadline IS NOT NULL AND po.deadline < (NOW() + INTERVAL 2 DAY)
              ORDER BY po.deadline ASC LIMIT " . (int) $limit
        );
    }

    public function recentPayments(int $limit = 6): array
    {
        return $this->db->fetchAll(
            "SELECT p.number, p.amount, p.payment_date, p.direction, pm.name AS method,
                    COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer
               FROM payments p
               JOIN payment_methods pm ON pm.id = p.payment_method_id
               JOIN customers c ON c.id = p.customer_id
              WHERE p.status = 'valid'
              ORDER BY p.payment_date DESC, p.id DESC LIMIT " . (int) $limit
        );
    }

    public function recentActivity(int $limit = 8): array
    {
        return $this->db->fetchAll(
            'SELECT l.created_at, l.action, l.module, l.description, u.full_name
               FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
              ORDER BY l.id DESC LIMIT ' . (int) $limit
        );
    }
}
