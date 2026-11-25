<?php /** Packs. Variables : $packs, $q, $status */
$showCost = can('products.view_cost');
$today = date('Y-m-d'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Packs commerciaux</h1><p class="text-body-secondary mb-0">Offres groupées de produits et services, vendues à prix forfaitaire.</p></div>
  <?php if (can('packs.manage')): ?><a href="<?= e(url('packs/create')) ?>" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Nouveau pack</a><?php endif; ?>
</div>
<form class="hoc-filters mb-3" method="get" action="<?= e(url('packs')) ?>"><?= route_field('packs') ?>
  <div class="flex-grow-1"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un pack…" aria-label="Rechercher"></div>
  <select class="form-select w-auto" name="status" aria-label="Statut"><?php foreach (['active' => 'Actifs', 'inactive' => 'Inactifs', 'all' => 'Tous'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
</form>
<?php if (!$packs): ?>
  <div class="card"><div class="hoc-empty"><i class="bi bi-boxes"></i>Aucun pack. Créez par exemple « START BUSINESS PACK » : logo + 100 cartes de visite + 100 flyers.</div></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($packs as $pk):
    $value = (float) $pk['components_value']; $cost = (float) $pk['components_cost']; $price = (float) $pk['price'];
    $available = $pk['is_active'] && (!$pk['available_from'] || $pk['available_from'] <= $today) && (!$pk['available_until'] || $pk['available_until'] >= $today); ?>
    <div class="col-md-6 col-xl-4"><div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <div class="d-flex gap-2 align-items-center"><div class="hoc-thumb"><?php if ($pk['image']): ?><img src="<?= e(asset($pk['image'])) ?>" alt=""><?php else: ?><i class="bi bi-boxes"></i><?php endif; ?></div>
            <div><a class="fw-bold text-reset text-decoration-none" href="<?= e(url('packs/' . $pk['id'])) ?>"><?= e($pk['name']) ?></a><br><code class="small"><?= e($pk['code']) ?></code></div></div>
          <?= $available ? '<span class="badge text-bg-success">Disponible</span>' : '<span class="badge text-bg-secondary">' . ($pk['is_active'] ? 'Hors période' : 'Inactif') . '</span>' ?>
        </div>
        <div class="hoc-mini"><span>Composants</span><strong><?= (int) $pk['items_count'] ?><?= (int) $pk['inactive_items'] ? ' <span class="text-danger small">(' . (int) $pk['inactive_items'] . ' inactif)</span>' : '' ?></strong></div>
        <div class="hoc-mini"><span>Valeur au détail</span><span><?= e(money($value)) ?></span></div>
        <div class="hoc-mini"><span>Prix du pack HT</span><strong class="fs-6"><?= e(money($price)) ?></strong></div>
        <?php if ($value > $price && $price > 0): ?><div class="hoc-mini"><span>Économie client</span><span class="text-success"><?= e(money($value - $price)) ?> (−<?= e(number_fr(($value - $price) / $value * 100, 1)) ?> %)</span></div><?php endif; ?>
        <?php if ($showCost): ?><div class="hoc-mini"><span>Coût · marge</span><span class="<?= $price - $cost < 0 ? 'text-danger' : '' ?>"><?= e(money($cost)) ?> · <?= e(money($price - $cost)) ?></span></div><?php endif; ?>
      </div>
      <div class="card-footer bg-transparent d-flex gap-2">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('packs/' . $pk['id'])) ?>"><i class="bi bi-eye"></i> Voir</a>
        <?php if (can('packs.manage')): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('packs/' . $pk['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
      </div>
    </div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
