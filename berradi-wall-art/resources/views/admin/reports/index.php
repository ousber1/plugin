<?php
use App\Services\Reports\ReportService;

$this->layout('layouts/admin', ['title' => __('admin.nav_reports')]);
$toInclusive = date('Y-m-d', strtotime($period['to'] . ' -1 day'));
$qs = fn (array $over) => '?' . http_build_query(array_filter(array_merge(['report' => $report, 'period' => $key, 'from' => $period['from'], 'to' => $toInclusive, 'group' => $group], $over), fn ($v) => $v !== null && $v !== ''));
$fmt = function (mixed $v, string $type): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return match ($type) {
        'money' => money($v),
        'percent' => rtrim(rtrim(number_format((float) $v, 1, ',', ' '), '0'), ',') . ' %',
        'number' => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ','),
        'int' => number_format((int) $v, 0, ',', ' '),
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v) ? format_date((string) $v, strlen((string) $v) > 10) : (string) $v,
        default => (string) $v,
    };
};
$noPeriod = $report === 'catalogue';
?>
<ul class="nav settings-tabs border-bottom mb-3 flex-nowrap overflow-auto">
    <?php foreach (ReportService::REPORTS as $r): ?><li class="nav-item"><a class="nav-link text-nowrap<?= $report === $r ? ' active' : '' ?>" href="<?= e($qs(['report' => $r])) ?>"><?= e(__('reports.r_' . $r)) ?></a></li><?php endforeach; ?>
</ul>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <?php if (!$noPeriod): ?>
        <form method="get" class="filter-bar">
            <input type="hidden" name="report" value="<?= e($report) ?>">
            <select class="form-select" name="period" aria-label="<?= e(__('finance.period')) ?>"><?php foreach (['month', 'prev_month', 'quarter', 'year', 'custom'] as $k): ?><option value="<?= $k ?>" <?= $key === $k ? 'selected' : '' ?>><?= e(__('finance.p_' . $k)) ?></option><?php endforeach; ?></select>
            <input class="form-control" type="date" name="from" value="<?= e(substr($period['from'], 0, 10)) ?>" aria-label="<?= e(__('orders.from')) ?>">
            <input class="form-control" type="date" name="to" value="<?= e($toInclusive) ?>" aria-label="<?= e(__('orders.to')) ?>">
            <?php if ($report === 'sales'): ?><select class="form-select" name="group" aria-label="<?= e(__('reports.group')) ?>"><?php foreach (['day', 'week', 'month'] as $g): ?><option value="<?= $g ?>" <?= $group === $g ? 'selected' : '' ?>><?= e(__('reports.g_' . $g)) ?></option><?php endforeach; ?></select><?php endif; ?>
            <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
        </form>
    <?php else: ?><p class="small text-muted-bwa mb-0"><?= e(__('reports.catalogue_help')) ?></p><?php endif; ?>
    <a class="btn btn-bwa" href="<?= e($qs(['export' => 'csv'])) ?>"><i class="bi bi-filetype-csv me-1"></i><?= e(__('reports.export_csv')) ?></a>
</div>
<?php if ($data['totals'] !== []): ?>
    <div class="kpi-grid mb-3">
        <?php foreach ($data['totals'] as $k => $v):
            $col = array_values(array_filter($data['columns'], fn ($c) => $c['key'] === $k))[0] ?? null;
            $type = $col['type'] ?? (is_string($v) && str_contains($v, '.') ? 'money' : 'number'); ?>
            <div class="kpi-tile"><div class="kpi-label"><?= e($col['label'] ?? __('reports.t_' . $k)) ?></div><div class="kpi-value" style="font-size:1.25rem"><?= e($fmt($v, $type === 'percent' ? 'number' : $type)) ?></div></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($chart !== null): ?>
    <div class="admin-card mb-3"><div class="card-body">
        <div class="chart-box<?= $report === 'sales' ? '' : ' is-hbar' ?>"><canvas data-chart="report" data-type="<?= $report === 'sales' ? 'bar' : 'hbar' ?>" <?= $chart['money'] ? 'data-money="1"' : '' ?> role="img" aria-label="<?= e(__('reports.r_' . $report)) ?>"></canvas></div>
    </div></div>
<?php endif; ?>
<div class="admin-card">
    <div class="table-responsive"><table class="table table-bwa">
        <thead><tr><?php foreach ($data['columns'] as $c): ?><th class="<?= in_array($c['type'], ['money', 'int', 'number', 'percent'], true) ? 'text-end' : '' ?>"><?= e($c['label']) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
            <tr><?php foreach ($data['columns'] as $c): ?><td class="<?= in_array($c['type'], ['money', 'int', 'number', 'percent'], true) ? 'text-end text-nowrap' : '' ?>">
                <?php if ($report === 'catalogue' && $c['key'] === 'product' && can('products.manage')): ?><a href="<?= e(route('admin.products.edit', ['id' => $r['id']])) ?>"><?= e((string) $r['product']) ?></a><?php else: ?><?= e($fmt($r[$c['key']] ?? null, $c['type'])) ?><?php endif; ?>
            </td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if ($data['rows'] === []): ?><tr><td colspan="<?= count($data['columns']) ?>"><div class="empty-state"><i class="bi bi-graph-up"></i><?= e($report === 'catalogue' ? __('reports.catalogue_ok') : __('reports.empty')) ?></div></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php if ($chart !== null): ?>
    <script type="application/json" id="dashboard-data"><?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    <?php $this->start('scripts'); ?>
    <script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
    <script src="<?= e(asset('js/dashboard.js')) ?>"></script>
    <?php $this->stop(); ?>
<?php endif; ?>
