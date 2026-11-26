<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Services\Admin\DashboardStats;
use App\Services\Sales\OrderStatus;

/**
 * Admin dashboard: real KPIs and charts (no sample data), setup checklist and recent activity.
 * Financial figures are only shown to roles allowed to see them.
 */
final class DashboardController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $finance = can('finance.view') || can('reports.view');
        $kpis = DashboardStats::kpis();
        $from90 = date('Y-m-d', strtotime('-90 days'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $monthly = DashboardStats::monthly(12);
        $months = array_map(fn ($m) => self::monthLabel($m['month']), $monthly);
        $charts = [
            'monthlySales' => ['labels' => $months, 'values' => array_map(fn ($m) => (float) $m['sales'], $monthly), 'orders' => array_map(fn ($m) => $m['orders'], $monthly)],
            'monthlyProfit' => ['labels' => $months, 'values' => array_map(fn ($m) => (float) $m['profit'], $monthly)],
            'ordersByStatus' => array_values(array_filter(array_map(fn ($s) => ['label' => OrderStatus::label($s['status']), 'value' => $s['count']], DashboardStats::ordersByStatus()), fn ($r) => $r['value'] > 0)),
            'revenueByCategory' => array_map(fn ($r) => ['label' => $r['label'], 'value' => (float) $r['amount']], DashboardStats::revenueByCategory($from90, $tomorrow)),
            'bestSellers' => array_map(fn ($r) => ['label' => $r['title'], 'value' => $r['quantity']], DashboardStats::bestSellers($from90, $tomorrow)),
            'currency' => (string) setting('currency_symbol', 'DH'),
            'i18n' => [
                'sales' => __('admin.chart_sales'), 'orders' => __('admin.chart_orders_count'), 'profit' => __('admin.chart_profit'),
                'quantity' => __('admin.chart_quantity'), 'amount' => __('admin.chart_amount'), 'empty' => __('admin.chart_empty'),
            ],
        ];
        if (!$finance) {
            unset($charts['monthlySales'], $charts['monthlyProfit'], $charts['revenueByCategory']);
        }
        $setupChecklist = [
            ['label' => __('admin.check_company'), 'done' => (string) setting('company_email', '') !== '' && (string) setting('company_phone', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_logo'), 'done' => (string) setting('company_logo', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_legal'), 'done' => (string) setting('company_ice', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_mail'), 'done' => (string) setting('mail_driver', 'mail') === 'smtp' && (string) setting('mail_host', '') !== '', 'url' => route('admin.settings', ['tab' => 'mail'])],
            ['label' => __('admin.check_whatsapp'), 'done' => (string) setting('company_whatsapp', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_products'), 'done' => (int) $db->value("SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL AND sku NOT LIKE 'DEMO-%'") > 0, 'url' => app()->router()->has('admin.products.create') ? route('admin.products.create') : null],
            ['label' => __('admin.check_https'), 'done' => $this->request->isSecure(), 'url' => null],
        ];
        $recentActivity = can('logs.view') ? $db->fetchAll(
            "SELECT a.*, u.name AS user_name FROM activity_logs a LEFT JOIN users u ON a.actor_type = 'user' AND u.id = a.actor_id ORDER BY a.id DESC LIMIT 8"
        ) : [];
        return $this->view('admin/dashboard', [
            'kpis' => $kpis,
            'finance' => $finance,
            'charts' => $charts,
            'latestOrders' => can('orders.view') ? DashboardStats::latestOrders(8) : [],
            'lowStock' => can('products.view') ? DashboardStats::lowStock() : [],
            'setupChecklist' => $setupChecklist,
            'recentActivity' => $recentActivity,
            'pendingMigrations' => can('settings.manage') ? count((new \App\Core\Migrator($db, base_path('database/migrations')))->pending()) : 0,
            'hasDemo' => (int) $db->value("SELECT COUNT(*) FROM products WHERE sku LIKE 'DEMO-%'") > 0,
        ]);
    }

    public static function monthLabel(string $ym): string
    {
        $m = (int) substr($ym, 5, 2);
        $names = locale() === 'ar'
            ? ['يناير', 'فبراير', 'مارس', 'أبريل', 'ماي', 'يونيو', 'يوليوز', 'غشت', 'شتنبر', 'أكتوبر', 'نونبر', 'دجنبر']
            : ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        return $names[$m - 1] . ' ' . substr($ym, 2, 2);
    }
}
