<?php
/** Formulaire produit / service. Variables : $product, $values, $prices, $bom, $categories, $units, $taxes, $machines, $priceLists, $supplier, $material */
$isEdit = $product !== null;
$v = static fn(string $k, $d = '') => old($k, $values[$k] ?? $d);
$types = \App\Controllers\ProductController::TYPES;
$showCost = can('products.view_cost');
$num = static fn($n, int $dec = 2) => $n === null || $n === '' ? '' : number_fr($n, $dec);
$defaultTax = (int) setting('default_tax_id', 1);
?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1><p class="text-body-secondary mb-0"><?= $isEdit ? 'Code ' . e($product['code']) : 'Code attribué automatiquement si laissé vide.' ?></p></div>
  <a href="<?= e(url($isEdit ? 'products/' . $product['id'] : 'products')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" action="<?= e(url($isEdit ? 'products/' . $product['id'] : 'products')) ?>" enctype="multipart/form-data" id="productForm">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card mb-4"><div class="card-header fw-semibold">Informations générales</div><div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="product_type">Type *</label>
          <select class="form-select" id="product_type" name="product_type"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $v('product_type') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label" for="category_id">Catégorie</label>
          <select class="form-select" id="category_id" name="category_id"><option value="0">— Aucune —</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] ? e($c['parent_name']) . ' › ' : '' ?><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-8"><label class="form-label" for="name">Désignation *</label><input class="form-control" id="name" name="name" value="<?= e($v('name')) ?>" required maxlength="190" autofocus></div>
        <div class="col-md-4"><label class="form-label" for="code">Code</label><input class="form-control text-uppercase" id="code" name="code" value="<?= e($v('code')) ?>" maxlength="40" placeholder="Automatique"></div>
        <div class="col-md-8"><label class="form-label" for="name_fr">Désignation sur les documents (si différente)</label><input class="form-control" id="name_fr" name="name_fr" value="<?= e($v('name_fr')) ?>" maxlength="190"></div>
        <div class="col-md-4"><label class="form-label" for="barcode">Code-barres</label><input class="form-control" id="barcode" name="barcode" value="<?= e($v('barcode')) ?>" maxlength="60"></div>
        <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"><?= e($v('description')) ?></textarea></div>
      </div></div>

      <div class="card mb-4"><div class="card-header fw-semibold">Prix et coûts (HT)</div><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label" for="unit_id">Unité de vente *</label>
          <select class="form-select" id="unit_id" name="unit_id"><?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $v('unit_id', 1) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?> (<?= e($u['symbol']) ?>)</option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="tax_id">TVA</label>
          <select class="form-select" id="tax_id" name="tax_id"><?php foreach ($taxes as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $v('tax_id', $defaultTax) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="selling_price">Prix de vente détail *</label>
          <div class="input-group"><input class="form-control" id="selling_price" name="selling_price" inputmode="decimal" value="<?= e($num($v('selling_price', 0))) ?>" data-calc><span class="input-group-text"><?= e(setting('currency_symbol', 'DH')) ?></span></div></div>
        <?php if ($showCost): ?>
          <div class="col-md-4"><label class="form-label" for="purchase_price">Prix d'achat</label><input class="form-control" id="purchase_price" name="purchase_price" inputmode="decimal" value="<?= e($num($v('purchase_price', 0), 4)) ?>" data-calc></div>
          <div class="col-md-4"><label class="form-label" for="estimated_cost">Coût de production estimé</label><input class="form-control" id="estimated_cost" name="estimated_cost" inputmode="decimal" value="<?= e($num($v('estimated_cost', 0), 4)) ?>" data-calc>
            <div class="form-text">Utilisé à la place du prix d'achat s'il est renseigné.</div></div>
        <?php else: ?>
          <input type="hidden" name="purchase_price" value="<?= e($num($v('purchase_price', 0), 4)) ?>"><input type="hidden" name="estimated_cost" value="<?= e($num($v('estimated_cost', 0), 4)) ?>">
        <?php endif; ?>
        <div class="col-md-4"><label class="form-label" for="min_selling_price">Prix minimum autorisé</label><input class="form-control" id="min_selling_price" name="min_selling_price" inputmode="decimal" value="<?= e($num($v('min_selling_price', 0))) ?>"></div>
        <?php if ($showCost): ?><div class="col-12"><div class="alert alert-light border mb-0 py-2 small" id="marginBox">Marge : —</div></div><?php endif; ?>
        <div class="col-12"><label class="form-label mb-1">Prix par liste de prix <span class="text-body-secondary small">(vide = prix détail)</span></label>
          <div class="row g-2"><?php foreach ($priceLists as $pl): ?>
            <div class="col-md-4"><div class="input-group input-group-sm"><span class="input-group-text"><?= e($pl['name']) ?></span>
              <input class="form-control" name="prices[<?= (int) $pl['id'] ?>]" inputmode="decimal" value="<?= e($num($_POST['prices'][$pl['id']] ?? ($prices[(int) $pl['id']] ?? null))) ?>"></div></div>
          <?php endforeach; ?></div></div>
      </div></div>

      <div class="card mb-4"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">Nomenclature : matières consommées par unité vendue
        <button type="button" class="btn btn-sm btn-outline-dark" id="bomAdd"><i class="bi bi-plus-lg"></i> Matière</button></div>
        <div class="card-body">
          <p class="small text-body-secondary">Ex. 1 m² de bâche consomme 1,05 m² de bâche 440 g. Ces quantités serviront à déduire les matières à la production et à calculer le coût de revient.</p>
          <div id="bomRows">
            <?php foreach (array_merge($bom, [null]) as $b): ?>
              <div class="row g-2 mb-2 align-items-end" data-bom-row<?= $b ? '' : ' data-template hidden' ?>>
                <div class="col-md-6"><div class="hoc-lookup" data-lookup="<?= e(url('materials/lookup')) ?>">
                  <input class="form-control form-control-sm" type="search" placeholder="Rechercher une matière…" autocomplete="off" data-lookup-input value="<?= $b ? e($b['code'] . ' — ' . $b['name']) : '' ?>">
                  <input type="hidden" name="bom[material_id][]" data-lookup-value value="<?= (int) ($b['material_id'] ?? 0) ?: '' ?>"></div></div>
                <div class="col-5 col-md-3"><div class="input-group input-group-sm"><input class="form-control" name="bom[quantity][]" inputmode="decimal" placeholder="Quantité" value="<?= $b ? e(number_fr($b['quantity'], 4)) : '' ?>"><span class="input-group-text" data-unit><?= e($b['symbol'] ?? 'u') ?></span></div></div>
                <div class="col-5 col-md-2"><div class="input-group input-group-sm"><input class="form-control" name="bom[waste][]" inputmode="decimal" placeholder="Gâche" value="<?= $b ? e(number_fr($b['waste_percent'], 1)) : '' ?>"><span class="input-group-text">%</span></div></div>
                <div class="col-2 col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-bom-remove title="Retirer"><i class="bi bi-x-lg"></i></button></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div></div>
    </div>

    <div class="col-xl-4">
      <div class="card mb-4"><div class="card-header fw-semibold">Image</div><div class="card-body">
        <div class="d-flex gap-3 align-items-center">
          <div class="hoc-image-preview"><?php if (!empty($values['image'])): ?><img src="<?= e(asset($values['image'])) ?>" alt=""><?php else: ?><i class="bi bi-image fs-3 text-body-secondary"></i><?php endif; ?></div>
          <div class="flex-grow-1"><input class="form-control form-control-sm" type="file" name="image" accept="image/png,image/jpeg,image/webp" aria-label="Image">
            <div class="form-text">PNG, JPG, WEBP — 3 Mo max.</div>
            <?php if ($isEdit && $product['image']): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rmimg"><label class="form-check-label small" for="rmimg">Supprimer l'image</label></div><?php endif; ?></div>
        </div></div></div>

      <div class="card mb-4" id="stockCard"><div class="card-header fw-semibold">Stock</div><div class="card-body row g-3">
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="track_stock" name="track_stock" value="1" <?= (int) $v('track_stock', 0) ? 'checked' : '' ?>>
          <label class="form-check-label" for="track_stock">Suivre le stock de cet article</label></div>
          <div class="form-text">Pour les articles revendus ou fabriqués à l'avance (t-shirts vierges, mugs, fournitures…). Les travaux sur mesure consomment des matières (nomenclature).</div></div>
        <div class="col-6"><label class="form-label" for="min_stock">Stock minimum</label><input class="form-control" id="min_stock" name="min_stock" inputmode="decimal" value="<?= e($num($v('min_stock', 0), 3)) ?>"></div>
        <?php if (!$isEdit && can('stock.movements')): ?><div class="col-6"><label class="form-label" for="initial_stock">Stock initial</label><input class="form-control" id="initial_stock" name="initial_stock" inputmode="decimal" placeholder="0"></div><?php endif; ?>
        <?php if ($isEdit): ?><div class="col-6"><label class="form-label">Stock actuel</label><div class="form-control-plaintext fw-semibold"><?= e(number_fr($product['stock_quantity'], 3)) ?></div></div>
          <div class="col-12 form-text mt-0">Le stock se modifie par des mouvements tracés (<a href="<?= e(url('stock/movements/create', ['product' => $product['id']])) ?>">entrée / sortie / ajustement</a>).</div><?php endif; ?>
      </div></div>

      <div class="card mb-4"><div class="card-header fw-semibold">Production et approvisionnement</div><div class="card-body row g-3">
        <div class="col-12"><label class="form-label" for="machine_id">Machine</label>
          <select class="form-select" id="machine_id" name="machine_id"><option value="0">— Aucune —</option>
            <?php foreach ($machines as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) $v('machine_id') === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Matière principale</label><div class="hoc-lookup" data-lookup="<?= e(url('materials/lookup')) ?>">
          <input class="form-control" type="search" placeholder="Rechercher…" autocomplete="off" data-lookup-input value="<?= $material ? e($material['code'] . ' — ' . $material['name']) : '' ?>">
          <input type="hidden" name="material_id" data-lookup-value value="<?= (int) ($material['id'] ?? 0) ?: '' ?>"></div></div>
        <div class="col-12"><label class="form-label">Fournisseur</label><div class="hoc-lookup" data-lookup="<?= e(url('suppliers/lookup')) ?>">
          <input class="form-control" type="search" placeholder="Rechercher…" autocomplete="off" data-lookup-input value="<?= $supplier ? e($supplier['code'] . ' — ' . $supplier['name']) : '' ?>">
          <input type="hidden" name="supplier_id" data-lookup-value value="<?= (int) ($supplier['id'] ?? 0) ?: '' ?>"></div></div>
      </div></div>

      <div class="card mb-4"><div class="card-header fw-semibold">Options</div><div class="card-body">
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Actif (proposé à la vente)</label></div>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="is_quick" name="is_quick" value="1" <?= (int) $v('is_quick', 0) ? 'checked' : '' ?>><label class="form-check-label" for="is_quick">Bouton rapide au point de vente</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="allow_price_override" name="allow_price_override" value="1" <?= (int) $v('allow_price_override', 1) ? 'checked' : '' ?>><label class="form-check-label" for="allow_price_override">Prix modifiable à la vente (prix variable)</label></div>
      </div></div>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2">
    <a href="<?= e(url($isEdit ? 'products/' . $product['id'] : 'products')) ?>" class="btn btn-outline-secondary">Annuler</a>
    <?php if (!$isEdit): ?><button class="btn btn-outline-dark" name="redirect" value="new">Enregistrer et nouveau</button><?php endif; ?>
    <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
  </div>
</form>
<script>
(function () {
  var services = <?= json_encode(\App\Controllers\ProductController::SERVICE_TYPES) ?>;
  var type = document.getElementById('product_type'), stock = document.getElementById('stockCard'), track = document.getElementById('track_stock');
  var sync = function () { var s = services.indexOf(type.value) >= 0; stock.style.opacity = s ? .5 : 1; track.disabled = s; if (s) { track.checked = false; } };
  type.addEventListener('change', sync); sync();
  var n = function (id) { var el = document.getElementById(id); return el ? parseFloat((el.value || '0').replace(/\s/g, '').replace(',', '.')) || 0 : 0; };
  var box = document.getElementById('marginBox');
  var calc = function () {
    if (!box) { return; }
    var sell = n('selling_price'), cost = n('estimated_cost') || n('purchase_price');
    if (sell <= 0) { box.textContent = 'Marge : —'; return; }
    var m = sell - cost, pct = m / sell * 100;
    box.className = 'alert border mb-0 py-2 small ' + (m < 0 ? 'alert-danger' : 'alert-light');
    box.innerHTML = 'Marge unitaire : <strong>' + m.toFixed(2).replace('.', ',') + '</strong> HT · taux de marge <strong>' + pct.toFixed(1).replace('.', ',') + ' %</strong>'
      + (cost > 0 ? ' · coefficient ' + (sell / cost).toFixed(2).replace('.', ',') : ' · renseignez un coût pour suivre la rentabilité');
  };
  document.querySelectorAll('[data-calc]').forEach(function (i) { i.addEventListener('input', calc); }); calc();
  // Lignes de nomenclature
  var rows = document.getElementById('bomRows'), tpl = rows.querySelector('[data-template]');
  document.getElementById('bomAdd').addEventListener('click', function () {
    var r = tpl.cloneNode(true); r.hidden = false; r.removeAttribute('data-template');
    r.querySelectorAll('input').forEach(function (i) { i.disabled = false; });
    rows.appendChild(r);
    window.HOC.initLookups && window.HOC.initLookups(r);
    r.querySelector('[data-lookup-input]').focus();
  });
  rows.addEventListener('click', function (e) { var b = e.target.closest('[data-bom-remove]'); if (b) { b.closest('[data-bom-row]').remove(); } });
  rows.addEventListener('hoc:lookup', function (e) { var u = e.target.closest('[data-bom-row]').querySelector('[data-unit]'); if (u && e.detail.unit) { u.textContent = e.detail.unit; } });
  tpl.querySelectorAll('input').forEach(function (i) { i.disabled = true; });
  document.getElementById('productForm').addEventListener('submit', function () { tpl.remove(); });
})();
</script>
