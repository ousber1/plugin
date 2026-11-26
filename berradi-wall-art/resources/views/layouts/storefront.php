<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(is_rtl() ? 'rtl' : 'ltr') ?>">
<head>
    <?= $this->insert('partials/head', ['seo' => $seo ?? null, 'title' => $title ?? '', 'metaDescription' => $metaDescription ?? null, 'noindex' => $noindex ?? false]) ?>
    <?= $this->section('head') ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#main"><?= e(__('common.skip_to_content')) ?></a>
    <?= $this->insert('partials/storefront_header') ?>
    <?= $this->insert('partials/alerts') ?>
    <main id="main" class="flex-grow-1">
        <?= $this->section('content') ?>
    </main>
    <?= $this->insert('partials/storefront_footer') ?>
    <script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    <script src="<?= e(asset('js/storefront.js')) ?>"></script>
    <script src="<?= e(asset('js/checkout.js')) ?>"></script>
    <?= $this->section('scripts') ?>
</body>
</html>
