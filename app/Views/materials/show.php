<?php /** Fiche matière. Variables : $m, $supplierPrices, $products, $movements, $consumed, $machines */
$types = \App\Controllers\MaterialController::TYPES;
$showCost = can('products.view_cost');
$low = (float) $m['min_stock'] > 0 && (float) $m['stock_quantity'] <= (float) $m['min_stock'];
$best = $supplierPrices[0]['price'] ?? null; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($m['name']) ?> <?= $m['is_active'] ? '' : '<span class="badge text-bg-secondary fs-6">Inactive</span>' ?></h1>
    <div class="text-body-secondary"><code><?= e($m['code']) ?></code> · <?= e($types[$m['material_type']] ?? $m['material_type']) ?><?= $m['supplier_name'] ? ' · ' . e($m['supplier_name']) : '' ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (can('stock.movements')): ?><a class="btn btn-outline-secondary" href="<?= e(url('stock/movements/create', ['material' => $m['id']])) ?>"><i class="bi bi-arrow-left-right"></i> Mouvement de stock</a><?php endif; ?>
    <?php if (can('stock.materials')): ?><a class="btn btn-dark" href="<?= e(url('materials/' . $m['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Stock</div>
    <div class="hoc-kpi-value <?= (float) $m['stock_quantity'] <= 0 ? 'text-danger' : ($low ? 'text-warning-emphasis' : '') ?>"><?= e(number_fr($m['stock_quantity'], 2)) ?> <small class="fs-6"><?= e($m['symbol']) ?></small></div>
    <div class="hoc-kpi-sub">Minimum <?= e(number_fr($m['min_stock'], 2)) ?><?= $low ? ' · à réapprovisionner' : '' ?></div></div></div></div>
  <?php if ($showCost): ?>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Coût moyen</div><div class="hoc-kpi-value"><?= e(money($m['unit_cost'])) ?></div><div class="hoc-kpi-sub">par <?= e($m['symbol']) ?> · dernier achat <?= e(money($m['last_purchase_cost'])) ?></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Valeur du stock</div><div class="hoc-kpi-value"><?= e(money(max(0, (float) $m['stock_quantity']) * (float) $m['unit_cost'])) ?></div><div class="hoc-kpi-sub">quantité × coût moyen</div></div></div></div>
  <?php endif; ?>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Consommé ce mois</div><div class="hoc-kpi-value"><?= e(number_fr($consumed, 2)) ?> <small class="fs-6"><?= e($m['symbol']) ?></small></div><div class="hoc-kpi-sub">Production, consommation, ventes</div></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Comparaison des fournisseurs</div>
    <?php if (!$supplierPrices): ?><div class="hoc-empty"><i class="bi bi-truck"></i>Aucun prix fournisseur. Ajoutez-les depuis la fiche de chaque fournisseur (onglet « Prix des matières »).</div><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fournisseur</th><th class="text-end">Prix</th><th class="text-end">Écart</th><th class="text-end">Qté min.</th><th>Depuis</th></tr></thead><tbody>
      <?php foreach ($supplierPrices as $sp): $diff = $best > 0 ? ((float) $sp['price'] - (float) $best) / (float) $best * 100 : 0; ?>
        <tr><td><a class="text-reset" href="<?= e(url('suppliers/' . $sp['sid'], ['tab' => 'prices'])) ?>"><?= e($sp['supplier_name']) ?></a><?= $diff == 0 ? ' <span class="badge text-bg-success">Meilleur prix</span>' : '' ?></td>
          <td class="text-end fw-semibold"><?= e(money($sp['price'])) ?></td><td class="text-end small <?= $diff > 0 ? 'text-danger' : '' ?>"><?= $diff > 0 ? '+' . e(number_fr($diff, 1)) . ' %' : '—' ?></td>
          <td class="text-end"><?= e(number_fr($sp['min_quantity'], 2)) ?></td><td class="small"><?= e(format_date($sp['valid_from'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Utilisation</div><div class="card-body">
    <div class="small text-body-secondary mb-1">Produits (nomenclature)</div>
    <?php if (!$products): ?><p class="small">Aucun produit.</p><?php else: foreach ($products as $p): ?>
      <div class="hoc-mini"><a class="text-reset" href="<?= e(url('products/' . $p['id'])) ?>"><?= e($p['name']) ?></a><span class="small"><?= e(number_fr($p['quantity'], 4)) ?> <?= e($m['symbol']) ?> / <?= e($p['symbol']) ?></span></div>
    <?php endforeach; endif; ?>
    <div class="small text-body-secondary mt-3 mb-1">Machines</div>
    <div><?= $machines ? implode(' ', array_map(static fn($ma) => '<span class="badge text-bg-light border">' . e($ma['name']) . '</span>', $machines)) : '<span class="small">Aucune.</span>' ?></div>
    <?php if ($m['width_cm'] || $m['notes']): ?><div class="small text-body-secondary mt-3 mb-1">Caractéristiques</div>
      <?php if ($m['width_cm']): ?><div class="small">Laize <?= e(number_fr($m['width_cm'], 1)) ?> cm<?= $m['length_m'] ? ' · rouleau de ' . e(number_fr($m['length_m'], 1)) . ' m' : '' ?></div><?php endif; ?>
      <?php if ($m['notes']): ?><div class="small" style="white-space:pre-line"><?= e($m['notes']) ?></div><?php endif; ?><?php endif; ?>
  </div></div></div>
  <div class="col-12"><div class="card"><div class="card-header fw-semibold d-flex justify-content-between">Derniers mouvements <a class="small text-decoration-none" href="<?= e(url('stock/movements', ['material' => $m['id']])) ?>">Historique complet</a></div>
    <?php \App\Core\View::partial('stock_movements', ['movements' => $movements, 'showItem' => false]); ?></div></div>
  <?php if (can('stock.materials')): ?><div class="col-12 text-end"><form method="post" action="<?= e(url('materials/' . $m['id'] . '/delete')) ?>" data-confirm="Supprimer cette matière ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer</button></form></div><?php endif; ?>
</div>
