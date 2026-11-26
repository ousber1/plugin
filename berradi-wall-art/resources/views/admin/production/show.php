<?php
use App\Services\Operations\ProductionService;
use App\Services\Sales\OrderStatus;

$this->layout('layouts/admin', ['title' => $j['job_number']]);
$manage = can('production.manage');
$closed = in_array($j['status'], ['completed', 'cancelled'], true);
$opt = json_decode((string) $j['options'], true) ?: [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="small text-muted-bwa" href="<?= e(route('admin.production.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_production')) ?></a>
    <div class="page-actions">
        <span class="badge-status <?= status_class($j['status']) ?>"><?= e(__('production.job_' . $j['status'])) ?></span>
        <?php if (can('orders.view') || can('orders.manage')): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.orders.show', ['id' => $j['order_id']])) ?>"><?= e($j['order_number']) ?> · <?= e(OrderStatus::label($j['order_status'])) ?></a><?php endif; ?>
        <?php if (can('orders.view') || can('orders.manage')): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.orders.print', ['id' => $j['order_id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i></a><?php endif; ?>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card mb-4">
            <div class="card-header"><h2><?= e($j['title'] ?? '—') ?> <span class="text-muted-bwa fw-normal">×<?= (int) $j['quantity'] ?></span></h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-4">
                        <?php if ($j['custom_design_id'] && (can('orders.view') || can('orders.manage'))): ?>
                            <img class="img-fluid rounded border" src="<?= e(route('admin.orders.item_preview', ['id' => $j['order_id'], 'itemId' => $j['order_item_id']])) ?>" alt="">
                        <?php elseif (!$j['custom_design_id'] && $j['preview_path']): ?>
                            <img class="img-fluid rounded border" src="<?= e(upload_url($j['preview_path'])) ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-8 small">
                        <div class="stat-line"><span><?= e(__('artwork.overall')) ?></span><span><?= $j['width_cm'] ? e(cm($j['width_cm']) . ' × ' . cm($j['height_cm']) . ' cm') : '—' ?></span></div>
                        <?php if (!empty($opt['panel_width_cm'])): ?><div class="stat-line"><span><?= e(__('artwork.panel_size')) ?></span><span><?= e(cm($opt['panel_width_cm']) . ' × ' . cm($opt['panel_height_cm']) . ' cm') ?> × <?= (int) ($opt['panels'] ?? 1) ?></span></div><?php endif; ?>
                        <div class="stat-line"><span><?= e(__('builder.support')) ?></span><span><?= e((string) $j['material_name']) ?></span></div>
                        <div class="stat-line"><span><?= e(__('builder.frame')) ?></span><span><?= e((string) ($j['frame_name'] ?: __('builder.no_frame'))) ?></span></div>
                        <?php if (!empty($opt['border']['name'])): ?><div class="stat-line"><span><?= e(__('builder.border')) ?></span><span><?= e($opt['border']['name']) ?></span></div><?php endif; ?>
                        <?php if (!empty($opt['finish']['name'])): ?><div class="stat-line"><span><?= e(__('builder.finish')) ?></span><span><?= e($opt['finish']['name']) ?></span></div><?php endif; ?>
                        <?php if ($j['custom_design_id']): ?>
                            <div class="stat-line"><span><?= e(__('admin.nav_artwork')) ?></span><span><?= $artworkPending > 0 ? '<span class="text-danger">' . e(__('production.artwork_pending', ['n' => $artworkPending])) . '</span>' : '<span class="text-success">' . e(__('artwork.status_approved')) . '</span>' ?>
                                <?php if (can('artwork.view')): ?> · <a href="<?= e(route('admin.artwork.show', ['itemId' => $j['order_item_id']])) ?>"><?= e(__('artwork.review')) ?></a><?php endif; ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($j['customer_notes']): ?><div class="alert alert-light border small mt-3 mb-0"><strong><?= e(__('orders.customer_note')) ?> :</strong> <?= e($j['customer_notes']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="card-header"><h2><?= e(__('production.stages')) ?></h2></div>
            <div class="card-body">
                <ul class="task-list">
                    <?php foreach ($tasks as $i => $t):
                        $isCurrent = $current !== null && (int) $current['id'] === (int) $t['id'];
                        $dot = $t['status'] === 'done' ? 'is-done' : ($t['status'] === 'skipped' ? 'is-skipped' : ($isCurrent ? 'is-current' : '')); ?>
                        <li>
                            <span class="task-dot <?= $dot ?>"><?= $t['status'] === 'done' ? '<i class="bi bi-check2"></i>' : ($t['status'] === 'skipped' ? '<i class="bi bi-dash"></i>' : $i + 1) ?></span>
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <strong><?= e(__('production.stage_' . $t['stage'])) ?></strong>
                                    <span class="small text-muted-bwa"><?= e(__('production.task_' . $t['status'])) ?><?= $t['completed_at'] ? ' · ' . e(format_date($t['completed_at'], true)) : ($t['started_at'] ? ' · ' . e(__('production.since', ['date' => format_date($t['started_at'], true)])) : '') ?><?= $t['duration_minutes'] ? ' · ' . (int) $t['duration_minutes'] . ' min' : '' ?></span>
                                </div>
                                <?php if ($t['user_name'] || $t['machine_name']): ?><div class="small text-muted-bwa"><?= e((string) $t['user_name']) ?><?= $t['machine_name'] ? ' · ' . e($t['machine_name']) : '' ?></div><?php endif; ?>
                                <?php if ($t['notes']): ?><div class="small" style="white-space:pre-line"><?= e($t['notes']) ?></div><?php endif; ?>
                                <?php if ($manage && $isCurrent && !$closed && $j['status'] !== 'on_hold'): ?>
                                    <?php if ($t['stage'] === 'ship'): ?>
                                        <p class="small text-muted-bwa mt-1 mb-0"><?= e(__('production.ship_help')) ?></p>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(route('admin.production.task', ['id' => $j['id'], 'taskId' => $t['id']])) ?>" class="d-flex flex-wrap gap-2 mt-2">
                                            <?= csrf_field() ?>
                                            <input class="form-control form-control-sm" style="max-width:260px" name="notes" maxlength="500" placeholder="<?= e(__('production.note_ph')) ?>" aria-label="<?= e(__('production.note_ph')) ?>">
                                            <?php if ($t['status'] !== 'in_progress'): ?><button class="btn btn-sm btn-outline-bwa" type="submit" name="action" value="start"><i class="bi bi-play me-1"></i><?= e(__('production.start_stage')) ?></button><?php endif; ?>
                                            <button class="btn btn-sm btn-bwa" type="submit" name="action" value="done"><i class="bi bi-check2 me-1"></i><?= e(__('production.finish_stage')) ?></button>
                                            <?php if (in_array($t['stage'], ['review_quality', 'cut_finish', 'frame', 'package'], true)): ?><button class="btn btn-sm btn-link text-muted-bwa" type="submit" name="action" value="skip"><?= e(__('production.skip')) ?></button><?php endif; ?>
                                        </form>
                                        <?php if ($t['stage'] === 'print'): ?><p class="small text-muted-bwa mt-1 mb-0"><i class="bi bi-box-seam me-1"></i><?= e(__('production.print_consumes')) ?></p><?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="admin-card">
            <div class="card-header"><h2><?= e(__('production.materials')) ?></h2></div>
            <div class="card-body small">
                <?php if ($consumed !== []): ?>
                    <p class="fw-semibold mb-1"><?= e(__('production.consumed')) ?></p>
                    <?php foreach ($consumed as $c): ?><div class="stat-line"><span><?= e($c['name']) ?></span><span><?= e(rtrim(rtrim(ltrim((string) $c['quantity'], '-'), '0'), '.')) ?> <?= e($c['unit']) ?> · <?= e(money($c['total_cost'])) ?></span></div><?php endforeach; ?>
                <?php else: ?>
                    <p class="fw-semibold mb-1"><?= e(__('production.planned')) ?></p>
                    <?php foreach ($requirements['lines'] as $l): $m = $materialNames[$l['material_id']] ?? null; ?>
                        <div class="stat-line"><span><?= e($m['name'] ?? $l['label']) ?></span><span><?= e(rtrim(rtrim($l['quantity'], '0'), '.')) ?> <?= e($m['unit'] ?? '') ?><?php if ($m !== null && (float) $m['stock_quantity'] < (float) $l['quantity']): ?> <span class="text-danger">(<?= e(__('production.stock_short', ['n' => rtrim(rtrim((string) $m['stock_quantity'], '0'), '.')])) ?>)</span><?php endif; ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($requirements['lines'] === [] && $requirements['unlinked'] === []): ?><p class="text-muted-bwa mb-0"><?= e(__('production.no_requirements')) ?></p><?php endif; ?>
                <?php endif; ?>
                <?php if ($requirements['unlinked'] !== [] && $consumed === []): ?><p class="text-muted-bwa mt-2 mb-0"><i class="bi bi-info-circle me-1"></i><?= e(__('production.unlinked', ['list' => implode(', ', $requirements['unlinked'])])) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <form method="post" action="<?= e(route('admin.production.update', ['id' => $j['id']])) ?>" class="admin-card mb-4" novalidate>
            <?= csrf_field() ?>
            <div class="card-header"><h2><?= e(__('production.planning')) ?></h2></div>
            <div class="card-body">
                <?php $dis = $manage && !$closed ? '' : 'disabled'; ?>
                <?= $this->insert('partials/field', ['name' => 'priority', 'label' => __('production.priority'), 'type' => 'select', 'options' => array_combine(ProductionService::PRIORITIES, array_map(fn ($p) => __('production.priority_' . $p), ProductionService::PRIORITIES)), 'value' => $j['priority'], 'attrs' => $dis]) ?>
                <?= $this->insert('partials/field', ['name' => 'assigned_user_id', 'label' => __('production.operator'), 'type' => 'select', 'options' => ['' => '—'] + array_column($users, 'name', 'id'), 'value' => (string) $j['assigned_user_id'], 'attrs' => $dis]) ?>
                <?= $this->insert('partials/field', ['name' => 'machine_id', 'label' => __('production.machine'), 'type' => 'select', 'options' => ['' => '—'] + array_combine(array_column($machines, 'id'), array_map(fn ($m) => $m['name'] . ' (' . money($m['hourly_cost']) . '/h)', $machines)), 'value' => (string) $j['machine_id'], 'attrs' => $dis]) ?>
                <?= $this->insert('partials/field', ['name' => 'deadline_at', 'label' => __('production.deadline'), 'type' => 'datetime-local', 'value' => $j['deadline_at'] ? date('Y-m-d\TH:i', strtotime($j['deadline_at'])) : '', 'attrs' => $dis]) ?>
                <div class="row g-2">
                    <div class="col-6"><?= $this->insert('partials/field', ['name' => 'labor_cost', 'label' => __('production.labor_cost'), 'value' => $j['labor_cost'], 'attrs' => $dis . ' inputmode="decimal"']) ?></div>
                    <div class="col-6"><?= $this->insert('partials/field', ['name' => 'other_cost', 'label' => __('production.other_cost'), 'value' => $j['other_cost'], 'attrs' => $dis . ' inputmode="decimal"']) ?></div>
                </div>
                <?= $this->insert('partials/field', ['name' => 'notes', 'label' => __('production.job_notes'), 'type' => 'textarea', 'rows' => 3, 'value' => (string) $j['notes'], 'attrs' => $dis]) ?>
                <?php if ($manage && !$closed): ?>
                    <?= $this->insert('partials/field', ['name' => 'hold', 'label' => __('production.put_on_hold'), 'type' => 'checkbox', 'value' => $j['status'] === 'on_hold' ? 1 : 0]) ?>
                    <button class="btn btn-bwa w-100" type="submit"><?= e(__('common.save')) ?></button>
                <?php endif; ?>
            </div>
        </form>
        <div class="admin-card"><div class="card-header"><h2><?= e(__('production.costs')) ?></h2></div><div class="card-body small">
            <div class="stat-line"><span><?= e(__('production.material_cost')) ?></span><span><?= e(money($j['material_cost'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('production.machine_cost')) ?></span><span><?= e(money($j['machine_cost'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('production.labor_cost')) ?></span><span><?= e(money($j['labor_cost'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('production.other_cost')) ?></span><span><?= e(money($j['other_cost'])) ?></span></div>
            <div class="stat-line fw-bold"><span><?= e(__('admin.total')) ?></span><span><?= e(money($total)) ?></span></div>
            <p class="text-muted-bwa mt-2 mb-0"><?= e(__('production.costs_help')) ?></p>
        </div></div>
    </div>
</div>
