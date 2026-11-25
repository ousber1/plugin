<?php /** Émission d'un avoir. Variables : $inv, $items, $customerName, $methods */
$oldQty = (array) old('qty', []); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Avoir sur la facture <?= e($inv['number']) ?></h1><p class="text-body-secondary mb-0"><?= e($customerName) ?> · facture de <?= e(money($inv['total'])) ?> TTC · reste dû <?= e(money($inv['balance_due'])) ?></p></div>
  <a class="btn btn-outline-secondary" href="<?= e(url('invoices/' . $inv['id'])) ?>"><i class="bi bi-arrow-left"></i> Retour à la facture</a>
</div>
<form method="post" action="<?= e(url('invoices/' . $inv['id'] . '/credit-note')) ?>" data-confirm="Émettre cet avoir ? C'est un document fiscal définitif (numéroté, non modifiable).">
  <?= csrf_field() ?>
  <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Lignes à créditer</span>
    <button type="button" class="btn btn-sm btn-outline-dark" id="creditAll">Tout créditer</button></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Désignation</th><th class="text-end">Facturé</th><th class="text-end">Déjà crédité</th><th class="text-end">PU HT</th><th style="width:150px">Quantité à créditer</th></tr></thead>
      <tbody><?php foreach ($items as $it): $avail = round((float) $it['quantity'] - (float) $it['credited'], 3); ?>
        <tr><td><?= e($it['description']) ?></td><td class="text-end text-nowrap"><?= e(number_fr($it['quantity'], 3)) ?> <?= e($it['symbol'] ?? '') ?></td>
          <td class="text-end"><?= (float) $it['credited'] > 0 ? e(number_fr($it['credited'], 3)) : '—' ?></td><td class="text-end"><?= e(number_fr($it['unit_price'])) ?></td>
          <td><?php if ($avail > 0): ?><input class="form-control form-control-sm js-cq" type="number" step="0.001" min="0" max="<?= e((string) $avail) ?>" data-max="<?= e((string) $avail) ?>" name="qty[<?= (int) $it['id'] ?>]" value="<?= e((string) ($oldQty[$it['id']] ?? '')) ?>" aria-label="Quantité à créditer"><?php else: ?><span class="small text-body-secondary">Entièrement crédité</span><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php if ((float) $inv['design_fees'] + (float) $inv['installation_fees'] + (float) $inv['transport_fees'] > 0): ?><div class="card-body small text-body-secondary border-top">Les frais (design, installation, transport) sont crédités lorsque la totalité de la facture est créditée.</div><?php endif; ?>
  </div>
  <div class="card mb-3"><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label" for="reason">Motif *</label><input class="form-control" id="reason" name="reason" required minlength="3" maxlength="255" value="<?= e(old('reason')) ?>" placeholder="Ex. retour client, erreur de prix, geste commercial…"></div>
    <div class="col-md-6"><label class="form-label">Utilisation du montant</label>
      <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="mApply" value="apply" <?= old('mode', 'apply') === 'apply' ? 'checked' : '' ?>><label class="form-check-label" for="mApply">Déduire du reste dû de cette facture <span class="text-body-secondary small">(excédent conservé au crédit du client)</span></label></div>
      <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="mKeep" value="keep" <?= old('mode') === 'keep' ? 'checked' : '' ?>><label class="form-check-label" for="mKeep">Conserver au crédit du compte client</label></div>
      <?php if (can('payments.refund')): ?><div class="form-check"><input class="form-check-input" type="radio" name="mode" id="mRefund" value="refund" <?= old('mode') === 'refund' ? 'checked' : '' ?>><label class="form-check-label" for="mRefund">Rembourser le client par</label>
        <select class="form-select form-select-sm d-inline-block w-auto ms-1" name="method_id" aria-label="Mode de remboursement"><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    </div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="restock" value="1" id="restock" <?= old('restock') ? 'checked' : '' ?>><label class="form-check-label" for="restock">Remettre en stock les articles retournés (produits suivis en stock)</label></div></div>
  </div></div>
  <button class="btn btn-accent"><i class="bi bi-file-earmark-minus"></i> Émettre l'avoir</button>
</form>
<script>
document.getElementById('creditAll').addEventListener('click', function () {
  document.querySelectorAll('.js-cq').forEach(function (i) { i.value = i.dataset.max; });
});
</script>
