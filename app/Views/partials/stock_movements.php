<?php /** Tableau de mouvements de stock. Variables : $movements, $showItem (bool) */
$showCost = can('products.view_cost'); ?>
<?php if (!$movements): ?><div class="hoc-empty"><i class="bi bi-arrow-left-right"></i>Aucun mouvement.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Date</th><?php if ($showItem): ?><th>Article</th><?php endif; ?><th>Type</th><th class="text-end">Quantité</th><?php if ($showCost): ?><th class="text-end">Coût unit.</th><?php endif; ?><th class="text-end">Solde</th><th>Motif / référence</th><th>Par</th></tr></thead>
  <tbody><?php foreach ($movements as $m): $in = (float) $m['quantity'] > 0; ?>
    <tr>
      <td class="text-nowrap small"><?= e(format_date($m['created_at'], true)) ?></td>
      <?php if ($showItem): ?><td><?php if (!empty($m['product_id'])): ?><a class="text-reset" href="<?= e(url('products/' . $m['product_id'])) ?>"><?= e($m['item_name']) ?></a><?php else: ?><a class="text-reset" href="<?= e(url('materials/' . $m['material_id'])) ?>"><?= e($m['item_name']) ?></a> <span class="badge text-bg-light border">matière</span><?php endif; ?></td><?php endif; ?>
      <td><span class="badge <?= $in ? 'text-bg-success' : 'text-bg-light border' ?>"><i class="bi bi-arrow-<?= $in ? 'down' : 'up' ?>"></i> <?= e(\App\Services\StockService::label($m['movement_type'])) ?></span></td>
      <td class="text-end fw-semibold <?= $in ? 'text-success' : 'text-danger' ?>"><?= $in ? '+' : '' ?><?= e(number_fr($m['quantity'], 3)) ?></td>
      <?php if ($showCost): ?><td class="text-end small"><?= e(money($m['unit_cost'])) ?></td><?php endif; ?>
      <td class="text-end"><?= e(number_fr($m['balance_after'], 3)) ?></td>
      <td class="small"><?= e($m['reason'] ?? '') ?><?= $m['reference_type'] ? ' <span class="text-body-secondary">(' . e($m['reference_type']) . ($m['reference_id'] ? ' #' . (int) $m['reference_id'] : '') . ')</span>' : '' ?></td>
      <td class="small text-body-secondary"><?= e($m['full_name'] ?? '—') ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
