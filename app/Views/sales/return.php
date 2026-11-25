<?php /** Retour sur une vente. Variables : $sale, $items, $refundable, $methods, $sessionOpen */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Retour sur le ticket <?= e($sale['number']) ?></h1>
    <p class="text-body-secondary mb-0"><?= e(\App\Controllers\SaleController::customerName($sale)) ?> · <?= e(format_date($sale['sale_date'], true)) ?> · total <?= e(money($sale['total'])) ?> · remboursable en paiement : <?= e(money($refundable)) ?></p></div>
  <a class="btn btn-outline-secondary" href="<?= e(url('sales/' . $sale['id'])) ?>"><i class="bi bi-arrow-left"></i> Retour à la vente</a>
</div>
<form method="post" action="<?= e(url('sales/' . $sale['id'] . '/return')) ?>" id="returnForm"><?= csrf_field() ?>
  <div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Article</th><th class="text-end">Vendu</th><th class="text-end">Déjà retourné</th><th style="width:140px">Quantité retournée</th><th class="text-center">Remettre en stock</th><th class="text-end">Montant TTC</th></tr></thead>
    <tbody><?php foreach ($items as $it): $avail = round((float) $it['quantity'] - (float) $it['returned_quantity'], 3); ?>
      <tr data-unit="<?= e((string) ((float) $it['line_total'] / max(0.001, (float) $it['quantity']))) ?>">
        <td><?= e($it['description']) ?></td><td class="text-end"><?= e(number_fr($it['quantity'], 3)) ?></td><td class="text-end"><?= e(number_fr($it['returned_quantity'], 3)) ?></td>
        <td><input class="form-control form-control-sm text-end" name="qty[<?= (int) $it['id'] ?>]" inputmode="decimal" placeholder="0" <?= $avail <= 0 ? 'disabled' : '' ?> data-max="<?= e((string) $avail) ?>" aria-label="Quantité retournée"></td>
        <td class="text-center"><?php if ($it['product_id'] || $it['pack_id']): ?><input class="form-check-input" type="checkbox" name="restock[<?= (int) $it['id'] ?>]" value="1" checked aria-label="Remettre en stock"><?php else: ?>—<?php endif; ?></td>
        <td class="text-end" data-amount>—</td></tr>
    <?php endforeach; ?></tbody>
    <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total du retour</td><td class="text-end" id="retTotal">0,00</td></tr></tfoot>
  </table></div></div>
  <div class="card"><div class="card-body row g-3 align-items-end">
    <div class="col-md-4"><label class="form-label" for="method_id">Remboursement</label><select class="form-select" id="method_id" name="method_id">
      <?php foreach ($methods as $m): if ((int) $m['affects_cash'] === 1 && !$sessionOpen) { continue; } if ((int) $m['is_credit'] === 1 && $sale['is_walk_in']) { continue; } ?>
        <option value="<?= (int) $m['id'] ?>"><?= (int) $m['is_credit'] === 1 ? 'Avoir sur le compte client (réduit sa dette)' : e($m['name']) ?></option>
      <?php endforeach; ?></select>
      <?php if (!$sessionOpen): ?><div class="form-text text-warning-emphasis">Caisse fermée : remboursement en espèces indisponible.</div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="reason">Motif *</label><input class="form-control" id="reason" name="reason" required minlength="3" maxlength="255" placeholder="Ex. défaut d'impression, mauvaise taille…"></div>
    <div class="col-md-2"><button class="btn btn-dark w-100" onclick="return confirm('Enregistrer ce retour ?')"><i class="bi bi-check2"></i> Valider</button></div>
  </div></div>
</form>
<script>
  document.getElementById('returnForm').addEventListener('input', function () {
    var total = 0;
    this.querySelectorAll('tbody tr').forEach(function (tr) {
      var i = tr.querySelector('input[name^=qty]'); if (!i) { return; }
      var q = Math.min(parseFloat((i.value || '0').replace(',', '.')) || 0, parseFloat(i.dataset.max));
      var a = Math.round(q * parseFloat(tr.dataset.unit) * 100) / 100; total += a;
      tr.querySelector('[data-amount]').textContent = q > 0 ? a.toFixed(2).replace('.', ',') : '—';
    });
    document.getElementById('retTotal').textContent = total.toFixed(2).replace('.', ',') + ' <?= e(setting('currency_symbol', 'DH')) ?>';
  });
</script>
