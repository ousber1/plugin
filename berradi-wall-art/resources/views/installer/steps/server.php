<p class="text-muted-bwa"><?= e(__('installer.server_intro')) ?></p>
<?= $this->insert('installer/steps/_checks', ['rows' => $rows]) ?>
<form method="post" action="<?= e(route('installer.store', ['step' => 2])) ?>" class="d-flex gap-2">
    <?= csrf_field() ?>
    <a href="<?= e(route('installer.step', ['step' => 2])) ?>" class="btn btn-outline-bwa"><i class="bi bi-arrow-clockwise me-1"></i><?= e(__('installer.recheck')) ?></a>
    <button class="btn btn-bwa" type="submit" <?= $passes ? '' : 'disabled' ?>><?= e(__('installer.continue')) ?></button>
</form>
