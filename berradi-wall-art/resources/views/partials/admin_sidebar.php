<?php
/**
 * Admin navigation. An entry is shown only when its route exists AND the user has the permission,
 * so modules appear automatically as they are delivered in later phases.
 */
$router = app()->router();
$sections = [
    '' => [
        ['admin.dashboard', 'bi-grid-1x2', 'admin.nav_dashboard', 'dashboard.view', true],
    ],
    'admin.nav_sales' => [
        ['admin.orders.index', 'bi-receipt', 'admin.nav_orders', 'orders.view', false, 'orders.manage'],
        ['admin.customers.index', 'bi-people', 'admin.nav_customers', 'customers.view', false, 'customers.manage'],
        ['admin.quotations.index', 'bi-file-earmark-text', 'admin.nav_quotations', 'quotations.view'],
        ['admin.invoices.index', 'bi-file-earmark-check', 'admin.nav_invoices', 'invoices.view'],
        ['admin.payments.index', 'bi-cash-coin', 'admin.nav_payments', 'payments.view'],
    ],
    'admin.nav_catalog' => [
        ['admin.products.index', 'bi-image', 'admin.nav_products', 'products.view', false, 'products.manage'],
        ['admin.categories.index', 'bi-tags', 'admin.nav_categories', 'categories.manage'],
        ['admin.collections.index', 'bi-collection', 'admin.nav_collections', 'collections.manage'],
        ['admin.options.index', 'bi-sliders', 'admin.nav_options', 'products.manage'],
    ],
    'admin.nav_operations' => [
        ['admin.artwork.index', 'bi-palette', 'admin.nav_artwork', 'artwork.view'],
        ['admin.production.index', 'bi-printer', 'admin.nav_production', 'production.view', false, 'production.manage'],
        ['admin.machines.index', 'bi-cpu', 'admin.nav_machines', 'production.manage'],
        ['admin.inventory.index', 'bi-box-seam', 'admin.nav_inventory', 'inventory.view', false, 'inventory.manage'],
        ['admin.purchases.index', 'bi-cart-check', 'admin.nav_purchases', 'purchases.manage'],
        ['admin.suppliers.index', 'bi-building', 'admin.nav_suppliers', 'suppliers.manage'],
        ['admin.shipping.index', 'bi-truck', 'admin.nav_shipping', 'shipping.manage'],
    ],
    'admin.nav_finance' => [
        ['admin.expenses.index', 'bi-wallet2', 'admin.nav_expenses', 'expenses.manage'],
        ['admin.reports.index', 'bi-graph-up', 'admin.nav_reports', 'reports.view'],
    ],
    'admin.nav_marketing' => [
        ['admin.coupons.index', 'bi-ticket-perforated', 'admin.nav_coupons', 'coupons.manage'],
        ['admin.reviews.index', 'bi-star', 'admin.nav_reviews', 'reviews.manage'],
    ],
    'admin.nav_system' => [
        ['admin.settings', 'bi-gear', 'admin.nav_settings', 'settings.manage'],
        ['admin.maintenance', 'bi-tools', 'admin.nav_maintenance', 'settings.manage'],
        ['admin.users.index', 'bi-person-badge', 'admin.nav_users', 'users.manage'],
        ['admin.roles.index', 'bi-shield-lock', 'admin.nav_roles', 'roles.manage'],
        ['admin.logs.index', 'bi-journal-text', 'admin.nav_logs', 'logs.view'],
    ],
];
?>
<aside class="admin-sidebar" aria-label="<?= e(__('admin.navigation')) ?>">
    <div class="sidebar-brand">
        <a href="<?= e(route('admin.dashboard')) ?>"><?= $this->insert('partials/brand', ['textOnly' => true]) ?></a>
    </div>
    <nav class="sidebar-scroll">
        <?php foreach ($sections as $title => $items): ?>
            <?php
            $visible = array_filter($items, fn ($i) => $router->has($i[0]) && (!empty($i[4]) || can($i[3]) || (isset($i[5]) && can($i[5]))));
            if ($visible === []) {
                continue;
            }
            ?>
            <?php if ($title !== ''): ?><div class="nav-section"><?= e(__($title)) ?></div><?php endif; ?>
            <?php foreach ($visible as $item): ?>
                <?php $href = route($item[0]); ?>
                <a class="nav-link <?= active($href, $item[0] === 'admin.dashboard') ?>" href="<?= e($href) ?>">
                    <i class="bi <?= e($item[1]) ?>"></i><span><?= e(__($item[2])) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="p-3 border-top border-secondary border-opacity-25 small">
        <a class="nav-link px-2" href="<?= e(route('home')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('admin.view_store')) ?></a>
    </div>
</aside>
<div class="sidebar-backdrop"></div>
