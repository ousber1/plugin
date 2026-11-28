<?php $this->layout('layouts/admin', ['title' => __('admin.nav_backups')]); ?>
<div class="row g-4">
    <div class="col-xl-7">
        <div class="admin-card mb-4">
            <div class="card-header"><h2><i class="bi bi-database-down me-2 text-gold"></i><?= e(__('backups.title')) ?></h2>
                <form method="post" action="<?= e(route('admin.backups.create')) ?>" data-once><?= csrf_field() ?><button class="btn btn-sm btn-bwa" type="submit" <?= $gz ? '' : 'disabled' ?> data-loading="<?= e(__('backups.creating')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('backups.create')) ?></button></form>
            </div>
            <?php if (!$gz): ?><div class="card-body"><div class="alert alert-warning mb-0"><?= e(__('backups.no_zlib')) ?></div></div><?php endif; ?>
            <div class="table-responsive"><table class="table table-bwa">
                <thead><tr><th><?= e(__('backups.file')) ?></th><th><?= e(__('admin.date')) ?></th><th class="text-end"><?= e(__('backups.size')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td class="small text-break"><code><?= e($b['name']) ?></code></td>
                        <td class="small text-nowrap"><?= e(date('d/m/Y H:i', $b['time'])) ?></td>
                        <td class="text-end small text-nowrap"><?= e(number_format($b['size'] / 1048576, 2, ',', ' ')) ?> Mo</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.backups.download', ['name' => $b['name']])) ?>"><i class="bi bi-download"></i></a>
                            <form class="d-inline" method="post" action="<?= e(route('admin.backups.destroy', ['name' => $b['name']])) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger" type="submit" aria-label="<?= e(__('account.delete_now')) ?>"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($backups === []): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-database"></i><?= e(__('backups.none')) ?></div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <div class="card-body border-top small text-muted-bwa">
                <p class="mb-1"><?= e(__('backups.restore_help')) ?></p>
                <p class="mb-0"><?= e(__('backups.files_help')) ?></p>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <form method="post" action="<?= e(route('admin.backups.settings')) ?>" class="admin-card" novalidate>
            <?= csrf_field() ?>
            <div class="card-header"><h2><?= e(__('backups.retention')) ?></h2></div>
            <div class="card-body">
                <?= $this->insert('partials/field', ['name' => 'backup_auto', 'label' => __('backups.auto'), 'type' => 'checkbox', 'value' => $settings['backup_auto'] ? 1 : 0]) ?>
                <?= $this->insert('partials/field', ['name' => 'backup_keep', 'label' => __('backups.keep'), 'type' => 'number', 'value' => $settings['backup_keep'], 'attrs' => 'min="1" max="365"']) ?>
                <div class="form-section">
                    <h3><?= e(__('backups.retention_data')) ?></h3>
                    <?php foreach (['retention_unordered_days', 'retention_ordered_days', 'retention_logs_days', 'retention_notifications_days', 'retention_carts_days'] as $k): ?>
                        <?= $this->insert('partials/field', ['name' => $k, 'label' => __('backups.' . $k), 'type' => 'number', 'value' => $settings[$k], 'help' => __('backups.' . $k . '_help'), 'attrs' => 'min="7" max="3650"']) ?>
                    <?php endforeach; ?>
                    <p class="small text-muted-bwa"><?= e(__('backups.kept_forever')) ?></p>
                </div>
                <button class="btn btn-bwa w-100" type="submit"><?= e(__('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>
