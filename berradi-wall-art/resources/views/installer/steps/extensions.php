<p class="text-muted-bwa"><?= e(__('installer.extensions_intro')) ?></p>
<h2 class="h6 text-uppercase small text-muted-bwa"><?= e(__('installer.php_extensions')) ?></h2>
<?= $this->insert('installer/steps/_checks', ['rows' => $extensions]) ?>
<h2 class="h6 text-uppercase small text-muted-bwa"><?= e(__('installer.folder_permissions')) ?></h2>
<?= $this->insert('installer/steps/_checks', ['rows' => $permissions]) ?>
<?php if (!$passes): ?><div class="alert alert-danger"><?= e(__('installer.fix_requirements')) ?></div><?php endif; ?>
<form method="post" action="<?= e(route('installer.store', ['step' => 3])) ?>" class="d-flex gap-2">
    <?= csrf_field() ?>
    <a href="<?= e(route('installer.step', ['step' => 3])) ?>" class="btn btn-outline-bwa"><i class="bi bi-arrow-clockwise me-1"></i><?= e(__('installer.recheck')) ?></a>
    <button class="btn btn-bwa" type="submit" <?= $passes ? '' : 'disabled' ?>><?= e(__('installer.continue')) ?></button>
</form>
