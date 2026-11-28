<?php $this->layout('layouts/admin', ['title' => __('admin.nav_logs')]) ?>
<div class="admin-card">
    <div class="card-header">
        <form class="d-flex flex-wrap gap-2" method="get">
            <input type="search" class="form-control form-control-sm" style="max-width:260px" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('common.search')) ?>">
            <select class="form-select form-select-sm" style="max-width:180px" name="actor">
                <option value=""><?= e(__('admin.all_actors')) ?></option>
                <?php foreach (['user', 'customer', 'system'] as $a): ?>
                    <option value="<?= $a ?>" <?= $actor === $a ? 'selected' : '' ?>><?= e(__('admin.actor_' . $a)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-bwa" type="submit"><?= e(__('common.filter')) ?></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-bwa">
            <thead><tr><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.actor')) ?></th><th><?= e(__('admin.action')) ?></th><th><?= e(__('admin.entity')) ?></th><th><?= e(__('admin.details')) ?></th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs->items as $log): ?>
                <tr>
                    <td class="text-nowrap small"><?= e(format_date($log['created_at'], true)) ?></td>
                    <td class="small"><?= e($names[$log['actor_type']][(int) $log['actor_id']] ?? __('admin.actor_' . $log['actor_type'])) ?></td>
                    <td><code class="small"><?= e($log['action']) ?></code></td>
                    <td class="small"><?= e($log['entity_type'] ? $log['entity_type'] . ($log['entity_id'] ? ' #' . $log['entity_id'] : '') : '—') ?></td>
                    <td class="small text-muted-bwa"><?= e($log['description']) ?></td>
                    <td class="small text-muted-bwa"><?= e($log['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($logs->items === []): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-journal"></i><?= e(__('common.no_results')) ?></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($logs->hasPages()): ?><div class="p-3 d-flex justify-content-between align-items-center"><span class="small text-muted-bwa"><?= $logs->from() ?>–<?= $logs->to() ?> / <?= $logs->total ?></span><?= $logs->links() ?></div><?php endif; ?>
</div>
