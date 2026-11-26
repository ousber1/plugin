<?php $user = auth_user(); ?>
<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(is_rtl() ? 'rtl' : 'ltr') ?>">
<head>
    <?= $this->insert('partials/head', ['title' => ($title ?? '') . ' · Admin', 'noindex' => true, 'extraCss' => ['css/admin.css']]) ?>
    <?= $this->section('head') ?>
</head>
<body class="admin-body">
    <?= $this->insert('partials/admin_sidebar') ?>
    <div class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-sm btn-outline-bwa d-lg-none" type="button" data-sidebar-toggle aria-label="<?= e(__('nav.menu')) ?>"><i class="bi bi-list"></i></button>
            <h1 class="page-title text-truncate"><?= e($title ?? '') ?></h1>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="lang-switch d-none d-sm-inline">
                    <a href="<?= e(route('locale.switch', ['locale' => 'fr'])) ?>" class="<?= locale() === 'fr' ? 'active' : '' ?>">FR</a>
                    <a href="<?= e(route('locale.switch', ['locale' => 'ar'])) ?>" class="<?= locale() === 'ar' ? 'active' : '' ?>">ع</a>
                </span>
                <div class="dropdown">
                    <button class="btn btn-link p-0 d-flex align-items-center gap-2 text-decoration-none text-dark" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= e(\App\Helpers\Str::initials((string) ($user['name'] ?? ''))) ?></span>
                        <span class="d-none d-md-inline text-start lh-sm">
                            <span class="d-block fw-semibold small"><?= e($user['name'] ?? '') ?></span>
                            <span class="d-block text-muted-bwa" style="font-size:.72rem"><?= e($user['role_name'] ?? '') ?></span>
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="<?= e(route('admin.profile')) ?>"><i class="bi bi-person me-2"></i><?= e(__('admin.my_profile')) ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(route('admin.logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i><?= e(__('auth.logout')) ?></button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <?= $this->insert('partials/alerts') ?>
        <main class="admin-content">
            <?= $this->section('content') ?>
        </main>
        <footer class="admin-footer d-flex justify-content-between">
            <span>BERRADI WALL ART · v<?= e(config('app.version')) ?></span>
            <span><?= e(date('d/m/Y H:i')) ?></span>
        </footer>
    </div>
    <script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    <script src="<?= e(asset('js/admin.js')) ?>"></script>
    <?= $this->section('scripts') ?>
</body>
</html>
