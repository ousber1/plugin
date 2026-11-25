<?php /** État du stock. Variables : $kind, $q, $level, $rows, $p, $valuation, $alerts, $materialTypes */
$showCost = can('products.view_cost'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">État du stock</h1><p class="text-body-secondary mb-0">Quantités, seuils et valorisation au coût moyen pondéré.</p></div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= e(url('stock/movements')) ?>" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Mouvements</a>
    <?php if (can('stock.inventory')): ?><a href="<?= e(url('stock/inventories')) ?>" class="btn btn-outline-secondary"><i class="bi bi-clipboard-check"></i> Inventaires</a><?php endif; ?>
    <?php if (can('stock.movements')): ?><a href="<?= e(url('stock/movements/create')) ?>" class="btn btn-dark"><i class="bi bi-arrow-left-right"></i> Entrée / sortie</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-4">
  <?php if ($showCost): ?>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Valeur totale du stock</div><div class="hoc-kpi-value"><?= e(money($valuation['total'])) ?></div><div class="hoc-kpi-sub">Coût moyen pondéré, HT</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Matières</div><div class="hoc-kpi-value"><?= e(money($valuation['materials'])) ?></div><div class="hoc-kpi-sub"><?= (int) $alerts['materials_count'] ?> référence(s)</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Produits stockés</div><div class="hoc-kpi-value"><?= e(money($valuation['products'])) ?></div><div class="hoc-kpi-sub"><?= (int) $alerts['products_count'] ?> référence(s) suivie(s)</div></div></div></div>
  <?php endif; ?>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Alertes stock faible</div>
    <div class="hoc-kpi-value <?= ($alerts['products_low'] + $alerts['materials_low']) ? 'text-danger' : '' ?>"><?= (int) ($alerts['products_low'] + $alerts['materials_low']) ?></div>
    <div class="hoc-kpi-sub"><?= (int) $alerts['materials_low'] ?> matière(s) · <?= (int) $alerts['products_low'] ?> produit(s)</div></div></div></div>
</div>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link<?= $kind === 'materials' ? ' active' : '' ?>" href="<?= e(url('stock', ['kind' => 'materials'])) ?>">Matières premières</a></li>
  <li class="nav-item"><a class="nav-link<?= $kind === 'products' ? ' active' : '' ?>" href="<?= e(url('stock', ['kind' => 'products'])) ?>">Produits stockés</a></li>
</ul>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('stock')) ?>"><?= route_field('stock') ?><input type="hidden" name="kind" value="<?= e($kind) ?>">
    <div class="flex-grow-1"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, code, code-barres…" aria-label="Rechercher"></div>
    <select class="form-select w-auto" name="level" aria-label="Niveau"><option value="">Tous les niveaux</option><option value="low" <?= $level === 'low' ? 'selected' : '' ?>>Sous le minimum</option><option value="out" <?= $level === 'out' ? 'selected' : '' ?>>En rupture</option></select>
    <button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Code</th><th>Article</th><th><?= $kind === 'materials' ? 'Type' : 'Catégorie' ?></th><th class="text-end">Stock</th><th class="text-end">Minimum</th><th>Niveau</th><?php if ($showCost): ?><th class="text-end">Coût moyen</th><th class="text-end">Valeur</th><?php endif; ?><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="9" class="hoc-empty"><i class="bi bi-box-seam"></i><?= $kind === 'products' ? 'Aucun produit avec suivi de stock. Activez « Suivre le stock » sur la fiche des articles revendus.' : 'Aucune matière.' ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $r):
      $qty = (float) $r['stock_quantity']; $min = (float) $r['min_stock'];
      $state = $qty <= 0 ? ['Rupture', 'danger'] : ($min > 0 && $qty <= $min ? ['Faible', 'warning'] : ['Correct', 'success']);
      $pct = $min > 0 ? min(100, max(3, $qty / ($min * 2) * 100)) : 100;
      $link = ($r['kind'] === 'product' ? 'products/' : 'materials/') . $r['id']; ?>
      <tr>
        <td><code><?= e($r['code']) ?></code></td>
        <td><a class="fw-semibold text-reset text-decoration-none" href="<?= e(url($link)) ?>"><?= e($r['name']) ?></a></td>
        <td class="small"><?= e($kind === 'materials' ? ($materialTypes[$r['group_label']] ?? $r['group_label']) : ($r['group_label'] ?? '—')) ?></td>
        <td class="text-end fw-semibold text-nowrap"><?= e(number_fr($qty, 2)) ?> <?= e($r['symbol']) ?></td>
        <td class="text-end text-body-secondary"><?= e(number_fr($min, 2)) ?></td>
        <td style="min-width:130px"><span class="badge text-bg-<?= $state[1] ?><?= $state[1] === 'warning' ? ' text-dark' : '' ?>"><?= $state[0] ?></span>
          <div class="progress mt-1" style="height:4px" aria-hidden="true"><div class="progress-bar bg-<?= $state[1] ?>" style="width:<?= round($pct) ?>%"></div></div></td>
        <?php if ($showCost): ?><td class="text-end"><?= e(money($r['unit_cost'])) ?></td><td class="text-end"><?= e(money(max(0, $qty) * (float) $r['unit_cost'])) ?></td><?php endif; ?>
        <td class="text-end text-nowrap">
          <?php if (can('stock.movements')): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('stock/movements/create', [$r['kind'] => $r['id']])) ?>" title="Mouvement"><i class="bi bi-arrow-left-right"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('stock/movements', [$r['kind'] => $r['id']])) ?>" title="Historique"><i class="bi bi-clock-history"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
