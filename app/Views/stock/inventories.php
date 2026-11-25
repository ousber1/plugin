<?php /** Liste des inventaires. Variables : $counts, $materialTypes */
$open = array_values(array_filter($counts, static fn($c) => $c['status'] === 'draft')); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Inventaires</h1><p class="text-body-secondary mb-0">Comptage physique et régularisation tracée des écarts.</p></div>
  <a href="<?= e(url('stock')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Stock</a>
</div>
<?php if (!$open && can('stock.inventory')): ?>
  <form method="post" action="<?= e(url('stock/inventories')) ?>" class="card mb-4"><div class="card-body row g-3 align-items-end"><?= csrf_field() ?>
    <div class="col-md-3"><label class="form-label" for="scope">Périmètre</label><select class="form-select" id="scope" name="scope"><option value="materials">Matières premières</option><option value="products">Produits stockés</option><option value="all">Tout le stock</option></select></div>
    <div class="col-md-3"><label class="form-label" for="material_type">Type de matière</label><select class="form-select" id="material_type" name="material_type"><option value="">Tous les types</option><?php foreach ($materialTypes as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label" for="notes">Note</label><input class="form-control" id="notes" name="notes" maxlength="500" placeholder="Ex. inventaire de fin de mois"></div>
    <div class="col-md-2"><button class="btn btn-dark w-100"><i class="bi bi-clipboard-plus"></i> Démarrer</button></div>
  </div></form>
<?php elseif ($open): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center"><span><i class="bi bi-hourglass-split"></i> L'inventaire <strong><?= e($open[0]['number']) ?></strong> est en cours (<?= (int) $open[0]['counted'] ?>/<?= (int) $open[0]['items'] ?> articles comptés).</span>
    <a class="btn btn-sm btn-dark" href="<?= e(url('stock/inventories/' . $open[0]['id'])) ?>">Continuer le comptage</a></div>
<?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Numéro</th><th>Date</th><th>Statut</th><th class="text-end">Articles</th><th class="text-end">Comptés</th><?php if (can('products.view_cost')): ?><th class="text-end">Écart valorisé</th><?php endif; ?><th>Par</th><th></th></tr></thead>
  <tbody>
  <?php if (!$counts): ?><tr><td colspan="8" class="hoc-empty"><i class="bi bi-clipboard"></i>Aucun inventaire réalisé.</td></tr><?php endif; ?>
  <?php foreach ($counts as $c): ?>
    <tr><td><code><?= e($c['number']) ?></code></td><td><?= e(format_date($c['count_date'])) ?></td><td><?= status_badge('inventory', $c['status']) ?></td>
      <td class="text-end"><?= (int) $c['items'] ?></td><td class="text-end"><?= (int) $c['counted'] ?></td>
      <?php if (can('products.view_cost')): ?><td class="text-end <?= (float) $c['value_diff'] < 0 ? 'text-danger' : '' ?>"><?= e(money($c['value_diff'])) ?></td><?php endif; ?>
      <td class="small"><?= e($c['full_name'] ?? '') ?><?= $c['validator'] ? '<br><span class="text-body-secondary">validé par ' . e($c['validator']) . '</span>' : '' ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('stock/inventories/' . $c['id'])) ?>"><i class="bi bi-eye"></i></a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
