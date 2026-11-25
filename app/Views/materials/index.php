<?php /** Liste des matières. Variables : $materials, $p, $f, $suppliers, $valuation */
$types = \App\Controllers\MaterialController::TYPES;
$showCost = can('products.view_cost'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Matières premières</h1>
    <p class="text-body-secondary mb-0">Consommables de production<?= $showCost ? ' · valeur du stock matières : <strong>' . e(money($valuation['materials'])) . '</strong>' : '' ?></p></div>
  <div class="d-flex gap-2">
    <a href="<?= e(url('materials/export', current_query())) ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Exporter</a>
    <?php if (can('stock.materials')): ?><a href="<?= e(url('materials/create')) ?>" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Nouvelle matière</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('materials')) ?>"><?= route_field('materials') ?>
    <div class="flex-grow-1"><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nom, code, code-barres…" aria-label="Rechercher"></div>
    <select class="form-select w-auto" name="type" aria-label="Type"><option value="">Tous les types</option><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="supplier" aria-label="Fournisseur"><option value="0">Tous les fournisseurs</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $f['supplier'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="stock" aria-label="Stock"><option value="">Tout stock</option><option value="low" <?= $f['stock'] === 'low' ? 'selected' : '' ?>>Stock faible</option><option value="out" <?= $f['stock'] === 'out' ? 'selected' : '' ?>>Rupture</option></select>
    <select class="form-select w-auto" name="status" aria-label="Statut"><?php foreach (['active' => 'Actives', 'inactive' => 'Inactives', 'all' => 'Toutes'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th><?= sort_link('Code', 'code', $f) ?></th><th><?= sort_link('Matière', 'name', $f) ?></th><th><?= sort_link('Type', 'type', $f) ?></th><th>Fournisseur</th>
      <th class="text-end"><?= sort_link('Stock', 'stock', $f) ?></th><th class="text-end">Minimum</th><?php if ($showCost): ?><th class="text-end">Coût moyen</th><th class="text-end"><?= sort_link('Valeur', 'value', $f) ?></th><?php endif; ?><th class="text-end"></th></tr></thead>
    <tbody>
    <?php if (!$materials): ?><tr><td colspan="9" class="hoc-empty"><i class="bi bi-boxes"></i>Aucune matière trouvée.</td></tr><?php endif; ?>
    <?php foreach ($materials as $m): $low = (float) $m['min_stock'] > 0 && (float) $m['stock_quantity'] <= (float) $m['min_stock']; ?>
      <tr>
        <td><code><?= e($m['code']) ?></code></td>
        <td><a class="fw-semibold text-reset text-decoration-none" href="<?= e(url('materials/' . $m['id'])) ?>"><?= e($m['name']) ?></a><?= $m['is_active'] ? '' : ' <span class="badge text-bg-secondary">Inactive</span>' ?>
          <?= $m['width_cm'] ? '<br><small class="text-body-secondary">Laize ' . e(number_fr($m['width_cm'], 0)) . ' cm' . ($m['length_m'] ? ' × ' . e(number_fr($m['length_m'], 0)) . ' m' : '') . '</small>' : '' ?></td>
        <td><?= e($types[$m['material_type']] ?? $m['material_type']) ?></td>
        <td class="small"><?= e($m['supplier_name'] ?? '—') ?></td>
        <td class="text-end fw-semibold text-nowrap <?= (float) $m['stock_quantity'] <= 0 ? 'text-danger' : ($low ? 'text-warning-emphasis' : '') ?>"><?= $low ? '<i class="bi bi-exclamation-triangle"></i> ' : '' ?><?= e(number_fr($m['stock_quantity'], 2)) ?> <?= e($m['symbol']) ?></td>
        <td class="text-end text-body-secondary"><?= e(number_fr($m['min_stock'], 2)) ?></td>
        <?php if ($showCost): ?><td class="text-end"><?= e(money($m['unit_cost'])) ?></td><td class="text-end"><?= e(money(max(0, (float) $m['stock_quantity']) * (float) $m['unit_cost'])) ?></td><?php endif; ?>
        <td class="text-end text-nowrap">
          <?php if (can('stock.movements')): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('stock/movements/create', ['material' => $m['id']])) ?>" title="Mouvement de stock"><i class="bi bi-arrow-left-right"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('materials/' . $m['id'])) ?>" title="Fiche"><i class="bi bi-eye"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
