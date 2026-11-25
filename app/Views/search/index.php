<?php /** Résultats de recherche. Variables : $q, $groups */ ?>
<div class="hoc-page-head"><div><h1 class="h3 fw-bold mb-1">Recherche</h1>
  <p class="text-body-secondary mb-0"><?= $q === '' ? 'Saisissez au moins 2 caractères.' : 'Résultats pour « ' . e($q) . ' »' ?></p></div></div>
<form method="get" action="<?= e(url('search')) ?>" class="mb-4" style="max-width:640px"><?= route_field('search') ?>
  <div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" type="search" name="q" value="<?= e($q) ?>" autofocus aria-label="Rechercher"></div></form>
<?php if ($q !== '' && !$groups): ?><div class="card"><div class="hoc-empty"><i class="bi bi-search"></i>Aucun résultat.</div></div><?php endif; ?>
<div class="row g-3">
  <?php foreach ($groups as $g): ?>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold"><i class="bi <?= e($g['icon']) ?> me-1"></i><?= e($g['label']) ?> <span class="badge text-bg-light border"><?= count($g['items']) ?></span></div>
      <div class="list-group list-group-flush"><?php foreach ($g['items'] as $it): ?>
        <a class="list-group-item list-group-item-action" href="<?= e($it['url']) ?>"><div class="fw-semibold"><?= e($it['title']) ?></div><small class="text-body-secondary"><?= e($it['subtitle']) ?></small></a>
      <?php endforeach; ?></div></div></div>
  <?php endforeach; ?>
</div>
