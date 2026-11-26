<?php
use App\Helpers\Money;

$this->layout('layouts/admin', ['title' => __('admin.nav_finance_overview')]);
$toInclusive = date('Y-m-d', strtotime($period['to'] . ' -1 day'));
$cats = fn ($c) => __('finance.ec_' . $c);
?>
<form method="get" class="filter-bar mb-3">
    <select class="form-select" name="period" aria-label="<?= e(__('finance.period')) ?>">
        <?php foreach (['month', 'prev_month', 'quarter', 'year', 'custom'] as $k): ?><option value="<?= $k ?>" <?= $key === $k ? 'selected' : '' ?>><?= e(__('finance.p_' . $k)) ?></option><?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e($period['from']) ?>" aria-label="<?= e(__('orders.from')) ?>">
    <input class="form-control" type="date" name="to" value="<?= e($toInclusive) ?>" aria-label="<?= e(__('orders.to')) ?>">
    <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
    <span class="small text-muted-bwa"><?= e(format_date($period['from'])) ?> → <?= e(format_date($toInclusive)) ?></span>
</form>
<div class="kpi-grid mb-4">
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-bag"></i><?= e(__('finance.sales')) ?></div><div class="kpi-value"><?= e(money($s['sales'])) ?></div><div class="kpi-sub"><?= e(__('finance.sales_sub', ['n' => $s['orders'], 'ht' => money($s['sales_ht'])])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-file-earmark-check"></i><?= e(__('finance.invoiced_net')) ?></div><div class="kpi-value"><?= e(money($s['invoiced'])) ?></div><div class="kpi-sub"><?= e(__('finance.invoiced_sub', ['i' => $s['invoices'], 'c' => $s['credit_notes']])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-arrow-down-circle"></i><?= e(__('finance.cash_in')) ?></div><div class="kpi-value"><?= e(money($s['cash_in'])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-arrow-up-circle"></i><?= e(__('finance.cash_out')) ?></div><div class="kpi-value"><?= e(money($s['cash_out'])) ?></div><div class="kpi-sub"><?= e(__('finance.cash_out_sub')) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-cash-stack"></i><?= e(__('finance.net_cash')) ?></div><div class="kpi-value <?= Money::toCents($s['net_cash']) < 0 ? 'text-danger' : '' ?>"><?= e(money($s['net_cash'])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-piggy-bank"></i><?= e(__('finance.profit')) ?></div><div class="kpi-value"><?= e(money($s['profit']['profit'])) ?></div><div class="kpi-sub"><?= e(__('admin.kpi_estimated')) ?></div></div>
</div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card mb-4">
            <div class="card-header"><h2><?= e(__('finance.chart_net_cash')) ?></h2><span class="small text-muted-bwa"><?= e(__('admin.last_12_months')) ?></span></div>
            <div class="card-body">
                <div class="chart-box"><canvas data-chart="netCash" data-type="bar" role="img" aria-label="<?= e(__('finance.chart_net_cash')) ?>"></canvas></div>
                <details class="chart-table mt-2"><summary><?= e(__('admin.view_table')) ?></summary><div data-chart-table="netCash"></div></details>
            </div>
        </div>
        <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('finance.vat_position')) ?></h2></div><div class="card-body">
            <div class="stat-line"><span><?= e(__('finance.vat_collected')) ?></span><span><?= e(money($s['vat_collected'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('finance.vat_deductible')) ?></span><span>−<?= e(money($s['vat_deductible'])) ?></span></div>
            <div class="stat-line fw-bold"><span><?= e(Money::toCents($s['vat_due']) >= 0 ? __('finance.vat_due') : __('finance.vat_credit')) ?></span><span><?= e(money(ltrim((string) $s['vat_due'], '-'))) ?></span></div>
            <p class="small text-muted-bwa mt-2 mb-0"><?= e(__('finance.vat_help')) ?></p>
        </div></div>
        <div class="admin-card"><div class="card-header"><h2><?= e(__('finance.overdue_invoices')) ?></h2></div>
            <?php if ($receivables['overdue'] === []): ?><div class="card-body small text-muted-bwa"><?= e(__('finance.no_overdue')) ?></div>
            <?php else: ?><div class="table-responsive"><table class="table table-bwa"><tbody>
                <?php foreach ($receivables['overdue'] as $i): ?><tr><td><a href="<?= e(route('admin.invoices.show', ['id' => $i['id']])) ?>"><?= e((string) $i['invoice_number']) ?></a></td><td><?= e($i['customer_company'] ?: $i['customer_name']) ?></td><td class="small text-danger"><?= e(__('finance.days_late', ['n' => $i['days']])) ?></td><td class="text-end text-nowrap"><?= e(money($i['left'])) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('finance.position')) ?></h2></div><div class="card-body">
            <div class="stat-line"><span><?= e(__('finance.receivable_orders')) ?></span><span><?= e(money($receivables['orders'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('finance.receivable_invoices')) ?></span><span><?= e(money($receivables['invoices'])) ?></span></div>
            <div class="stat-line fw-bold"><span><?= e(__('finance.receivable_total')) ?></span><span><?= e(money($receivables['total'])) ?></span></div>
            <div class="stat-line mt-2"><span><?= e(__('finance.payables')) ?></span><span><?= e(money($payables)) ?></span></div>
        </div></div>
        <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('finance.cash_by_method')) ?></h2></div><div class="card-body">
            <?php foreach ($s['by_method'] as $m): ?><div class="stat-line small"><span><?= e($m['name']) ?> (<?= (int) $m['n'] ?>)</span><span><?= e(money($m['total'])) ?></span></div><?php endforeach; ?>
            <?php if ($s['by_method'] === []): ?><p class="small text-muted-bwa mb-0"><?= e(__('orders.no_payment')) ?></p><?php endif; ?>
        </div></div>
        <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('finance.by_category')) ?></h2><?php if (can('expenses.manage')): ?><a class="small" href="<?= e(route('admin.expenses.index')) ?>"><?= e(__('admin.manage')) ?></a><?php endif; ?></div><div class="card-body">
            <?php foreach ($s['expenses_by_category'] as $c): ?><div class="stat-line small"><span><?= e($cats($c['category'])) ?> (<?= (int) $c['n'] ?>)</span><span><?= e(money($c['ttc'])) ?></span></div><?php endforeach; ?>
            <?php if ($s['expenses_by_category'] === []): ?><p class="small text-muted-bwa mb-0"><?= e(__('finance.no_expenses')) ?></p><?php endif; ?>
        </div></div>
        <div class="admin-card"><div class="card-header"><h2><?= e(__('finance.profit_detail')) ?></h2></div><div class="card-body small">
            <div class="stat-line"><span><?= e(__('finance.sales_ht')) ?></span><span><?= e(money($s['profit']['sales_ht'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('orders.cost_estimate')) ?></span><span>−<?= e(money($s['profit']['production_cost'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('orders.real_cost')) ?></span><span>−<?= e(money($s['profit']['shipping_cost'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('finance.expenses_ht')) ?></span><span>−<?= e(money($s['profit']['expenses'])) ?></span></div>
            <div class="stat-line fw-bold"><span><?= e(__('finance.profit')) ?></span><span><?= e(money($s['profit']['profit'])) ?></span></div>
        </div></div>
    </div>
</div>
<script type="application/json" id="dashboard-data"><?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php $this->start('scripts'); ?>
<script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset('js/dashboard.js')) ?>"></script>
<?php $this->stop(); ?>
