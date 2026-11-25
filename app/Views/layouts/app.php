<?php
/**
 * Gabarit principal (utilisateur connecté) : barre latérale repliable + barre supérieure.
 * Variables : $content, $title
 * Le menu ne liste que les modules réellement disponibles ; il s'enrichit à chaque phase.
 */
$user = auth_user();
$current = \App\Core\Request::route();
$menu = [
    ['section' => 'Général'],
    ['route' => '', 'icon' => 'bi-house', 'label' => 'Accueil', 'perm' => null],
    ['section' => 'Administration', 'perm' => 'settings.manage'],
    ['route' => 'system/status', 'icon' => 'bi-activity', 'label' => 'État du système', 'perm' => 'settings.manage'],
];
$initials = mb_strtoupper(implode('', array_map(static fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim((string) ($user['full_name'] ?? '?'))) ?: ['?'], 0, 2))));
?>
<!doctype html>
<html lang="fr">
<head>
<?php \App\Core\View::partial('head', ['title' => $title ?? '']); ?>
</head>
<body class="hoc-app">
<div class="hoc-shell" id="hocShell">
  <aside class="hoc-sidebar" id="hocSidebar" aria-label="Navigation principale">
    <div class="hoc-sidebar-brand">
      <span class="hoc-logo-mark hoc-logo-sm">HO</span>
      <span class="hoc-brand-text"><?= e(setting('business_name', 'HO CREATIVE')) ?></span>
    </div>
    <nav class="hoc-nav">
      <?php foreach ($menu as $item):
        if (!empty($item['perm']) && !can($item['perm'])) { continue; }
        if (isset($item['section'])): ?>
          <div class="hoc-nav-section"><?= e($item['section']) ?></div>
        <?php else:
          $active = $current === $item['route']; ?>
          <a href="<?= e(url($item['route'])) ?>" class="hoc-nav-link<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
          </a>
        <?php endif;
      endforeach; ?>
    </nav>
    <div class="hoc-sidebar-foot small">v<?= e(APP_VERSION) ?></div>
  </aside>
  <div class="hoc-backdrop" id="hocBackdrop"></div>

  <div class="hoc-main">
    <header class="hoc-topbar">
      <button class="btn btn-link hoc-icon-btn" id="hocToggle" type="button" aria-label="Afficher / masquer le menu">
        <i class="bi bi-list fs-4"></i>
      </button>
      <nav aria-label="Fil d'Ariane" class="d-none d-md-block">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="<?= e(url('')) ?>">Accueil</a></li>
          <?php if (($title ?? '') !== '' && $current !== ''): ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($title) ?></li>
          <?php endif; ?>
        </ol>
      </nav>
      <div class="ms-auto d-flex align-items-center gap-1">
        <button class="btn btn-link hoc-icon-btn" id="hocTheme" type="button" aria-label="Basculer le thème clair / sombre">
          <i class="bi bi-moon-stars"></i>
        </button>
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

    <main class="hoc-content">
      <?php \App\Core\View::partial('flash'); ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
