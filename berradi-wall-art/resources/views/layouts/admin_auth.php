<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(is_rtl() ? 'rtl' : 'ltr') ?>">
<head>
    <?= $this->insert('partials/head', ['title' => $title ?? '', 'noindex' => true, 'extraCss' => ['css/admin.css']]) ?>
</head>
<body class="admin-login-body">
    <?= $this->insert('partials/alerts', ['hideErrorToast' => true]) ?>
    <div class="admin-login-card">
        <div class="card-body">
            <div class="text-center mb-4">
                <?= $this->insert('partials/brand') ?>
                <div class="eyebrow mt-3"><?= e(__('admin.backoffice')) ?></div>
            </div>
            <?= $this->section('content') ?>
            <div class="text-center mt-4 small">
                <a class="text-muted-bwa" href="<?= e(route('locale.switch', ['locale' => 'fr'])) ?>">Français</a> ·
                <a class="text-muted-bwa" href="<?= e(route('locale.switch', ['locale' => 'ar'])) ?>">العربية</a>
            </div>
        </div>
    </div>
    <script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
