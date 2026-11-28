<p class="text-muted-bwa"><?= e(__('installer.welcome_intro')) ?></p>
<ul class="small text-muted-bwa mb-4">
    <li><?= e(__('installer.welcome_need_db')) ?></li>
    <li><?= e(__('installer.welcome_need_email')) ?></li>
    <li><?= e(__('installer.welcome_need_time')) ?></li>
</ul>
<form method="post" action="<?= e(route('installer.store', ['step' => 1])) ?>">
    <?= csrf_field() ?>
    <label class="form-label"><?= e(__('installer.choose_language')) ?></label>
    <div class="d-flex gap-3 mb-4">
        <?php foreach (config('app.locales') as $code => $label): ?>
            <label class="card-bwa p-3 d-flex align-items-center gap-2" style="cursor:pointer">
                <input type="radio" class="form-check-input mt-0" name="locale" value="<?= e($code) ?>" <?= locale() === $code ? 'checked' : '' ?>> <?= e($label) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-bwa" type="submit"><?= e(__('installer.start')) ?> <i class="bi bi-arrow-right ms-1"></i></button>
</form>
