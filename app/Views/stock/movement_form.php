<?php /** Saisie d'un mouvement. Variables : $item, $operations */
$showCost = can('products.view_cost');
$kind = $item['kind'] ?? 'material'; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Entrée / sortie de stock</h1><p class="text-body-secondary mb-0">Chaque mouvement est tracé (date, utilisateur, motif) et ne peut pas être supprimé.</p></div>
  <a href="<?= e(url('stock')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Stock</a>
</div>
<form method="post" action="<?= e(url('stock/movements')) ?>" class="card" style="max-width:820px" id="mvForm"><?= csrf_field() ?>
  <div class="card-body row g-3">
    <div class="col-md-4"><label class="form-label" for="kind">Article</label>
      <select class="form-select" id="kind" name="kind"><option value="material" <?= $kind === 'material' ? 'selected' : '' ?>>Matière première</option><option value="product" <?= $kind === 'product' ? 'selected' : '' ?>>Produit stocké</option></select></div>
    <div class="col-md-8"><label class="form-label">Rechercher</label>
      <div class="hoc-lookup" id="itemLookup" data-lookup="<?= e($kind === 'product' ? url('products/lookup', ['stock' => 1]) : url('materials/lookup')) ?>" data-url-product="<?= e(url('products/lookup', ['stock' => 1])) ?>" data-url-material="<?= e(url('materials/lookup')) ?>">
        <input class="form-control" type="search" placeholder="Nom, code ou code-barres…" autocomplete="off" data-lookup-input value="<?= $item ? e($item['code'] . ' — ' . $item['name']) : '' ?>" <?= $item ? '' : 'autofocus' ?>>
        <input type="hidden" name="item_id" data-lookup-value required value="<?= (int) ($item['id'] ?? 0) ?: '' ?>"></div>
      <div class="form-text" id="itemInfo"><?= $item ? 'Stock actuel : <strong>' . e(number_fr($item['stock_quantity'], 3)) . ' ' . e($item['symbol']) . '</strong>' : '' ?></div></div>
    <div class="col-md-6"><label class="form-label" for="operation">Opération *</label>
      <select class="form-select" id="operation" name="operation">
        <?php foreach ($operations as $k => [$l, $dir]): if ($k === 'adjustment' && !can('stock.adjust')) { continue; } ?><option value="<?= $k ?>" data-dir="<?= $dir ?>"><?= e($l) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3" data-show="qty"><label class="form-label" for="quantity">Quantité *</label><input class="form-control" id="quantity" name="quantity" inputmode="decimal"></div>
    <div class="col-md-3 d-none" data-show="adjust"><label class="form-label" for="new_quantity">Quantité réelle *</label><input class="form-control" id="new_quantity" name="new_quantity" inputmode="decimal"></div>
    <?php if ($showCost): ?><div class="col-md-3" data-show="cost"><label class="form-label" for="unit_cost">Coût unitaire HT</label><input class="form-control" id="unit_cost" name="unit_cost" inputmode="decimal" placeholder="<?= $item ? e(number_fr($item['unit_cost'], 4)) : '' ?>"><div class="form-text">Met à jour le coût moyen.</div></div><?php endif; ?>
    <div class="col-md-6" data-show="supplier"><label class="form-label">Fournisseur</label>
      <div class="hoc-lookup" data-lookup="<?= e(url('suppliers/lookup')) ?>"><input class="form-control" type="search" placeholder="Optionnel" autocomplete="off" data-lookup-input><input type="hidden" name="supplier_id" data-lookup-value></div></div>
    <div class="col-12"><label class="form-label" for="reason">Motif / référence</label><input class="form-control" id="reason" name="reason" maxlength="255" placeholder="Ex. BL fournisseur n° 1234, chute de découpe, rouleau abîmé…"></div>
    <div class="col-12"><div class="alert alert-light border small mb-0" id="preview">Choisissez un article et une quantité.</div></div>
  </div>
  <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
    <button class="btn btn-outline-dark" name="redirect" value="new">Enregistrer et continuer</button>
    <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
  </div>
</form>
<script>
(function () {
  var kind = document.getElementById('kind'), lookup = document.getElementById('itemLookup'), op = document.getElementById('operation');
  var stock = <?= $item ? json_encode((float) $item['stock_quantity']) : 'null' ?>, unit = <?= json_encode($item['symbol'] ?? '') ?>;
  var num = function (id) { var el = document.getElementById(id); return el ? parseFloat((el.value || '').replace(/\s/g, '').replace(',', '.')) : NaN; };
  var fmt = function (n) { return (Math.round(n * 1000) / 1000).toString().replace('.', ','); };
  kind.addEventListener('change', function () {
    lookup.dataset.lookup = kind.value === 'product' ? lookup.dataset.urlProduct : lookup.dataset.urlMaterial;
    lookup.querySelector('[data-lookup-input]').value = ''; lookup.querySelector('[data-lookup-value]').value = '';
    stock = null; document.getElementById('itemInfo').textContent = ''; update();
  });
  lookup.addEventListener('hoc:lookup', function (e) {
    var m = /stock ([\d\s.,-]+)/i.exec(e.detail.detail || '');
    stock = m ? parseFloat(m[1].replace(/\s/g, '').replace(',', '.')) : null; unit = e.detail.unit || '';
    document.getElementById('itemInfo').innerHTML = stock !== null ? 'Stock actuel : <strong>' + fmt(stock) + ' ' + unit + '</strong>' : '';
    update();
  });
  var show = function (key, on) { document.querySelectorAll('[data-show="' + key + '"]').forEach(function (el) { el.classList.toggle('d-none', !on); }); };
  var update = function () {
    var o = op.value, dir = +op.selectedOptions[0].dataset.dir;
    show('qty', o !== 'adjustment'); show('adjust', o === 'adjustment');
    show('cost', o === 'purchase'); show('supplier', o === 'purchase' || o === 'supplier_return');
    var p = document.getElementById('preview');
    if (stock === null) { p.textContent = 'Choisissez un article et une quantité.'; p.className = 'alert alert-light border small mb-0'; return; }
    var after = o === 'adjustment' ? num('new_quantity') : stock + dir * num('quantity');
    if (isNaN(after)) { p.textContent = 'Stock actuel : ' + fmt(stock) + ' ' + unit; return; }
    p.className = 'alert border small mb-0 ' + (after < 0 ? 'alert-danger' : 'alert-light');
    p.innerHTML = 'Stock après mouvement : <strong>' + fmt(after) + ' ' + unit + '</strong>' + (after < 0 ? ' — impossible : stock insuffisant.' : '') + (o === 'adjustment' && !isNaN(after) ? ' (écart ' + (after - stock >= 0 ? '+' : '') + fmt(after - stock) + ')' : '');
  };
  op.addEventListener('change', update);
  document.getElementById('mvForm').addEventListener('input', update);
  update();
})();
</script>
