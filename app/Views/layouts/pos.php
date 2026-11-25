<?php
/** Gabarit plein écran du point de vente. Variables : $content, $title, $session */
$user = auth_user();
$logo = (string) setting('business_logo', '');
?>
<!doctype html>
<html lang="fr">
<head>
<?php \App\Core\View::partial('head', ['title' => $title ?? 'Point de vente']); ?>
<link href="<?= e(asset('css/pos.css')) ?>" rel="stylesheet">
</head>
<body class="hoc-pos-body">
<header class="pos-top">
  <a class="pos-brand" href="<?= e(url('')) ?>" title="Retour au tableau de bord">
    <?php if ($logo !== ''): ?><span class="hoc-logo-img"><img src="<?= e(asset($logo)) ?>" alt=""></span><?php else: ?><span class="hoc-logo-mark hoc-logo-sm">HO</span><?php endif; ?>
    <span class="d-none d-md-inline">Point de vente</span>
  </a>
  <?php if (!empty($session)): ?>
    <span class="pos-session"><span class="hoc-status-dot bg-success"></span>Caisse ouverte · <?= e($session['number']) ?><span class="d-none d-lg-inline"> · <?= e($session['opened_by_name'] ?? '') ?></span></span>
  <?php else: ?>
    <span class="pos-session text-danger"><span class="hoc-status-dot bg-danger"></span>Caisse fermée</span>
  <?php endif; ?>
  <nav class="ms-auto d-flex align-items-center gap-1">
    <?php if (can('pos.access')): ?><a class="btn btn-sm btn-link text-reset" href="<?= e(url('sales')) ?>"><i class="bi bi-receipt"></i><span class="d-none d-lg-inline"> Ventes</span></a><?php endif; ?>
    <?php if (can('cash.view')): ?><a class="btn btn-sm btn-link text-reset" href="<?= e(url('cash')) ?>"><i class="bi bi-cash-stack"></i><span class="d-none d-lg-inline"> Caisse</span></a><?php endif; ?>
    <button class="btn btn-sm btn-link text-reset" id="hocTheme" type="button" aria-label="Thème" data-url="<?= e(url('profile/theme')) ?>"><i class="bi bi-moon-stars"></i></button>
    <span class="hoc-avatar ms-1" title="<?= e($user['full_name'] ?? '') ?>"><?= e(mb_strtoupper(mb_substr((string) ($user['full_name'] ?? '?'), 0, 2))) ?></span>
    <a class="btn btn-sm btn-outline-secondary ms-2" href="<?= e(url('')) ?>"><i class="bi bi-x-lg"></i><span class="d-none d-md-inline"> Quitter</span></a>
  </nav>
</header>
<?php \App\Core\View::partial('flash'); ?>
<?= $content ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/pos.js')) ?>"></script>
</body>
</html>
