<?php /** Formulaire pack. Variables : $pack, $values, $items, $taxes */
$isEdit = $pack !== null;
$v = static fn(string $k, $d = '') => old($k, $values[$k] ?? $d);
$defaultTax = (int) setting('default_tax_id', 1);
$showCost = can('products.view_cost'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1><p class="text-body-secondary mb-0">La valeur et le coût sont calculés à partir des prix actuels du catalogue.</p></div>
  <a href="<?= e(url($isEdit ? 'packs/' . $pack['id'] : 'packs')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" action="<?= e(url($isEdit ? 'packs/' . $pack['id'] : 'packs')) ?>" enctype="multipart/form-data" id="packForm"><?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card mb-4"><div class="card-body row g-3">
        <div class="col-md-8"><label class="form-label" for="name">Nom du pack *</label><input class="form-control" id="name" name="name" value="<?= e($v('name')) ?>" required maxlength="190" autofocus placeholder="Ex. RESTAURANT PRO PACK"></div>
        <div class="col-md-4"><label class="form-label" for="code">Code</label><input class="form-control text-uppercase" id="code" name="code" value="<?= e($v('code')) ?>" maxlength="40" placeholder="Automatique"></div>
        <div class="col-12"><label class="form-label" for="description">Description commerciale</label><textarea class="form-control" id="description" name="description" rows="3"><?= e($v('description')) ?></textarea></div>
      </div></div>
      <div class="card"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">Composants <button type="button" class="btn btn-sm btn-outline-dark" id="itemAdd"><i class="bi bi-plus-lg"></i> Ajouter</button></div>
        <div class="card-body" id="itemRows">
          <?php foreach (array_merge($items, [null]) as $it): ?>
            <div class="row g-2 mb-2 align-items-center" data-item<?= $it ? '' : ' data-template hidden' ?> data-price="<?= e((string) ($it['selling_price'] ?? 0)) ?>" data-cost="<?= e((string) ($it['unit_cost'] ?? 0)) ?>">
              <div class="col-md-5"><div class="hoc-lookup" data-lookup="<?= e(url('products/lookup')) ?>">
                <input class="form-control form-control-sm" type="search" placeholder="Produit ou service…" autocomplete="off" data-lookup-input value="<?= $it ? e($it['code'] . ' — ' . $it['name']) : '' ?>">
                <input type="hidden" name="items[product_id][]" data-lookup-value value="<?= (int) ($it['product_id'] ?? 0) ?: '' ?>"></div></div>
              <div class="col-4 col-md-2"><input class="form-control form-control-sm" name="items[quantity][]" inputmode="decimal" placeholder="Qté" value="<?= $it ? e(number_fr($it['quantity'], 3)) : '1' ?>" data-qty aria-label="Quantité"></div>
              <div class="col-8 col-md-4"><input class="form-control form-control-sm" name="items[description][]" maxlength="255" placeholder="Précision (ex. recto-verso 350 g)" value="<?= e($it['description'] ?? '') ?>" aria-label="Précision"></div>
              <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Retirer"><i class="bi bi-x-lg"></i></button></div>
            </div>
          <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-xl-4">
      <div class="card mb-4"><div class="card-header fw-semibold">Prix</div><div class="card-body row g-3">
        <div class="col-12"><div class="hoc-mini"><span>Valeur des composants</span><strong id="sumValue">—</strong></div>
          <?php if ($showCost): ?><div class="hoc-mini"><span>Coût des composants</span><span id="sumCost">—</span></div><?php endif; ?></div>
        <div class="col-6"><label class="form-label" for="discount_percent">Remise %</label><input class="form-control" id="discount_percent" name="discount_percent" inputmode="decimal" value="<?= e(number_fr($v('discount_percent', 0))) ?>"></div>
        <div class="col-6"><label class="form-label" for="price">Prix du pack HT *</label><input class="form-control" id="price" name="price" inputmode="decimal" value="<?= e(number_fr($v('price', 0))) ?>" required></div>
        <div class="col-12"><button type="button" class="btn btn-sm btn-outline-dark w-100" id="applyDiscount">Calculer le prix : valeur − remise</button></div>
        <?php if ($showCost): ?><div class="col-12"><div class="alert alert-light border small py-2 mb-0" id="packMargin">Marge : —</div></div><?php endif; ?>
        <div class="col-12"><label class="form-label" for="tax_id">TVA</label><select class="form-select" id="tax_id" name="tax_id"><?php foreach ($taxes as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $v('tax_id', $defaultTax) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
      </div></div>
      <div class="card mb-4"><div class="card-header fw-semibold">Disponibilité</div><div class="card-body row g-3">
        <div class="col-6"><label class="form-label" for="available_from">À partir du</label><input class="form-control" type="date" id="available_from" name="available_from" value="<?= e($v('available_from')) ?>"></div>
        <div class="col-6"><label class="form-label" for="available_until">Jusqu'au</label><input class="form-control" type="date" id="available_until" name="available_until" value="<?= e($v('available_until')) ?>"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Pack actif</label></div>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="is_custom" name="is_custom" value="1" <?= (int) $v('is_custom', 0) ? 'checked' : '' ?>><label class="form-check-label" for="is_custom">Pack personnalisé (pour un client)</label></div></div>
      </div></div>
      <div class="card"><div class="card-header fw-semibold">Visuel</div><div class="card-body">
        <?php if (!empty($values['image'])): ?><img src="<?= e(asset($values['image'])) ?>" alt="" class="img-fluid rounded mb-2" style="max-height:120px"><?php endif; ?>
        <input class="form-control form-control-sm" type="file" name="image" accept="image/png,image/jpeg,image/webp" aria-label="Visuel">
        <?php if ($isEdit && $pack['image']): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rmimg"><label class="form-check-label small" for="rmimg">Supprimer le visuel</label></div><?php endif; ?>
      </div></div>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4"><a href="<?= e(url($isEdit ? 'packs/' . $pack['id'] : 'packs')) ?>" class="btn btn-outline-secondary">Annuler</a><button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button></div>
</form>
<script>
(function () {
  var rows = document.getElementById('itemRows'), tpl = rows.querySelector('[data-template]');
  var num = function (s) { return parseFloat(String(s || '0').replace(/\s/g, '').replace(',', '.')) || 0; };
  var fmt = function (n) { return n.toFixed(2).replace('.', ',') + ' <?= e(setting('currency_symbol', 'DH')) ?>'; };
  tpl.querySelectorAll('input').forEach(function (i) { i.disabled = true; });
  var totals = function () {
    var value = 0, cost = 0;
    rows.querySelectorAll('[data-item]:not([data-template])').forEach(function (r) {
      var q = num(r.querySelector('[data-qty]').value);
      value += q * num(r.dataset.price); cost += q * num(r.dataset.cost);
    });
    document.getElementById('sumValue').textContent = fmt(value);
    var sc = document.getElementById('sumCost'); if (sc) { sc.textContent = fmt(cost); }
    var price = num(document.getElementById('price').value), mb = document.getElementById('packMargin');
    if (mb) { mb.innerHTML = price > 0 ? 'Marge : <strong>' + fmt(price - cost) + '</strong> (' + ((price - cost) / price * 100).toFixed(1).replace('.', ',') + ' %)' + (value > price ? ' · économie client ' + fmt(value - price) : '') : 'Marge : —'; mb.className = 'alert border small py-2 mb-0 ' + (price - cost < 0 ? 'alert-danger' : 'alert-light'); }
    return value;
  };
  document.getElementById('itemAdd').addEventListener('click', function () {
    var r = tpl.cloneNode(true); r.hidden = false; r.removeAttribute('data-template');
    r.querySelectorAll('input').forEach(function (i) { i.disabled = false; });
    rows.appendChild(r); window.HOC.initLookups(r); r.querySelector('[data-lookup-input]').focus();
  });
  rows.addEventListener('click', function (e) { var b = e.target.closest('[data-remove]'); if (b) { b.closest('[data-item]').remove(); totals(); } });
  rows.addEventListener('hoc:lookup', function (e) { var r = e.target.closest('[data-item]'); r.dataset.price = e.detail.price || 0; r.dataset.cost = e.detail.cost || 0; totals(); });
  rows.addEventListener('input', totals);
  document.getElementById('price').addEventListener('input', totals);
  document.getElementById('applyDiscount').addEventListener('click', function () {
    var v = totals(), d = num(document.getElementById('discount_percent').value);
    document.getElementById('price').value = (Math.round(v * (1 - d / 100) * 100) / 100).toFixed(2).replace('.', ',');
    totals();
  });
  document.getElementById('packForm').addEventListener('submit', function () { tpl.remove(); });
  totals();
})();
</script>
