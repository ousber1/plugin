<?php
/** Main navigation — items appear automatically once their route exists (built phase by phase). */
$router = app()->router();
$nav = array_filter([
    ['route' => 'home', 'label' => __('nav.home')],
    ['route' => 'shop.index', 'label' => __('nav.shop')],
    ['route' => 'builder.index', 'label' => __('nav.custom')],
    ['route' => 'collections.index', 'label' => __('nav.collections')],
    ['route' => 'shop.best_sellers', 'label' => __('nav.best_sellers')],
    ['route' => 'page.about', 'label' => __('nav.about')],
    ['route' => 'page.contact', 'label' => __('nav.contact')],
    ['route' => 'order.track', 'label' => __('nav.track')],
], fn ($i) => $router->has($i['route']));
$customer = auth_customer();
$phone = (string) setting('company_phone', '');
?>
<div class="topbar py-2">
    <div class="container d-flex justify-content-between align-items-center gap-3">
        <span class="d-none d-md-inline"><?= e(__('common.topbar_message')) ?></span>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <?php if ($phone !== ''): ?>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><i class="bi bi-telephone me-1"></i><span dir="ltr"><?= e($phone) ?></span></a>
            <?php endif; ?>
            <span class="lang-switch">
                <a href="<?= e(route('locale.switch', ['locale' => 'fr'])) ?>" class="<?= locale() === 'fr' ? 'active' : '' ?>">FR</a>
                <a href="<?= e(route('locale.switch', ['locale' => 'ar'])) ?>" class="<?= locale() === 'ar' ? 'active' : '' ?>">ع</a>
            </span>
        </div>
    </div>
</div>
<header class="site-header sticky-top">
    <nav class="navbar navbar-expand-xl py-3" aria-label="<?= e(__('nav.main')) ?>">
        <div class="container">
            <a class="navbar-brand me-xl-4" href="<?= e(route('home')) ?>"><?= $this->insert('partials/brand') ?></a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="<?= e(__('nav.menu')) ?>">
                <i class="bi bi-list fs-2"></i>
            </button>
            <div class="collapse navbar-collapse site-nav" id="mainNav">
                <ul class="navbar-nav mx-auto">
                    <?php foreach ($nav as $item): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= active(route($item['route']), $item['route'] === 'home') ?>" href="<?= e(route($item['route'])) ?>"><?= e($item['label']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex align-items-center gap-1">
                    <?php if ($router->has('shop.index')): ?>
                        <button class="header-icon btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#headerSearch" aria-expanded="false" aria-controls="headerSearch" title="<?= e(__('shop.search')) ?>"><i class="bi bi-search"></i></button>
                    <?php endif; ?>
                    <?php if ($customer): ?>
                        <a class="header-icon" href="<?= e(route('customer.account')) ?>" title="<?= e(__('nav.account')) ?>"><i class="bi bi-person"></i><span class="d-lg-none ms-2 fs-6"><?= e($customer['first_name']) ?></span></a>
                    <?php else: ?>
                        <a class="header-icon" href="<?= e(route('customer.login')) ?>" title="<?= e(__('nav.login')) ?>"><i class="bi bi-person"></i><span class="d-lg-none ms-2 fs-6"><?= e(__('nav.login')) ?></span></a>
                    <?php endif; ?>
                    <?php if ($router->has('cart.index')): ?>
                        <?php $cartCount = \App\Core\App::instance()->isInstalled() ? \App\Services\Sales\CartService::count() : 0; ?>
                        <a class="header-icon" href="<?= e(route('cart.index')) ?>" title="<?= e(__('nav.cart')) ?>"><i class="bi bi-bag"></i><span class="cart-badge<?= $cartCount > 0 ? '' : ' d-none' ?>" data-cart-count><?= $cartCount ?></span></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <?php if ($router->has('shop.index')): ?>
        <div class="collapse border-top" id="headerSearch">
            <form class="container py-3" method="get" action="<?= e(route('shop.index')) ?>" role="search">
                <div class="input-group">
                    <input type="search" name="q" class="form-control" placeholder="<?= e(__('shop.search_placeholder')) ?>" aria-label="<?= e(__('shop.search')) ?>" value="<?= e((string) (\App\Core\Request::current()?->query('q') ?? '')) ?>">
                    <button class="btn btn-bwa" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</header>
