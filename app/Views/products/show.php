<?php /** Fiche produit. Variables : $product, $prices, $bom, $packs, $movements, $sales */
$p = $product;
$types = \App\Controllers\ProductController::TYPES;
$showCost = can('products.view_cost');
$unitCost = (float) $p['estimated_cost'] > 0 ? (float) $p['estimated_cost'] : (float) $p['purchase_price'];
$bomCost = 0.0;
foreach ($bom as $b) { $bomCost += (float) $b['quantity'] * (1 + (float) $b['waste_percent'] / 100) * (float) $b['unit_cost']; }
$ttc = (float) $p['selling_price'] * (1 + (float) $p['tax_rate'] / 100);
?>
<div class="hoc-page-head">
  <div class="d-flex gap-3 align-items-center">
    <div class="hoc-thumb hoc-thumb-lg"><?php if ($p['image']): ?><img src="<?= e(asset($p['image'])) ?>" alt=""><?php else: ?><i class="bi bi-box"></i><?php endif; ?></div>
    <div><h1 class="h3 fw-bold mb-1"><?= e($p['name']) ?> <?= $p['is_active'] ? '' : '<span class="badge text-bg-secondary fs-6">Inactif</span>' ?></h1>
      <div class="text-body-secondary"><code><?= e($p['code']) ?></code> · <?= e($types[$p['product_type']] ?? $p['product_type']) ?><?= $p['category_name'] ? ' · ' . e($p['category_name']) : '' ?><?= $p['barcode'] ? ' · <i class="bi bi-upc"></i> ' . e($p['barcode']) : '' ?></div></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($p['track_stock'] && can('stock.movements')): ?><a class="btn btn-outline-secondary" href="<?= e(url('stock/movements/create', ['product' => $p['id']])) ?>"><i class="bi bi-arrow-left-right"></i> Mouvement de stock</a><?php endif; ?>
    <?php if (can('products.create')): ?><a class="btn btn-outline-secondary" href="<?= e(url('products/create', ['duplicate' => $p['id']])) ?>"><i class="bi bi-copy"></i> Dupliquer</a><?php endif; ?>
    <?php if (can('products.edit')): ?><a class="btn btn-dark" href="<?= e(url('products/' . $p['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Prix de vente</div>
    <div class="hoc-kpi-value"><?= e(money($p['selling_price'])) ?></div><div class="hoc-kpi-sub">HT / <?= e($p['unit_symbol']) ?> · <?= e(money($ttc)) ?> TTC (<?= e($p['tax_name'] ?? 'TVA') ?>)</div></div></div></div>
  <?php if ($showCost): ?><div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Coût et marge</div>
    <div class="hoc-kpi-value <?= (float) $p['margin_percent'] < 0 ? 'text-danger' : '' ?>"><?= (float) $p['selling_price'] > 0 && $unitCost > 0 ? e(number_fr($p['margin_percent'], 1)) . ' %' : '—' ?></div>
    <div class="hoc-kpi-sub"><?= $unitCost > 0 ? 'Coût unitaire ' . e(money($unitCost)) : 'Aucun coût renseigné' ?><?= $bom ? ' · matières ' . e(money($bomCost)) : '' ?></div></div></div></div><?php endif; ?>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Stock</div>
    <?php if ($p['track_stock']): $low = (float) $p['stock_quantity'] <= (float) $p['min_stock']; ?>
      <div class="hoc-kpi-value <?= (float) $p['stock_quantity'] <= 0 ? 'text-danger' : ($low ? 'text-warning-emphasis' : '') ?>"><?= e(number_fr($p['stock_quantity'], 2)) ?> <small class="fs-6"><?= e($p['unit_symbol']) ?></small></div>
      <div class="hoc-kpi-sub">Minimum <?= e(number_fr($p['min_stock'], 2)) ?><?= $showCost ? ' · valeur ' . e(money(max(0, (float) $p['stock_quantity']) * (float) $p['purchase_price'])) : '' ?></div>
    <?php else: ?><div class="hoc-kpi-value text-body-secondary fs-5">Non suivi</div><div class="hoc-kpi-sub">Fabriqué / réalisé à la demande</div><?php endif; ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Ventes <?= date('Y') ?></div>
    <div class="hoc-kpi-value"><?= e(money($sales['revenue'])) ?></div><div class="hoc-kpi-sub"><?= e(number_fr($sales['qty'], 2)) ?> <?= e($p['unit_symbol']) ?> vendus<?= $showCost && (float) $sales['revenue'] > 0 ? ' · marge ' . e(money((float) $sales['revenue'] - (float) $sales['cost'])) : '' ?></div></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Détails</div><div class="card-body">
    <?php foreach ([['Désignation documents', $p['name_fr'] ?: $p['name']], ['Unité', $p['unit_name'] . ' (' . $p['unit_symbol'] . ')'], ['Prix minimum', money($p['min_selling_price'])],
        ['Machine', $p['machine_name']], ['Matière principale', $p['material_name']], ['Fournisseur', $p['supplier_name']],
        ['Prix modifiable à la vente', $p['allow_price_override'] ? 'Oui' : 'Non'], ['Bouton rapide POS', $p['is_quick'] ? 'Oui' : 'Non']] as [$l, $val]): ?>
      <div class="hoc-mini"><span class="text-body-secondary"><?= e($l) ?></span><span class="text-end"><?= e($val ?: '—') ?></span></div>
    <?php endforeach; ?>
    <?php if ($p['description']): ?><p class="mt-3 mb-0 small" style="white-space:pre-line"><?= e($p['description']) ?></p><?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="card mb-3"><div class="card-header fw-semibold">Listes de prix (HT)</div><ul class="list-group list-group-flush">
    <li class="list-group-item d-flex justify-content-between"><span>Détail</span><strong><?= e(money($p['selling_price'])) ?></strong></li>
    <?php foreach ($prices as $pl): if ((int) $pl['id'] === 1) { continue; } ?><li class="list-group-item d-flex justify-content-between"><span><?= e($pl['name']) ?></span><span><?= $pl['price'] !== null ? '<strong>' . e(money($pl['price'])) . '</strong>' : '<span class="text-body-secondary">= détail</span>' ?></span></li><?php endforeach; ?>
  </ul></div>
  <div class="card"><div class="card-header fw-semibold">Nomenclature (par <?= e($p['unit_symbol']) ?>)</div>
    <?php if (!$bom): ?><div class="hoc-empty"><i class="bi bi-diagram-3"></i>Aucune matière associée.</div><?php else: ?>
    <ul class="list-group list-group-flush"><?php foreach ($bom as $b): ?>
      <li class="list-group-item d-flex justify-content-between"><a class="text-reset" href="<?= e(url('materials/' . $b['material_id'])) ?>"><?= e($b['name']) ?></a>
        <span class="small"><?= e(number_fr($b['quantity'], 4)) ?> <?= e($b['symbol']) ?><?= (float) $b['waste_percent'] > 0 ? ' + ' . e(number_fr($b['waste_percent'], 1)) . ' % gâche' : '' ?><?= $showCost ? ' · ' . e(money((float) $b['quantity'] * (1 + (float) $b['waste_percent'] / 100) * (float) $b['unit_cost'])) : '' ?></span></li>
    <?php endforeach; ?></ul><?php endif; ?></div></div>
  <?php if ($packs): ?><div class="col-12"><div class="card"><div class="card-header fw-semibold">Inclus dans les packs</div><ul class="list-group list-group-flush">
    <?php foreach ($packs as $pk): ?><li class="list-group-item d-flex justify-content-between"><a class="text-reset" href="<?= e(url('packs/' . $pk['id'])) ?>"><code><?= e($pk['code']) ?></code> <?= e($pk['name']) ?></a><span>× <?= e(number_fr($pk['quantity'], 2)) ?></span></li><?php endforeach; ?>
  </ul></div></div><?php endif; ?>
  <?php if ($p['track_stock']): ?><div class="col-12"><div class="card"><div class="card-header fw-semibold d-flex justify-content-between">Derniers mouvements de stock <a class="small text-decoration-none" href="<?= e(url('stock/movements', ['product' => $p['id']])) ?>">Historique complet</a></div>
    <?php \App\Core\View::partial('stock_movements', ['movements' => $movements, 'showItem' => false]); ?></div></div><?php endif; ?>
  <?php if (can('products.delete')): ?><div class="col-12 text-end"><form method="post" action="<?= e(url('products/' . $p['id'] . '/delete')) ?>" data-confirm="Supprimer « <?= e($p['name']) ?> » du catalogue ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer du catalogue</button></form></div><?php endif; ?>
</div>
