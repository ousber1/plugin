<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_maintenance')]);
$yes = fn (bool $ok, string $okText = '', string $badText = '') => $ok
    ? '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>' . e($okText ?: __('common.yes')) . '</span>'
    : '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>' . e($badText ?: __('common.no')) . '</span>';
$cronAge = $system['last_cron'] !== '' ? time() - (int) strtotime($system['last_cron']) : null;
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card mb-4"><div class="card-header"><h2><i class="bi bi-database-gear me-2 text-gold"></i><?= e(__('admin.db_updates')) ?></h2></div><div class="card-body">
            <div class="stat-line"><span><?= e(__('admin.migrations_ran')) ?></span><span><?= (int) $ran ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.migrations_pending')) ?></span><span><?= count($pending) ?></span></div>
            <?php if ($pending !== []): ?>
                <ul class="small mt-3 mb-3"><?php foreach ($pending as $p): ?><li><code><?= e((string) $p) ?></code></li><?php endforeach; ?></ul>
                <p class="small text-muted-bwa"><?= e(__('admin.update_backup_hint')) ?></p>
            <?php else: ?>
                <p class="small text-muted-bwa mt-3"><?= e(__('admin.db_up_to_date')) ?></p>
            <?php endif; ?>
            <form method="post" action="<?= e(route('admin.maintenance.update')) ?>" data-confirm="<?= e(__('admin.update_confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn <?= $pending !== [] ? 'btn-bwa' : 'btn-outline-bwa' ?>" type="submit"><i class="bi bi-arrow-repeat me-1"></i><?= e($pending !== [] ? __('admin.update_now') : __('admin.rerun_seeders')) ?></button>
            </form>
        </div></div>

        <div class="admin-card"><div class="card-header"><h2><i class="bi bi-images me-2 text-gold"></i><?= e(__('admin.demo_catalog')) ?></h2><?= $demo ? '<span class="badge text-bg-warning">' . e(__('admin.installed')) . '</span>' : '' ?></div><div class="card-body">
            <p class="small text-muted-bwa"><?= e(__('admin.demo_help')) ?></p>
            <form method="post" action="<?= e(route('admin.maintenance.demo')) ?>" data-confirm="<?= e($demo ? __('admin.demo_remove_confirm') : __('admin.demo_install_confirm')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="<?= $demo ? 'remove' : 'install' ?>">
                <button class="btn <?= $demo ? 'btn-outline-danger' : 'btn-outline-bwa' ?>" type="submit"><i class="bi <?= $demo ? 'bi-trash' : 'bi-download' ?> me-1"></i><?= e($demo ? __('admin.demo_remove') : __('admin.demo_install')) ?></button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card mb-4"><div class="card-header"><h2><i class="bi bi-cpu me-2 text-gold"></i><?= e(__('admin.system_info')) ?></h2></div><div class="card-body">
            <div class="stat-line"><span><?= e(__('admin.app_version')) ?></span><span><?= e((string) $system['app']) ?></span></div>
            <div class="stat-line"><span>PHP</span><span><?= e($system['php']) ?> <?= version_compare($system['php'], '8.2.0', '>=') ? '' : $yes(false, '', '8.2+') ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.database')) ?></span><span><?= e($system['db']) ?> · <?= (int) $system['tables'] ?> <?= e(__('admin.tables')) ?></span></div>
            <div class="stat-line"><span>memory_limit</span><span><?= e($system['memory_limit']) ?></span></div>
            <div class="stat-line"><span>upload_max_filesize / post_max_size</span><span><?= e($system['upload_max_filesize']) ?> / <?= e($system['post_max_size']) ?></span></div>
            <div class="stat-line"><span>GD</span><span><?= $yes($system['gd']) ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.composer_vendor')) ?></span><span><?= $yes($system['vendor'], __('admin.installed'), __('admin.vendor_missing')) ?></span></div>
            <div class="stat-line"><span>HTTPS</span><span><?= $yes($system['https']) ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.debug_mode')) ?></span><span><?= $system['debug'] ? '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>' . e(__('admin.debug_on')) . '</span>' : e(__('admin.debug_off')) ?></span></div>
        </div></div>
        <div class="admin-card"><div class="card-header"><h2><i class="bi bi-clock-history me-2 text-gold"></i><?= e(__('admin.cron_storage')) ?></h2></div><div class="card-body">
            <div class="stat-line"><span><?= e(__('admin.last_cron')) ?></span><span>
                <?php if ($cronAge === null): ?><span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i><?= e(__('admin.cron_never')) ?></span>
                <?php else: ?><?= e(format_date($system['last_cron'], true)) ?><?= $cronAge > 7200 ? ' <span class="text-danger small">(' . e(__('admin.cron_late')) . ')</span>' : '' ?><?php endif; ?>
            </span></div>
            <div class="stat-line"><span><?= e(__('admin.private_uploads')) ?></span><span><?= e($system['private_uploads']) ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.public_uploads')) ?></span><span><?= e($system['public_uploads']) ?></span></div>
            <p class="small text-muted-bwa mt-3 mb-1"><?= e(__('admin.cron_help')) ?></p>
            <code class="small d-block text-break">*/15 * * * * /usr/local/bin/php <?= e(base_path('bin/console')) ?> cron:run</code>
        </div></div>
    </div>
</div>
<div class="admin-card mt-4" id="security">
    <div class="card-header"><h2><i class="bi bi-shield-lock me-2 text-gold"></i><?= e(__('security.title')) ?></h2>
        <?php $fails = count(array_filter($security, fn ($c) => $c['state'] === 'fail')); $warns = count(array_filter($security, fn ($c) => $c['state'] === 'warn')); ?>
        <span class="small"><?= $fails ? '<span class="text-danger">' . e(__('security.n_fail', ['n' => $fails])) . '</span> · ' : '' ?><?= e(__('security.n_warn', ['n' => $warns])) ?></span></div>
    <div class="card-body">
        <?php foreach ($security as $c): ?>
            <div class="stat-line">
                <span><i class="bi <?= $c['state'] === 'ok' ? 'bi-check-circle-fill text-success' : ($c['state'] === 'warn' ? 'bi-exclamation-triangle-fill text-warning' : 'bi-x-circle-fill text-danger') ?> me-2"></i><?= e(__('security.' . $c['key'])) ?><?= $c['detail'] !== '' ? ' <code class="small">' . e($c['detail']) . '</code>' : '' ?></span>
                <?php if ($c['state'] !== 'ok'): ?><span class="small text-muted-bwa text-end" style="max-width:55%"><?= e(__('security.fix_' . $c['key'])) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p class="small text-muted-bwa mt-3 mb-0"><?= e(__('security.help')) ?></p>
    </div>
</div>

