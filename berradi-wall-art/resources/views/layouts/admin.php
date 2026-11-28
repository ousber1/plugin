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
                <?php $unread = $user ? \App\Services\Notifications\StaffNotifier::unreadCount((int) $user['id']) : 0; $latest = $user ? \App\Services\Notifications\StaffNotifier::latest((int) $user['id'], 6) : []; ?>
                <div class="dropdown">
                    <button class="btn btn-link p-0 text-dark position-relative notif-bell" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('notifications.title') . ($unread ? ' (' . $unread . ')' : '')) ?>">
                        <i class="bi bi-bell fs-5"></i><?php if ($unread > 0): ?><span class="notif-count"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm notif-menu p-0">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong class="small"><?= e(__('notifications.title')) ?></strong>
                            <?php if ($unread > 0): ?><form method="post" action="<?= e(route('admin.notifications.read_all')) ?>"><?= csrf_field() ?><button class="btn btn-link btn-sm p-0 small" type="submit"><?= e(__('notifications.mark_all')) ?></button></form><?php endif; ?>
                        </div>
                        <?php foreach ($latest as $n): ?>
                            <a class="dropdown-item notif-item<?= $n['status'] === 'queued' ? ' is-unread' : '' ?>" href="<?= e(route('admin.notifications.open', ['id' => $n['id']])) ?>">
                                <span class="d-block small text-wrap"><?= e((string) $n['subject']) ?></span>
                                <span class="d-block text-muted-bwa" style="font-size:.7rem"><?= e(format_date($n['created_at'], true)) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($latest === []): ?><p class="small text-muted-bwa px-3 py-3 mb-0"><?= e(__('notifications.none')) ?></p><?php endif; ?>
                        <a class="d-block text-center small py-2 border-top" href="<?= e(route('admin.notifications.index')) ?>"><?= e(__('notifications.see_all')) ?></a>
                    </div>
                </div>
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
