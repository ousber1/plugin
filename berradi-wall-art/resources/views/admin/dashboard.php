<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_dashboard')]);
$user = auth_user();
$router = app()->router();
use App\Helpers\Money;

$trend = null;
if (Money::toCents($kpis['revenue_prev_month']) > 0) {
    $trend = (int) round((Money::toCents($kpis['revenue_month']) - Money::toCents($kpis['revenue_prev_month'])) * 100 / Money::toCents($kpis['revenue_prev_month']));
}
$quick = array_filter([
    ['admin.products.create', 'bi-plus-square', __('admin.qa_add_product'), 'products.manage'],
    ['admin.orders.create', 'bi-receipt', __('admin.qa_create_order'), 'orders.create'],
    ['admin.customers.create', 'bi-person-plus', __('admin.qa_add_customer'), 'customers.manage'],
    ['admin.invoices.create', 'bi-file-earmark-plus', __('admin.qa_create_invoice'), 'invoices.manage'],
    ['admin.production.index', 'bi-printer', __('admin.qa_production'), 'production.view'],
    ['admin.reports.index', 'bi-graph-up', __('admin.qa_reports'), 'reports.view'],
], fn ($q) => $router->has($q[0]) && can($q[3]));
$tiles = [];
if ($finance) {
    $tiles[] = ['bi-cash-stack', __('admin.kpi_revenue_month'), money($kpis['revenue_month']), $trend !== null ? __('admin.kpi_vs_prev', ['pct' => ($trend >= 0 ? '+' : '') . $trend]) : __('admin.kpi_orders_n', ['n' => $kpis['orders_month']])];
    $tiles[] = ['bi-calendar-day', __('admin.kpi_revenue_today'), money($kpis['revenue_today']), null];
    $tiles[] = ['bi-graph-up-arrow', __('admin.kpi_revenue_total'), money($kpis['revenue_total']), __('admin.kpi_avg_order', ['amount' => money($kpis['average_order'])])];
    $tiles[] = ['bi-hourglass-split', __('admin.kpi_outstanding'), money($kpis['outstanding']), __('admin.kpi_collected_month', ['amount' => money($kpis['collected_month'])])];
}
$tiles[] = ['bi-receipt', __('admin.kpi_orders_total'), (string) $kpis['orders_total'], null];
$tiles[] = ['bi-bell', __('admin.kpi_orders_pending'), (string) $kpis['orders_pending'], null];
$tiles[] = ['bi-printer', __('admin.kpi_orders_production'), (string) $kpis['orders_production'], null];
$tiles[] = ['bi-check2-circle', __('admin.kpi_orders_completed'), (string) $kpis['orders_completed'], null];
$tiles[] = ['bi-people', __('admin.kpi_customers'), (string) $kpis['customers'], __('admin.kpi_new_month', ['n' => $kpis['customers_month']])];
if ($finance) {
    $tiles[] = ['bi-wallet2', __('admin.kpi_expenses_month'), money($kpis['expenses_month']), __('admin.kpi_ht')];
    $tiles[] = ['bi-piggy-bank', __('admin.kpi_profit_month'), money($kpis['profit_month']['profit']), __('admin.kpi_estimated')];
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <div class="eyebrow"><?= e(format_date(date('Y-m-d'))) ?></div>
        <h2 class="mb-0"><?= e(__('admin.welcome', ['name' => (string) ($user['name'] ?? '')])) ?></h2>
    </div>
    <?php if ($quick): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($quick as [$r, $icon, $label]): ?>
                <a class="btn btn-sm btn-outline-bwa" href="<?= e(route($r)) ?>"><i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($pendingMigrations > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-database-exclamation me-2"></i><?= e(__('admin.update_pending', ['n' => $pendingMigrations])) ?></span>
        <a class="btn btn-sm btn-bwa" href="<?= e(route('admin.maintenance')) ?>"><?= e(__('admin.update_now')) ?></a>
    </div>
<?php endif; ?>
<?php if ($hasDemo && can('settings.manage')): ?>
    <div class="alert alert-info small d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-info-circle me-2"></i><?= e(__('admin.demo_active')) ?></span>
        <a class="btn-link-bwa" href="<?= e(route('admin.maintenance')) ?>"><?= e(__('admin.manage')) ?></a>
    </div>
<?php endif; ?>

<div class="kpi-grid mb-4">
    <?php foreach ($tiles as [$icon, $label, $value, $sub]): ?>
        <div class="kpi-tile">
            <div class="kpi-label"><i class="bi <?= e($icon) ?>"></i><?= e($label) ?></div>
            <div class="kpi-value"><?= e($value) ?></div>
            <?php if ($sub): ?><div class="kpi-sub"><?= e($sub) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($finance): ?>
    <p class="small text-muted-bwa mt-n2 mb-4"><i class="bi bi-calculator me-1"></i><?= e(__('admin.profit_formula', [
        'sales' => money($kpis['profit_month']['sales_ht']), 'cost' => money($kpis['profit_month']['production_cost']),
        'ship' => money($kpis['profit_month']['shipping_cost']), 'exp' => money($kpis['profit_month']['expenses']),
    ])) ?></p>
<?php endif; ?>

<script type="application/json" id="dashboard-data"><?= \App\Services\SeoService::encode($charts) ?></script>
<div class="row g-4 mb-4">
    <?php
    $chartCards = [];
    if (isset($charts['monthlySales'])) { $chartCards[] = ['monthlySales', __('admin.chart_monthly_sales'), 'col-xl-8', 'bar']; }
    $chartCards[] = ['ordersByStatus', __('admin.chart_orders_by_status'), isset($charts['monthlySales']) ? 'col-xl-4' : 'col-xl-6', 'hbar'];
    if (isset($charts['monthlyProfit'])) { $chartCards[] = ['monthlyProfit', __('admin.chart_monthly_profit'), 'col-xl-6', 'bar']; }
    if (isset($charts['revenueByCategory'])) { $chartCards[] = ['revenueByCategory', __('admin.chart_revenue_category'), 'col-xl-6', 'hbar']; }
    $chartCards[] = ['bestSellers', __('admin.chart_best_sellers'), 'col-xl-6', 'hbar'];
    ?>
    <?php foreach ($chartCards as [$key, $title, $col, $type]): ?>
        <div class="col-12 <?= e($col) ?>">
            <div class="admin-card h-100">
                <div class="card-header"><h2><?= e($title) ?></h2>
                    <?php if (in_array($key, ['revenueByCategory', 'bestSellers'], true)): ?><span class="small text-muted-bwa"><?= e(__('admin.last_90_days')) ?></span><?php endif; ?>
                    <?php if (in_array($key, ['monthlySales', 'monthlyProfit'], true)): ?><span class="small text-muted-bwa"><?= e(__('admin.last_12_months')) ?></span><?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="chart-box<?= $type === 'hbar' ? ' is-hbar' : '' ?>"><canvas data-chart="<?= e($key) ?>" data-type="<?= e($type) ?>" role="img" aria-label="<?= e($title) ?>"></canvas></div>
                    <details class="chart-table mt-2"><summary><?= e(__('admin.view_table')) ?></summary><div data-chart-table="<?= e($key) ?>"></div></details>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <?php if ($latestOrders !== [] || can('orders.view')): ?>
        <div class="col-xl-8">
            <div class="admin-card h-100">
                <div class="card-header"><h2><i class="bi bi-receipt me-2 text-gold"></i><?= e(__('admin.latest_orders')) ?></h2>
                    <?php if ($router->has('admin.orders.index')): ?><a class="btn-link-bwa" href="<?= e(route('admin.orders.index')) ?>"><?= e(__('admin.view_all')) ?></a><?php endif; ?>
                </div>
                <?php if ($latestOrders === []): ?>
                    <div class="empty-state"><i class="bi bi-receipt"></i><?= e(__('admin.no_orders_yet')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bwa">
                            <thead><tr><th><?= e(__('account.order')) ?></th><th><?= e(__('admin.customer')) ?></th><th><?= e(__('admin.status')) ?></th><th><?= e(__('checkout.payment')) ?></th><th class="text-end"><?= e(__('account.total')) ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($latestOrders as $o): ?>
                                <tr>
                                    <td class="fw-semibold text-nowrap"><?php if ($router->has('admin.orders.show')): ?><a href="<?= e(route('admin.orders.show', ['number' => $o['order_number']])) ?>"><?= e($o['order_number']) ?></a><?php else: ?><?= e($o['order_number']) ?><?php endif; ?><div class="small text-muted-bwa fw-normal"><?= e(format_date($o['placed_at'], true)) ?></div></td>
                                    <td><?= e($o['customer_name']) ?></td>
                                    <td><span class="badge-status <?= in_array($o['status'], ['cancelled', 'returned'], true) ? 'is-danger' : ($o['status'] === 'delivered' ? 'is-success' : '') ?>"><?= e(\App\Services\Sales\OrderStatus::label((string) $o['status'])) ?></span></td>
                                    <td class="small"><?= e(__('account.payment_' . $o['payment_status'])) ?></td>
                                    <td class="text-end text-nowrap"><?= e(money((string) $o['grand_total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="col-xl-4">
        <?php if ($lowStock !== []): ?>
            <div class="admin-card mb-4">
                <div class="card-header"><h2><i class="bi bi-exclamation-triangle me-2 text-gold"></i><?= e(__('admin.low_stock')) ?></h2></div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($lowStock as $p): ?>
                            <li class="d-flex justify-content-between px-3 py-2 border-bottom small">
                                <a href="<?= e($router->has('admin.products.edit') ? route('admin.products.edit', ['id' => $p['id']]) : '#') ?>"><?= e($p['title']) ?></a>
                                <span class="badge-status <?= (int) $p['stock_quantity'] <= 0 ? 'is-danger' : '' ?>"><?= (int) $p['stock_quantity'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        <?php $todo = array_filter($setupChecklist, fn ($i) => !$i['done']); ?>
        <?php if ($todo !== [] && can('settings.manage')): ?>
            <div class="admin-card">
                <div class="card-header"><h2><i class="bi bi-list-check me-2 text-gold"></i><?= e(__('admin.setup_checklist')) ?></h2><span class="small text-muted-bwa"><?= count($setupChecklist) - count($todo) ?>/<?= count($setupChecklist) ?></span></div>
                <div class="card-body">
                    <?php foreach ($setupChecklist as $item): ?>
                        <div class="checklist-item">
                            <i class="bi <?= $item['done'] ? 'bi-check-circle-fill status-ok' : 'bi-circle status-warn' ?>"></i>
                            <span class="flex-grow-1 small"><?= e($item['label']) ?></span>
                            <?php if (!$item['done'] && $item['url']): ?><a class="btn-link-bwa" href="<?= e($item['url']) ?>"><?= e(__('admin.configure')) ?></a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($recentActivity !== []): ?>
        <div class="col-12">
            <div class="admin-card">
                <div class="card-header"><h2><i class="bi bi-clock-history me-2 text-gold"></i><?= e(__('admin.recent_activity')) ?></h2><a class="btn-link-bwa" href="<?= e(route('admin.logs.index')) ?>"><?= e(__('admin.view_all')) ?></a></div>
                <div class="table-responsive">
                    <table class="table table-bwa">
                        <tbody>
                        <?php foreach ($recentActivity as $log): ?>
                            <tr><td class="text-nowrap small"><?= e(format_date($log['created_at'], true)) ?></td><td class="small"><?= e($log['user_name'] ?? __('admin.actor_' . $log['actor_type'])) ?></td><td><code class="small"><?= e($log['action']) ?></code></td><td class="small text-muted-bwa"><?= e($log['description']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php $this->start('scripts'); ?>
<script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset('js/dashboard.js')) ?>"></script>
<?php $this->stop(); ?>
