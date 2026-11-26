<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e(__('installer.finish_intro')) ?></div>
<h2 class="h6 text-uppercase small text-muted-bwa mt-4"><?= e(__('installer.cron_title')) ?></h2>
<p class="small text-muted-bwa"><?= e(__('installer.cron_help')) ?></p>
<pre class="code-block">*/15 * * * * <?= e($cronCommand) ?> >/dev/null 2>&1</pre>
<h2 class="h6 text-uppercase small text-muted-bwa mt-4"><?= e(__('installer.security_title')) ?></h2>
<ul class="small text-muted-bwa">
    <li><?= e(__('installer.security_docroot')) ?></li>
    <li><?= e(__('installer.security_https')) ?></li>
    <li><?= e(__('installer.security_backup')) ?></li>
    <li><?= e(__('installer.security_admin_path')) ?></li>
</ul>
<form method="post" action="<?= e(route('installer.store', ['step' => 9])) ?>" class="mt-4" data-once>
    <?= csrf_field() ?>
    <div class="card-bwa p-3 mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="install_demo" value="1" id="install_demo">
            <label class="form-check-label fw-semibold" for="install_demo"><?= e(__('installer.demo_label')) ?></label>
        </div>
        <p class="small text-muted-bwa mb-0 mt-1"><?= e(__('installer.demo_help')) ?></p>
    </div>
    <button class="btn btn-bwa btn-lg" type="submit" data-loading="<?= e(__('installer.installing')) ?>"><i class="bi bi-lock me-1"></i><?= e(__('installer.finish_button')) ?></button>
</form>
