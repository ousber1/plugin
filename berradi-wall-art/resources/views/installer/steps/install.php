<p class="text-muted-bwa"><?= e(__('installer.install_intro')) ?></p>
<?php if (!empty($manualEnv)): ?>
    <div class="alert alert-warning"><?= e(__('installer.env_manual_help')) ?></div>
    <pre class="code-block mb-4"><?= e($manualEnv) ?></pre>
<?php endif; ?>
<?php if (!empty($log)): ?>
    <pre class="code-block mb-4"><?= e(implode("\n", $log)) ?></pre>
<?php endif; ?>
<div class="d-flex gap-2">
    <?php if ($installed): ?>
        <a href="<?= e(route('installer.step', ['step' => 7])) ?>" class="btn btn-bwa"><?= e(__('installer.continue')) ?> <i class="bi bi-arrow-right ms-1"></i></a>
    <?php else: ?>
        <form method="post" action="<?= e(route('installer.store', ['step' => 6])) ?>" data-once>
            <?= csrf_field() ?>
            <button class="btn btn-bwa" type="submit" data-loading="<?= e(__('installer.installing')) ?>"><i class="bi bi-database-add me-1"></i><?= e(__('installer.install_button')) ?></button>
        </form>
    <?php endif; ?>
</div>
