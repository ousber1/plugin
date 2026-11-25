<?php /** Fiche pack. Variables : $pack, $items */
$showCost = can('products.view_cost');
$value = (float) $pack['components_value']; $cost = (float) $pack['components_cost']; $price = (float) $pack['price'];
$today = date('Y-m-d');
$available = $pack['is_active'] && (!$pack['available_from'] || $pack['available_from'] <= $today) && (!$pack['available_until'] || $pack['available_until'] >= $today); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($pack['name']) ?> <?= $available ? '<span class="badge text-bg-success fs-6">Disponible</span>' : '<span class="badge text-bg-secondary fs-6">Non disponible</span>' ?></h1>
    <div class="text-body-secondary"><code><?= e($pack['code']) ?></code><?= $pack['is_custom'] ? ' · pack personnalisé' : '' ?><?= $pack['available_from'] || $pack['available_until'] ? ' · du ' . e(format_date($pack['available_from'])) . ' au ' . e(format_date($pack['available_until'])) : '' ?></div></div>
  <div class="d-flex gap-2">
    <?php if (can('packs.manage')): ?><a class="btn btn-outline-secondary" href="<?= e(url('packs/create', ['duplicate' => $pack['id']])) ?>"><i class="bi bi-copy"></i> Dupliquer</a>
      <a class="btn btn-dark" href="<?= e(url('packs/' . $pack['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Prix du pack</div><div class="hoc-kpi-value"><?= e(money($price)) ?></div><div class="hoc-kpi-sub">HT · <?= e(money($price * (1 + (float) $pack['tax_rate'] / 100))) ?> TTC</div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Valeur au détail</div><div class="hoc-kpi-value"><?= e(money($value)) ?></div><div class="hoc-kpi-sub"><?= $value > $price ? 'Économie client ' . e(money($value - $price)) : 'Aucune remise' ?></div></div></div></div>
  <?php if ($showCost): ?>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Coût des composants</div><div class="hoc-kpi-value"><?= e(money($cost)) ?></div><div class="hoc-kpi-sub">coûts unitaires du catalogue</div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Marge estimée</div><div class="hoc-kpi-value <?= $price - $cost < 0 ? 'text-danger' : '' ?>"><?= e(money($price - $cost)) ?></div><div class="hoc-kpi-sub"><?= $price > 0 ? e(number_fr(($price - $cost) / $price * 100, 1)) . ' % du prix' : '—' ?></div></div></div></div>
  <?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-header fw-semibold">Composants</div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Produit / service</th><th class="text-end">Quantité</th><th class="text-end">Prix unitaire</th><th class="text-end">Valeur</th><?php if ($showCost): ?><th class="text-end">Coût</th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($items as $it): ?><tr>
        <td><a class="text-reset" href="<?= e(url('products/' . $it['product_id'])) ?>"><?= e($it['name']) ?></a><?= !$it['is_active'] || $it['deleted_at'] ? ' <span class="badge text-bg-danger">inactif</span>' : '' ?><?= $it['description'] ? '<br><small class="text-body-secondary">' . e($it['description']) . '</small>' : '' ?></td>
        <td class="text-end"><?= e(number_fr($it['quantity'], 3)) ?> <?= e($it['symbol']) ?></td><td class="text-end"><?= e(money($it['selling_price'])) ?></td>
        <td class="text-end"><?= e(money((float) $it['quantity'] * (float) $it['selling_price'])) ?></td><?php if ($showCost): ?><td class="text-end text-body-secondary"><?= e(money((float) $it['quantity'] * (float) $it['unit_cost'])) ?></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
  <div class="col-lg-4">
    <?php if ($pack['image']): ?><div class="card mb-3"><img src="<?= e(asset($pack['image'])) ?>" alt="" class="card-img-top"></div><?php endif; ?>
    <div class="card"><div class="card-header fw-semibold">Description</div><div class="card-body" style="white-space:pre-line"><?= $pack['description'] ? e($pack['description']) : '<span class="text-body-secondary">—</span>' ?></div></div>
    <?php if (can('packs.manage')): ?><form class="text-end mt-3" method="post" action="<?= e(url('packs/' . $pack['id'] . '/delete')) ?>" data-confirm="Supprimer ce pack ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer le pack</button></form><?php endif; ?>
  </div>
</div>
