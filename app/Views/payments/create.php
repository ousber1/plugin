<?php
/** Saisie d'un règlement client. Variables : $customer, $open, $customerName, $balance, $focusInvoice, $focusSale, $methods, $cashOpen */
$oldInv = (array) old('inv', []); $oldSale = (array) old('sale', []);
?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Encaisser un règlement</h1><p class="text-body-secondary mb-0">Imputez le montant reçu sur les factures et tickets à crédit du client ; le surplus est conservé comme avance.</p></div>
</div>
<div class="card mb-3"><form class="card-body d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= e(url('payments/create')) ?>"><?= route_field('payments/create') ?>
  <div class="flex-grow-1" style="min-width:260px"><label class="form-label">Client</label>
    <div class="hoc-lookup" data-lookup="<?= e(url('customers/lookup')) ?>" id="payCustomer">
      <input class="form-control" data-lookup-input autocomplete="off" placeholder="Rechercher un client…" value="<?= e($customer ? $customer['code'] . ' — ' . $customerName : '') ?>">
      <input type="hidden" name="customer" data-lookup-value value="<?= e((string) ($customer['id'] ?? '')) ?>">
    </div></div>
  <button class="btn btn-outline-dark">Afficher les documents dus</button>
</form></div>
<script>document.getElementById('payCustomer').addEventListener('hoc:lookup', function () { this.closest('form').submit(); });</script>
<?php if ($customer): ?>
<form method="post" action="<?= e(url('payments')) ?>" id="payForm"><?= csrf_field() ?>
  <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Documents à régler — <?= e($customerName) ?></span>
        <span class="small">Solde du compte : <strong class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($balance)) ?></strong></span></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
          <thead><tr><th>Document</th><th>Date</th><th>Échéance</th><th class="text-end">Reste dû</th><th style="width:160px">Montant imputé</th></tr></thead>
          <tbody>
          <?php if (!$open['invoices'] && !$open['sales']): ?><tr><td colspan="5" class="hoc-empty"><i class="bi bi-check2-circle"></i>Aucun document en attente de règlement. Le montant sera enregistré comme avance.</td></tr><?php endif; ?>
          <?php foreach ($open['invoices'] as $i): ?>
            <tr class="<?= $focusInvoice === (int) $i['id'] ? 'table-warning' : '' ?>"><td><a href="<?= e(url('invoices/' . $i['id'])) ?>" target="_blank"><?= e($i['number']) ?></a> <?= status_badge('invoice', $i['status']) ?></td>
              <td class="small"><?= e(format_date($i['issue_date'])) ?></td><td class="small <?= $i['status'] === 'overdue' ? 'text-danger' : '' ?>"><?= e(format_date($i['due_date'])) ?></td>
              <td class="text-end fw-semibold"><?= e(money($i['balance_due'])) ?></td>
              <td><input class="form-control form-control-sm text-end js-alloc" type="number" step="0.01" min="0" max="<?= e((string) $i['balance_due']) ?>" data-due="<?= e((string) $i['balance_due']) ?>" data-focus="<?= $focusInvoice === (int) $i['id'] ? '1' : '' ?>" name="inv[<?= (int) $i['id'] ?>]" value="<?= e((string) ($oldInv[$i['id']] ?? '')) ?>" aria-label="Montant imputé sur <?= e($i['number']) ?>"></td></tr>
          <?php endforeach; ?>
          <?php foreach ($open['sales'] as $s): ?>
            <tr class="<?= $focusSale === (int) $s['id'] ? 'table-warning' : '' ?>"><td><a href="<?= e(url('sales/' . $s['id'])) ?>" target="_blank">Ticket <?= e($s['number']) ?></a> <span class="badge text-bg-light border">à crédit</span></td>
              <td class="small"><?= e(format_date($s['sale_date'])) ?></td><td class="small">—</td>
              <td class="text-end fw-semibold"><?= e(money($s['due'])) ?></td>
              <td><input class="form-control form-control-sm text-end js-alloc" type="number" step="0.01" min="0" max="<?= e((string) $s['due']) ?>" data-due="<?= e((string) $s['due']) ?>" data-focus="<?= $focusSale === (int) $s['id'] ? '1' : '' ?>" name="sale[<?= (int) $s['id'] ?>]" value="<?= e((string) ($oldSale[$s['id']] ?? '')) ?>" aria-label="Montant imputé sur le ticket <?= e($s['number']) ?>"></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php if ($open['advances']): ?><div class="card-body border-top small">Avances déjà disponibles :
          <?php foreach ($open['advances'] as $a): ?><a href="<?= e(url('payments/' . $a['id'])) ?>"><?= e($a['number']) ?></a> (<?= e(money($a['unallocated_amount'])) ?>) <?php endforeach; ?> — imputez-les depuis la fiche du reçu ou de la facture.</div><?php endif; ?>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card hoc-sticky"><div class="card-header fw-semibold">Paiement reçu</div><div class="card-body">
        <label class="form-label" for="amount">Montant reçu *</label>
        <div class="input-group mb-2"><input class="form-control form-control-lg text-end" type="number" step="0.01" min="0.01" id="amount" name="amount" required value="<?= e(old('amount')) ?>"><span class="input-group-text">DH</span></div>
        <button type="button" class="btn btn-sm btn-outline-dark mb-3" id="autoAlloc"><i class="bi bi-magic"></i> Imputer automatiquement (plus anciens d'abord)</button>
        <label class="form-label" for="method_id">Mode de paiement *</label>
        <select class="form-select mb-3" id="method_id" name="method_id" required>
          <?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>" data-ref="<?= (int) $m['requires_reference'] ?>" data-cash="<?= (int) $m['affects_cash'] ?>" <?= (int) old('method_id', 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
        </select>
        <div class="alert alert-warning small py-2 d-none" id="cashClosed"><i class="bi bi-exclamation-triangle"></i> La caisse est fermée : un paiement en espèces nécessite une caisse ouverte.</div>
        <label class="form-label" for="reference">Référence <span class="small text-body-secondary" id="refHint"></span></label>
        <input class="form-control mb-3" id="reference" name="reference" maxlength="100" value="<?= e(old('reference')) ?>" placeholder="N° de chèque, de virement, de transaction…">
        <label class="form-label" for="payment_date">Date</label>
        <input class="form-control mb-3" type="date" id="payment_date" name="payment_date" max="<?= date('Y-m-d') ?>" value="<?= e(old('payment_date', date('Y-m-d'))) ?>">
        <label class="form-label" for="notes">Note</label>
        <input class="form-control mb-3" id="notes" name="notes" maxlength="500" value="<?= e(old('notes')) ?>">
        <div class="hoc-mini"><span>Imputé</span><strong id="sumAlloc">0,00 DH</strong></div>
        <div class="hoc-mini"><span>Avance (non imputée)</span><strong id="sumAdv">0,00 DH</strong></div>
        <div class="alert alert-danger small py-2 mt-2 d-none" id="allocErr"></div>
        <div class="d-grid mt-3"><button class="btn btn-accent btn-lg" id="paySubmit"><i class="bi bi-check2"></i> Enregistrer le règlement</button></div>
      </div></div>
    </div>
  </div>
</form>
<script>
(function () {
  var form = document.getElementById('payForm'), amount = document.getElementById('amount'), inputs = [].slice.call(form.querySelectorAll('.js-alloc'));
  var fmt = function (n) { return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/ /g, ' ') + ' DH'; };
  var cashOpen = <?= $cashOpen ? 'true' : 'false' ?>;
  function sum() {
    var s = inputs.reduce(function (t, i) { return t + (parseFloat(i.value) || 0); }, 0), a = parseFloat(amount.value) || 0;
    document.getElementById('sumAlloc').textContent = fmt(s);
    document.getElementById('sumAdv').textContent = fmt(Math.max(0, a - s));
    var err = document.getElementById('allocErr'), bad = inputs.some(function (i) { return (parseFloat(i.value) || 0) > parseFloat(i.dataset.due) + 0.004; });
    err.textContent = bad ? 'Un montant imputé dépasse le reste dû du document.' : (s > a + 0.004 ? 'La somme des imputations dépasse le montant reçu.' : '');
    err.classList.toggle('d-none', !err.textContent);
    document.getElementById('paySubmit').disabled = !!err.textContent;
  }
  function auto() {
    var left = parseFloat(amount.value) || 0;
    var ordered = inputs.filter(function (i) { return i.dataset.focus; }).concat(inputs.filter(function (i) { return !i.dataset.focus; }));
    ordered.forEach(function (i) { var v = Math.min(left, parseFloat(i.dataset.due)); v = Math.round(v * 100) / 100; i.value = v > 0 ? v.toFixed(2) : ''; left = Math.round((left - v) * 100) / 100; });
    sum();
  }
  function method() {
    var o = document.getElementById('method_id').selectedOptions[0];
    document.getElementById('refHint').textContent = o && o.dataset.ref === '1' ? '(obligatoire)' : '';
    document.getElementById('reference').required = !!(o && o.dataset.ref === '1');
    document.getElementById('cashClosed').classList.toggle('d-none', !(o && o.dataset.cash === '1' && !cashOpen));
  }
  amount.addEventListener('input', function () { if (!inputs.some(function (i) { return i.dataset.touched; })) { auto(); } else { sum(); } });
  inputs.forEach(function (i) { i.addEventListener('input', function () { i.dataset.touched = '1'; sum(); }); });
  document.getElementById('autoAlloc').addEventListener('click', function () { inputs.forEach(function (i) { delete i.dataset.touched; }); auto(); });
  document.getElementById('method_id').addEventListener('change', method);
  var f = inputs.filter(function (i) { return i.dataset.focus; })[0];
  if (f && !amount.value) { amount.value = parseFloat(f.dataset.due).toFixed(2); auto(); }
  method(); sum();
})();
</script>
<?php endif; ?>
