<?php /** Feuille de comptage. Variables : $count, $items */
$draft = $count['status'] === 'draft';
$showCost = can('products.view_cost'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Inventaire <?= e($count['number']) ?> <?= status_badge('inventory', $count['status']) ?></h1>
    <p class="text-body-secondary mb-0">Ouvert le <?= e(format_date($count['created_at'], true)) ?> par <?= e($count['full_name'] ?? '—') ?><?= $count['validated_at'] ? ' · validé le ' . e(format_date($count['validated_at'], true)) . ' par ' . e($count['validator']) : '' ?><?= $count['notes'] ? ' · ' . e($count['notes']) : '' ?></p></div>
  <div class="d-flex gap-2"><button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Feuille de comptage</button>
    <a href="<?= e(url('stock/inventories')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Inventaires</a></div>
</div>
<?php if ($draft): ?><div class="alert alert-info small"><i class="bi bi-info-circle"></i> Saisissez la quantité réellement comptée. Laissez vide les articles non comptés : ils ne seront pas modifiés. À la validation, l'écart (compté − théorique) est enregistré comme mouvement « Inventaire ».</div><?php endif; ?>
<form method="post" action="<?= e(url('stock/inventories/' . $count['id'])) ?>" id="invForm"><?= csrf_field() ?>
  <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Code</th><th>Article</th><th>Nature</th><th class="text-end">Théorique</th><th style="width:160px">Compté</th><th class="text-end">Écart</th><?php if ($showCost): ?><th class="text-end">Écart valorisé</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($items as $it): $diff = $it['difference'] !== null ? (float) $it['difference'] : null; ?>
      <tr data-sys="<?= e((string) (float) $it['system_quantity']) ?>" data-cost="<?= e((string) (float) $it['unit_cost']) ?>">
        <td><code><?= e($it['code']) ?></code></td><td class="fw-semibold"><?= e($it['name']) ?></td><td class="small"><?= e($it['kind']) ?></td>
        <td class="text-end"><?= e(number_fr($it['system_quantity'], 3)) ?> <?= e($it['symbol']) ?></td>
        <td><?php if ($draft): ?><input class="form-control form-control-sm text-end" name="counted[<?= (int) $it['id'] ?>]" inputmode="decimal" value="<?= $it['counted_quantity'] !== null ? e(number_fr($it['counted_quantity'], 3)) : '' ?>" aria-label="Quantité comptée" data-count><?php else: ?><?= $it['counted_quantity'] !== null ? e(number_fr($it['counted_quantity'], 3)) : '<span class="text-body-secondary">non compté</span>' ?><?php endif; ?></td>
        <td class="text-end fw-semibold" data-diff><?= $diff === null ? '—' : '<span class="' . ($diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-success' : '')) . '">' . ($diff > 0 ? '+' : '') . e(number_fr($diff, 3)) . '</span>' ?></td>
        <?php if ($showCost): ?><td class="text-end small" data-val><?= $diff === null ? '—' : e(money($diff * (float) $it['unit_cost'])) ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($draft): ?><div class="card-footer bg-transparent d-flex flex-wrap justify-content-between gap-2">
      <button type="submit" class="btn btn-outline-danger" form="cancelInv"><i class="bi bi-x-lg"></i> Annuler l'inventaire</button>
      <div class="d-flex gap-2"><button class="btn btn-outline-dark" name="action" value="save"><i class="bi bi-save"></i> Enregistrer le comptage</button>
        <?php if (can('stock.inventory')): ?><button class="btn btn-dark" name="action" value="validate" onclick="return confirm('Valider l\'inventaire ? Les écarts seront appliqués au stock et ne pourront pas être annulés.')"><i class="bi bi-check2-all"></i> Valider et ajuster le stock</button><?php endif; ?></div>
    </div><?php endif; ?>
  </div>
</form>
<?php if ($draft): ?><form method="post" action="<?= e(url('stock/inventories/' . $count['id'] . '/cancel')) ?>" id="cancelInv" data-confirm="Annuler cet inventaire ? Aucun stock ne sera modifié."><?= csrf_field() ?></form>
<script>
  document.getElementById('invForm').addEventListener('input', function (e) {
    if (!e.target.matches('[data-count]')) { return; }
    var tr = e.target.closest('tr'), v = parseFloat(e.target.value.replace(/\s/g, '').replace(',', '.')), cell = tr.querySelector('[data-diff]'), val = tr.querySelector('[data-val]');
    if (isNaN(v)) { cell.textContent = '—'; if (val) { val.textContent = '—'; } return; }
    var d = Math.round((v - parseFloat(tr.dataset.sys)) * 1000) / 1000;
    cell.innerHTML = '<span class="' + (d < 0 ? 'text-danger' : (d > 0 ? 'text-success' : '')) + '">' + (d > 0 ? '+' : '') + String(d).replace('.', ',') + '</span>';
    if (val) { val.textContent = (d * parseFloat(tr.dataset.cost)).toFixed(2).replace('.', ',') + ' <?= e(setting('currency_symbol', 'DH')) ?>'; }
  });
</script><?php endif; ?>
