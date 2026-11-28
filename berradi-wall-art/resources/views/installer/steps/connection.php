<?php if ($result['ok']): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e(__('installer.db_ok', ['server' => (string) ($result['server'] ?? ''), 'version' => (string) ($result['version'] ?? '')])) ?></div>
<?php else: ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= e(__('installer.db_failed')) ?><?php if (!empty($result['error'])): ?><div class="small mt-2" dir="ltr"><?= e($result['error']) ?></div><?php endif; ?></div>
<?php endif; ?>
<?php if (!empty($result['checks'])): ?>
    <div class="table-responsive mb-4">
        <table class="table check-table">
            <tbody>
            <?php foreach ($result['checks'] as $c): ?>
                <tr><td class="fw-semibold"><?= e($c['label']) ?></td><td dir="ltr" class="text-start"><?= e($c['current']) ?></td><td class="text-end"><i class="bi <?= $c['ok'] ? 'bi-check-circle-fill status-ok' : 'bi-x-circle-fill status-bad' ?>"></i></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (($result['existing_tables'] ?? 0) > 0): ?>
    <div class="alert alert-warning small"><?= e(__('installer.db_not_empty', ['count' => (int) $result['existing_tables']])) ?></div>
<?php endif; ?>
<form method="post" action="<?= e(route('installer.store', ['step' => 5])) ?>" class="d-flex gap-2">
    <?= csrf_field() ?>
    <a href="<?= e(route('installer.step', ['step' => 4])) ?>" class="btn btn-outline-bwa"><i class="bi bi-arrow-left me-1"></i><?= e(__('installer.edit_settings')) ?></a>
    <button class="btn btn-bwa" type="submit" <?= $result['ok'] ? '' : 'disabled' ?>><?= e(__('installer.continue')) ?></button>
</form>
