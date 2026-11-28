<?php
/**
 * Storefront header: utility bar, main bar (logo, search, account / tracking / basket), category bar with mega menus
 * built from the catalogue (Catalog\Navigation), announcement strip (Contenu boutique → Bandeau d'annonces).
 */
$router = app()->router();
$installed = \App\Core\App::instance()->isInstalled();
$menu = $installed ? \App\Services\Catalog\Navigation::menu() : ['wall' => ['tiles' => [], 'categories' => []], 'gift' => ['tiles' => [], 'categories' => []], 'laser' => ['tiles' => [], 'categories' => []], 'recipients' => [], 'occasions' => []];
$customer = auth_customer();
$phone = (string) setting('company_phone', '');
$q = (string) (\App\Core\Request::current()?->query('q') ?? '');
$cartCount = $installed && $router->has('cart.index') ? \App\Services\Sales\CartService::count() : 0;
$universes = array_filter([
    'wall' => ['shop.wall', 'universe.nav_wall', 'bi-image'],
    'gift' => ['shop.gifts', 'universe.nav_gift', 'bi-gift'],
    'laser' => ['shop.laser', 'universe.nav_laser', 'bi-lightning-charge'],
], fn ($u) => $router->has($u[0]));
$bars = [];
foreach ([1, 2] as $n) {
    $text = trim((string) setting(locale() === 'ar' ? "promo_bar_{$n}_text_ar" : "promo_bar_{$n}_text", '')) ?: trim((string) setting("promo_bar_{$n}_text", ''));
    if ($text !== '') {
        $link = (string) setting("promo_bar_{$n}_link", '');
        $bars[] = ['text' => $text, 'link' => preg_match('#^(/|https://)#', $link) ? $link : ''];
    }
}
$tagLinks = function (array $tags, string $route, int $max = 12): string {
    $html = '';
    foreach (array_slice($tags, 0, $max) as $t) {
        $html .= '<li><a href="' . e(route($route, ['slug' => $t['slug']])) . '">' . e($t['name']) . '</a></li>';
    }
    return $html;
};
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
<header class="site-header shop-header sticky-top">
    <div class="header-main">
        <div class="container header-main-inner">
            <button class="header-icon menu-toggle d-xl-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="<?= e(__('nav.menu')) ?>">
                <i class="bi bi-list"></i><span><?= e(__('nav.menu')) ?></span>
            </button>
            <a class="navbar-brand header-brand" href="<?= e(route('home')) ?>"><?= $this->insert('partials/brand') ?></a>
            <?php if ($router->has('shop.index')): ?>
                <form class="header-search" method="get" action="<?= e(route('shop.index')) ?>" role="search">
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('gifts.search_placeholder')) ?>" aria-label="<?= e(__('shop.search')) ?>">
                    <button type="submit" aria-label="<?= e(__('shop.search')) ?>"><i class="bi bi-search"></i></button>
                </form>
            <?php endif; ?>
            <div class="header-actions">
                <?php if ($customer): ?>
                    <a class="header-action" href="<?= e(route('customer.account')) ?>"><i class="bi bi-person-circle"></i><span><?= e($customer['first_name']) ?></span></a>
                <?php elseif ($router->has('customer.login')): ?>
                    <a class="header-action" href="<?= e(route('customer.login')) ?>"><i class="bi bi-person-circle"></i><span><?= e(__('nav.account')) ?></span></a>
                <?php endif; ?>
                <?php if ($router->has('order.track')): ?>
                    <a class="header-action d-none d-md-flex" href="<?= e(route('order.track')) ?>"><i class="bi bi-truck"></i><span><?= e(__('gifts.track_short')) ?></span></a>
                <?php endif; ?>
                <?php if ($router->has('cart.index')): ?>
                    <a class="header-action" href="<?= e(route('cart.index')) ?>"><i class="bi bi-basket"></i><span><?= e(__('nav.cart')) ?></span><span class="cart-badge<?= $cartCount > 0 ? '' : ' d-none' ?>" data-cart-count><?= $cartCount ?></span></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <nav class="cat-bar" aria-label="<?= e(__('nav.main')) ?>">
        <div class="collapse d-xl-block" id="mainNav">
            <ul class="cat-menu container" data-mega-menu>
                <?php foreach ($universes as $family => [$route, $label, $icon]): $m = $menu[$family]; ?>
                    <li class="has-mega">
                        <a class="cat-link" href="<?= e(route($route)) ?>"><i class="bi <?= $icon ?> d-xl-none me-2"></i><?= e(__($label)) ?></a>
                        <button class="mega-toggle d-xl-none" type="button" aria-expanded="false" aria-label="<?= e(__($label)) ?>"><i class="bi bi-chevron-down"></i></button>
                        <div class="mega">
                            <div class="container mega-inner">
                                <?php if ($m['tiles'] !== []): ?>
                                    <div class="mega-looking d-none d-xl-block">
                                        <div class="mega-head"><?= e(__('gifts.looking_for')) ?></div>
                                        <div class="mega-tiles">
                                            <?php foreach ($m['tiles'] as $t): ?>
                                                <a class="mega-tile" href="<?= e($t['url']) ?>"><span class="mt-img"><img src="<?= e(upload_url($t['image'])) ?>" alt="" loading="lazy"></span><span class="mt-label"><?= e($t['name']) ?></span></a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="mega-col">
                                    <div class="mega-head"><?= e(__('gifts.by_category')) ?></div>
                                    <ul>
                                        <?php foreach (array_slice($m['categories'], 0, 14) as $c): ?><li><a href="<?= e($c['url']) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
                                        <li><a class="mega-all" href="<?= e(route($route)) ?>"><?= e(__('gifts.see_all')) ?> <i class="bi bi-arrow-<?= is_rtl() ? 'left' : 'right' ?>"></i></a></li>
                                    </ul>
                                </div>
                                <?php if ($family !== 'wall' && $menu['recipients'] !== [] && $router->has('shop.recipient')): ?>
                                    <div class="mega-col"><div class="mega-head"><?= e(__('gifts.by_recipient')) ?></div><ul><?= $tagLinks($menu['recipients'], 'shop.recipient') ?></ul></div>
                                <?php endif; ?>
                                <?php if ($menu['occasions'] !== [] && $router->has('shop.occasion')): ?>
                                    <div class="mega-col"><div class="mega-head"><?= e(__('gifts.by_occasion')) ?></div><ul><?= $tagLinks($menu['occasions'], 'shop.occasion') ?></ul></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if ($menu['recipients'] !== [] && $router->has('shop.recipient')): ?>
                    <li class="has-mega">
                        <a class="cat-link" href="<?= e(route('shop.recipient', ['slug' => $menu['recipients'][0]['slug']])) ?>"><i class="bi bi-people d-xl-none me-2"></i><?= e(__('gifts.nav_recipient')) ?></a>
                        <button class="mega-toggle d-xl-none" type="button" aria-expanded="false" aria-label="<?= e(__('gifts.nav_recipient')) ?>"><i class="bi bi-chevron-down"></i></button>
                        <div class="mega"><div class="container mega-inner">
                            <div class="mega-icons">
                                <?php foreach ($menu['recipients'] as $t): ?>
                                    <a class="mega-icon" href="<?= e(route('shop.recipient', ['slug' => $t['slug']])) ?>"><i class="bi bi-<?= e((string) ($t['icon'] ?: 'gift')) ?>"></i><span><?= e($t['name']) ?></span></a>
                                <?php endforeach; ?>
                            </div>
                        </div></div>
                    </li>
                <?php endif; ?>
                <?php if ($menu['occasions'] !== [] && $router->has('shop.occasion')): ?>
                    <li class="has-mega">
                        <a class="cat-link" href="<?= e(route('shop.occasion', ['slug' => $menu['occasions'][0]['slug']])) ?>"><i class="bi bi-calendar-heart d-xl-none me-2"></i><?= e(__('gifts.nav_occasion')) ?></a>
                        <button class="mega-toggle d-xl-none" type="button" aria-expanded="false" aria-label="<?= e(__('gifts.nav_occasion')) ?>"><i class="bi bi-chevron-down"></i></button>
                        <div class="mega"><div class="container mega-inner">
                            <div class="mega-icons">
                                <?php foreach ($menu['occasions'] as $t): ?>
                                    <a class="mega-icon" href="<?= e(route('shop.occasion', ['slug' => $t['slug']])) ?>"><i class="bi bi-<?= e((string) ($t['icon'] ?: 'calendar-heart')) ?>"></i><span><?= e($t['name']) ?></span></a>
                                <?php endforeach; ?>
                            </div>
                        </div></div>
                    </li>
                <?php endif; ?>
                <?php foreach ([['builder.index', 'nav.custom', 'bi-camera'], ['shop.offers', 'shop.offers', 'bi-percent'], ['page.contact', 'nav.contact', 'bi-chat-dots']] as [$r, $label, $icon]): if (!$router->has($r)) continue; ?>
                    <li><a class="cat-link<?= $r === 'shop.offers' ? ' is-accent' : '' ?>" href="<?= e(route($r)) ?>"><i class="bi <?= $icon ?> d-xl-none me-2"></i><?= e(__($label)) ?></a></li>
                <?php endforeach; ?>
                <?php if ($router->has('order.track')): ?><li class="d-md-none"><a class="cat-link" href="<?= e(route('order.track')) ?>"><i class="bi bi-truck me-2"></i><?= e(__('nav.track')) ?></a></li><?php endif; ?>
            </ul>
        </div>
    </nav>
    <?php if ($bars !== []): ?>
        <div class="promo-strip<?= count($bars) === 1 ? ' is-single' : '' ?>">
            <?php foreach ($bars as $i => $b): ?>
                <?php if ($b['link'] !== ''): ?><a class="ps-item ps-<?= $i + 1 ?>" href="<?= e($b['link']) ?>"><?= e($b['text']) ?> <i class="bi bi-arrow-<?= is_rtl() ? 'left' : 'right' ?>-short"></i></a>
                <?php else: ?><span class="ps-item ps-<?= $i + 1 ?>"><?= e($b['text']) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</header>
