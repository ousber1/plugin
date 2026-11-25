<?php
/**
 * Gabarit principal (utilisateur connecté) : barre latérale repliable, barre
 * supérieure avec notifications et menu utilisateur, fil d'Ariane.
 * Variables : $content, $title, $breadcrumbs (optionnel : [[libellé, route|null], …])
 *
 * Le menu ne liste que les modules disponibles ; chaque phase y ajoute ses entrées.
 * Chaque entrée est filtrée par permission.
 */
$user = auth_user();
$current = \App\Core\Request::route();
$menu = [
    ['section' => 'Général'],
    ['route' => '', 'icon' => 'bi-grid-1x2', 'label' => 'Tableau de bord', 'perm' => 'dashboard.view'],
    ['route' => 'notifications', 'icon' => 'bi-bell', 'label' => 'Notifications', 'perm' => null, 'match' => 'notifications'],
    ['section' => 'Ventes'],
    ['route' => 'pos', 'icon' => 'bi-cart3', 'label' => 'Point de vente', 'perm' => 'pos.access', 'match' => 'pos'],
    ['route' => 'sales', 'icon' => 'bi-receipt', 'label' => 'Historique des ventes', 'perm' => 'pos.access', 'match' => 'sales'],
    ['route' => 'cash', 'icon' => 'bi-cash-stack', 'label' => 'Caisse', 'perm' => 'cash.view', 'match' => 'cash'],
    ['section' => 'Commercial'],
    ['route' => 'customers', 'icon' => 'bi-people', 'label' => 'Clients', 'perm' => 'customers.view', 'match' => 'customers'],
    ['route' => 'suppliers', 'icon' => 'bi-truck', 'label' => 'Fournisseurs', 'perm' => 'suppliers.view', 'match' => 'suppliers'],
    ['section' => 'Catalogue'],
    ['route' => 'products', 'icon' => 'bi-box', 'label' => 'Produits & services', 'perm' => 'products.view', 'match' => 'products'],
    ['route' => 'categories', 'icon' => 'bi-tags', 'label' => 'Catégories', 'perm' => 'products.view', 'match' => 'categories'],
    ['route' => 'packs', 'icon' => 'bi-boxes', 'label' => 'Packs', 'perm' => 'packs.view', 'match' => 'packs'],
    ['section' => 'Stock'],
    ['route' => 'stock', 'icon' => 'bi-box-seam', 'label' => 'État du stock', 'perm' => 'stock.view', 'match' => 'stock', 'exclude' => ['stock/movements', 'stock/inventories']],
    ['route' => 'materials', 'icon' => 'bi-layers', 'label' => 'Matières premières', 'perm' => 'stock.view', 'match' => 'materials'],
    ['route' => 'stock/movements', 'icon' => 'bi-arrow-left-right', 'label' => 'Mouvements', 'perm' => 'stock.view', 'match' => 'stock/movements'],
    ['route' => 'stock/inventories', 'icon' => 'bi-clipboard-check', 'label' => 'Inventaires', 'perm' => 'stock.view', 'match' => 'stock/inventories'],
    ['section' => 'Administration'],
    ['route' => 'users', 'icon' => 'bi-people', 'label' => 'Utilisateurs', 'perm' => 'users.view', 'match' => 'users'],
    ['route' => 'roles', 'icon' => 'bi-shield-check', 'label' => 'Rôles & permissions', 'perm' => 'users.view', 'match' => 'roles'],
    ['route' => 'settings', 'icon' => 'bi-gear', 'label' => 'Paramètres', 'perm' => 'settings.manage', 'match' => 'settings'],
    ['route' => 'logs', 'icon' => 'bi-journal-text', 'label' => 'Journal d\'activité', 'perm' => 'logs.view', 'match' => 'logs'],
    ['route' => 'system/status', 'icon' => 'bi-activity', 'label' => 'État du système', 'perm' => 'settings.manage', 'match' => 'system'],
];
// Retire les sections vides (aucune entrée autorisée)
$visible = [];
foreach ($menu as $item) {
    if (isset($item['section'])) {
        $visible[] = $item;
        continue;
    }
    if ($item['perm'] === null || can($item['perm'])) {
        $visible[] = $item;
    }
}
$visible = array_values(array_filter($visible, static function ($item, $i) use ($visible) {
    return !isset($item['section']) || (isset($visible[$i + 1]) && !isset($visible[$i + 1]['section']));
}, ARRAY_FILTER_USE_BOTH));

$isActive = static function (array $item) use ($current): bool {
    if (isset($item['match'])) {
        foreach ($item['exclude'] ?? [] as $ex) {
            if ($current === $ex || str_starts_with($current, $ex . '/')) {
                return false;
            }
        }
        return $current === $item['match'] || str_starts_with($current, $item['match'] . '/');
    }
    return $current === $item['route'];
};
$initials = mb_strtoupper(implode('', array_map(static fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim((string) ($user['full_name'] ?? '?'))) ?: ['?'], 0, 2))));
$logo = (string) setting('business_logo', '');
$breadcrumbs ??= ($current !== '' && ($title ?? '') !== '') ? [[$title, null]] : [];
?>
<!doctype html>
<html lang="fr">
<head>
<?php \App\Core\View::partial('head', ['title' => $title ?? '']); ?>
</head>
<body class="hoc-app">
<a class="visually-hidden-focusable position-absolute p-2 bg-body z-3" href="#hocMainContent">Aller au contenu</a>
<div class="hoc-shell" id="hocShell">
  <aside class="hoc-sidebar" id="hocSidebar" aria-label="Navigation principale">
    <a class="hoc-sidebar-brand text-decoration-none" href="<?= e(url('')) ?>">
      <?php if ($logo !== ''): ?>
        <span class="hoc-logo-img"><img src="<?= e(asset($logo)) ?>" alt=""></span>
      <?php else: ?>
        <span class="hoc-logo-mark hoc-logo-sm">HO</span>
      <?php endif; ?>
      <span class="hoc-brand-text text-truncate"><?= e(setting('business_name', 'HO CREATIVE')) ?></span>
    </a>
    <nav class="hoc-nav">
      <?php foreach ($visible as $item):
        if (isset($item['section'])): ?>
          <div class="hoc-nav-section"><?= e($item['section']) ?></div>
        <?php else:
          $active = $isActive($item); ?>
          <a href="<?= e(url($item['route'])) ?>" class="hoc-nav-link<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?> title="<?= e($item['label']) ?>">
            <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
          </a>
        <?php endif;
      endforeach; ?>
    </nav>
    <div class="hoc-sidebar-foot small">HO CREATIVE ERP · v<?= e(APP_VERSION) ?></div>
  </aside>
  <div class="hoc-backdrop" id="hocBackdrop"></div>

  <div class="hoc-main">
    <header class="hoc-topbar">
      <button class="btn btn-link hoc-icon-btn" id="hocToggle" type="button" aria-label="Afficher / masquer le menu">
        <i class="bi bi-list fs-4"></i>
      </button>
      <nav aria-label="Fil d'Ariane" class="d-none d-md-block text-truncate">
        <ol class="breadcrumb mb-0 flex-nowrap">
          <li class="breadcrumb-item"><a href="<?= e(url('')) ?>"><i class="bi bi-house"></i><span class="visually-hidden">Accueil</span></a></li>
          <?php foreach ($breadcrumbs as $i => [$label, $route]):
            $last = $i === array_key_last($breadcrumbs); ?>
            <li class="breadcrumb-item<?= $last ? ' active' : '' ?>"<?= $last ? ' aria-current="page"' : '' ?>>
              <?php if (!$last && $route !== null): ?><a href="<?= e(url($route)) ?>"><?= e($label) ?></a><?php else: ?><?= e($label) ?><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
      <form class="hoc-search ms-md-3" method="get" action="<?= e(url('search')) ?>" role="search" id="hocSearch" data-url="<?= e(url('search')) ?>">
        <?= route_field('search') ?>
        <i class="bi bi-search"></i>
        <input class="form-control form-control-sm" type="search" name="q" placeholder="Rechercher… (Ctrl+K)" autocomplete="off" aria-label="Recherche globale" id="hocSearchInput">
        <div class="hoc-search-results shadow d-none" id="hocSearchResults"></div>
      </form>
      <div class="ms-auto d-flex align-items-center gap-1">
        <?php if (can('pos.access')): ?><a class="btn btn-accent btn-sm d-none d-sm-inline-flex align-items-center gap-1 me-1" href="<?= e(url('pos')) ?>"><i class="bi bi-cart3"></i><span class="d-none d-lg-inline">Point de vente</span></a><?php endif; ?>
        <button class="btn btn-link hoc-icon-btn" id="hocTheme" type="button" aria-label="Basculer le thème clair / sombre" data-url="<?= e(url('profile/theme')) ?>">
          <i class="bi bi-moon-stars"></i>
        </button>

        <div class="dropdown" id="hocNotif" data-feed="<?= e(url('notifications/feed')) ?>" data-read-all="<?= e(url('notifications/read-all')) ?>" data-read="<?= e(url('notifications/0/read')) ?>">
          <button class="btn btn-link hoc-icon-btn position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <?php $unread = \App\Services\NotificationService::unreadCount(); ?>
            <span class="hoc-badge<?= $unread ? '' : ' d-none' ?>" id="hocNotifCount"><?= $unread > 99 ? '99+' : (int) $unread ?></span>
          </button>
          <div class="dropdown-menu dropdown-menu-end shadow hoc-notif-menu p-0">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
              <strong>Notifications</strong>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="hocNotifReadAll">Tout marquer comme lu</button>
            </div>
            <div id="hocNotifList" class="hoc-notif-list"><div class="p-3 text-center text-body-secondary small">Chargement…</div></div>
            <a href="<?= e(url('notifications')) ?>" class="d-block text-center py-2 border-top small text-decoration-none">Voir toutes les notifications</a>
          </div>
        </div>

        <div class="dropdown">
          <button class="btn btn-link hoc-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="hoc-avatar"><?= e($initials) ?></span>
            <span class="d-none d-sm-inline text-start lh-sm">
              <span class="d-block fw-semibold"><?= e($user['full_name'] ?? '') ?></span>
              <small class="text-body-secondary"><?= e($user['role_name'] ?? '') ?></small>
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><span class="dropdown-item-text small text-body-secondary"><?= e($user['email'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= e(url('profile')) ?>"><i class="bi bi-person me-2"></i>Mon profil</a></li>
            <?php if (can('settings.manage')): ?>
              <li><a class="dropdown-item" href="<?= e(url('settings')) ?>"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="post" action="<?= e(url('logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="hoc-content" id="hocMainContent">
      <?php \App\Core\View::partial('flash'); ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($scripts)): foreach ((array) $scripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
