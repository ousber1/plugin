<?php
use App\Services\Operations\ProductionService;

$this->layout('layouts/admin', ['title' => __('admin.nav_production')]);
$manage = can('production.manage');
$open = array_sum(array_map('count', $board));
$late = 0;
foreach ($board as $jobs) {
    foreach ($jobs as $j) {
        $late += $j['deadline_at'] !== null && strtotime($j['deadline_at']) < time() ? 1 : 0;
    }
}
?>
<div class="kpi-grid mb-3">
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-printer"></i><?= e(__('production.open_jobs')) ?></div><div class="kpi-value"><?= $open ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-alarm"></i><?= e(__('production.late_jobs')) ?></div><div class="kpi-value<?= $late > 0 ? ' text-danger' : '' ?>"><?= $late ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-check2-all"></i><?= e(__('production.done_today')) ?></div><div class="kpi-value"><?= (int) $done ?></div></div>
</div>
<?php if ($waiting !== [] && $manage): ?>
    <div class="alert alert-warning">
        <strong><?= e(__('production.waiting_jobs')) ?></strong>
        <?php foreach ($waiting as $w): ?>
            <form method="post" action="<?= e(route('admin.production.create_jobs', ['orderId' => $w['id']])) ?>" class="d-inline-block ms-2"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><?= e($w['order_number']) ?> — <?= e(__('production.create_jobs')) ?></button></form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<form method="get" class="filter-bar mb-3" role="search">
    <select class="form-select" name="priority" aria-label="<?= e(__('production.priority')) ?>"><option value=""><?= e(__('production.all_priorities')) ?></option><?php foreach (ProductionService::PRIORITIES as $p): ?><option value="<?= $p ?>" <?= $f['priority'] === $p ? 'selected' : '' ?>><?= e(__('production.priority_' . $p)) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="user" aria-label="<?= e(__('production.operator')) ?>"><option value=""><?= e(__('production.all_operators')) ?></option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $f['user'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="machine" aria-label="<?= e(__('production.machine')) ?>"><option value=""><?= e(__('production.all_machines')) ?></option><?php foreach ($machines as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $f['machine'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="late" value="1" <?= $f['late'] ? 'checked' : '' ?>> <?= e(__('production.only_late')) ?></label>
    <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
</form>
<div class="prod-board" role="list">
    <?php foreach ($board as $stage => $jobs): if ($stage === 'receive' && $jobs === []) { continue; } ?>
        <section class="prod-col" role="listitem" aria-label="<?= e(__('production.stage_' . $stage)) ?>">
            <header class="prod-col-head"><span><?= e(__('production.stage_' . $stage)) ?></span><span class="badge text-bg-light border"><?= count($jobs) ?></span></header>
            <?php foreach ($jobs as $j): $isLate = $j['deadline_at'] !== null && strtotime($j['deadline_at']) < time(); ?>
                <article class="prod-card<?= $j['status'] === 'on_hold' ? ' is-hold' : '' ?><?= in_array($j['priority'], ['urgent', 'high'], true) ? ' is-' . $j['priority'] : '' ?>">
                    <div class="d-flex justify-content-between gap-2">
                        <a class="fw-semibold small" href="<?= e(route('admin.production.show', ['id' => $j['id']])) ?>"><?= e($j['job_number']) ?></a>
                        <?php if ($j['priority'] !== 'normal'): ?><span class="badge-status <?= status_class($j['priority']) ?>"><?= e(__('production.priority_' . $j['priority'])) ?></span><?php endif; ?>
                    </div>
                    <div class="small mt-1"><?= e((string) $j['title']) ?> <span class="text-muted-bwa">×<?= (int) $j['quantity'] ?></span></div>
                    <div class="small text-muted-bwa"><?= $j['width_cm'] ? e(cm($j['width_cm']) . '×' . cm($j['height_cm']) . ' cm · ') : '' ?><?= e((string) $j['material_name']) ?><?= $j['frame_name'] ? ' · ' . e($j['frame_name']) : '' ?></div>
                    <div class="small text-muted-bwa"><?= e($j['order_number']) ?> · <?= e($j['customer_name']) ?></div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mt-2 small">
                        <span class="<?= $isLate ? 'text-danger fw-semibold' : 'text-muted-bwa' ?>"><i class="bi bi-alarm me-1"></i><?= $j['deadline_at'] ? e(format_date($j['deadline_at'])) : '—' ?></span>
                        <span class="text-muted-bwa"><?= $j['assignee'] ? '<i class="bi bi-person me-1"></i>' . e($j['assignee']) : '' ?></span>
                    </div>
                    <?php if ($j['status'] === 'on_hold'): ?><div class="small text-danger mt-1"><i class="bi bi-pause-circle me-1"></i><?= e(__('production.job_on_hold')) ?></div>
                    <?php elseif ($manage && $j['task_id'] && $stage !== 'ship'): ?>
                        <form method="post" action="<?= e(route('admin.production.task', ['id' => $j['id'], 'taskId' => $j['task_id']])) ?>" class="mt-2">
                            <?= csrf_field() ?><input type="hidden" name="back" value="board">
                            <?php if ($j['task_status'] === 'in_progress'): ?>
                                <button class="btn btn-sm btn-bwa w-100" type="submit" name="action" value="done"><i class="bi bi-check2 me-1"></i><?= e(__('production.finish_stage')) ?></button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-bwa w-100" type="submit" name="action" value="start"><i class="bi bi-play me-1"></i><?= e(__('production.start_stage')) ?></button>
                            <?php endif; ?>
                        </form>
                    <?php elseif ($stage === 'ship'): ?>
                        <a class="btn btn-sm btn-outline-bwa w-100 mt-2" href="<?= e(route('admin.orders.show', ['id' => $j['order_id']])) ?>#shipping"><i class="bi bi-truck me-1"></i><?= e(__('production.to_shipping')) ?></a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($jobs === []): ?><p class="small text-muted-bwa px-1"><?= e(__('production.empty_col')) ?></p><?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
