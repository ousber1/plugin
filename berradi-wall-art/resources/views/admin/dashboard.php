<?php $this->layout('layouts/admin', ['title' => __('admin.nav_dashboard')]) ?>
<?php $user = auth_user(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <div class="eyebrow"><?= e(format_date(date('Y-m-d'))) ?></div>
        <h2 class="mb-0"><?= e(__('admin.welcome', ['name' => (string) ($user['name'] ?? '')])) ?></h2>
    </div>
    <?php if (can('settings.manage')): ?>
        <a href="<?= e(route('admin.settings')) ?>" class="btn btn-outline-bwa btn-sm"><i class="bi bi-gear me-1"></i><?= e(__('admin.nav_settings')) ?></a>
    <?php endif; ?>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle me-2"></i><?= e(__('admin.phase1_notice')) ?>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h2><i class="bi bi-list-check me-2 text-gold"></i><?= e(__('admin.setup_checklist')) ?></h2></div>
            <div class="card-body">
                <?php foreach ($setupChecklist as $item): ?>
                    <div class="checklist-item">
                        <i class="bi <?= $item['done'] ? 'bi-check-circle-fill status-ok' : 'bi-circle status-warn' ?>"></i>
                        <span class="flex-grow-1"><?= e($item['label']) ?></span>
                        <?php if (!$item['done'] && $item['url'] && can('settings.manage')): ?>
                            <a class="btn-link-bwa" href="<?= e($item['url']) ?>"><?= e(__('admin.configure')) ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h2><i class="bi bi-cpu me-2 text-gold"></i><?= e(__('admin.system_status')) ?></h2></div>
            <div class="card-body p-0">
                <table class="table table-bwa">
                    <tbody>
                    <tr><td><?= e(__('admin.php_version')) ?></td><td class="text-end"><?= e($system['php']) ?></td></tr>
                    <tr><td><?= e(__('admin.db_version')) ?></td><td class="text-end"><?= e($system['db']) ?></td></tr>
                    <tr><td><?= e(__('admin.db_tables')) ?></td><td class="text-end"><?= (int) $system['tables'] ?> · <?= (int) $system['migrations'] ?> <?= e(__('admin.migrations')) ?></td></tr>
                    <tr><td><?= e(__('admin.active_staff')) ?></td><td class="text-end"><?= (int) $system['users'] ?></td></tr>
                    <tr><td><?= e(__('admin.categories')) ?></td><td class="text-end"><?= (int) $system['categories'] ?></td></tr>
                    <tr><td>GD (images)</td><td class="text-end"><?= $system['gd'] ? '<span class="status-ok">OK</span>' : '<span class="status-bad">' . e(__('admin.missing')) . '</span>' ?></td></tr>
                    <tr><td>Dompdf / PHPMailer</td><td class="text-end"><?= $system['vendor'] ? '<span class="status-ok">OK</span>' : '<span class="status-bad">' . e(__('admin.missing')) . '</span>' ?></td></tr>
                    <tr><td>HTTPS</td><td class="text-end"><?= $system['https'] ? '<span class="status-ok">OK</span>' : '<span class="status-warn">' . e(__('admin.not_active')) . '</span>' ?></td></tr>
                    <tr><td>APP_DEBUG</td><td class="text-end"><?= $system['debug'] ? '<span class="status-bad">ON</span>' : '<span class="status-ok">OFF</span>' ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="admin-card">
            <div class="card-header">
                <h2><i class="bi bi-clock-history me-2 text-gold"></i><?= e(__('admin.recent_activity')) ?></h2>
                <?php if (can('logs.view')): ?><a class="btn-link-bwa" href="<?= e(route('admin.logs.index')) ?>"><?= e(__('admin.view_all')) ?></a><?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($recentActivity === []): ?>
                    <div class="empty-state"><i class="bi bi-journal"></i><?= e(__('admin.no_activity')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bwa">
                            <thead><tr><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.actor')) ?></th><th><?= e(__('admin.action')) ?></th><th><?= e(__('admin.details')) ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($recentActivity as $log): ?>
                                <tr>
                                    <td class="text-nowrap small"><?= e(format_date($log['created_at'], true)) ?></td>
                                    <td><?= e($log['user_name'] ?? __('admin.actor_' . $log['actor_type'])) ?></td>
                                    <td><code><?= e($log['action']) ?></code></td>
                                    <td class="small text-muted-bwa"><?= e($log['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
