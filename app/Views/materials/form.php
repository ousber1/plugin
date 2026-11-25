<?php /** Formulaire matière. Variables : $m, $units, $supplier, $machines, $linkedMachines */
$isEdit = $m !== null;
$v = static fn(string $k, $d = '') => old($k, $m[$k] ?? $d);
$types = \App\Controllers\MaterialController::TYPES;
$num = static fn($n, int $dec = 2) => $n === null || $n === '' ? '' : number_fr($n, $dec); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1><p class="text-body-secondary mb-0"><?= $isEdit ? 'Code ' . e($m['code']) : 'Code attribué automatiquement si laissé vide.' ?></p></div>
  <a href="<?= e(url($isEdit ? 'materials/' . $m['id'] : 'materials')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" action="<?= e(url($isEdit ? 'materials/' . $m['id'] : 'materials')) ?>"><?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-8"><div class="card"><div class="card-body row g-3">
      <div class="col-md-8"><label class="form-label" for="name">Nom *</label><input class="form-control" id="name" name="name" value="<?= e($v('name')) ?>" required maxlength="190" autofocus placeholder="Ex. Vinyle blanc brillant 1,37 m"></div>
      <div class="col-md-4"><label class="form-label" for="code">Code</label><input class="form-control text-uppercase" id="code" name="code" value="<?= e($v('code')) ?>" maxlength="40" placeholder="Automatique"></div>
      <div class="col-md-4"><label class="form-label" for="material_type">Type *</label><select class="form-select" id="material_type" name="material_type"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $v('material_type', 'other') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label" for="unit_id">Unité de stock *</label><select class="form-select" id="unit_id" name="unit_id"><?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $v('unit_id', 7) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?> (<?= e($u['symbol']) ?>)</option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label" for="barcode">Code-barres</label><input class="form-control" id="barcode" name="barcode" value="<?= e($v('barcode')) ?>" maxlength="60"></div>
      <div class="col-md-4"><label class="form-label" for="unit_cost">Coût unitaire HT</label><input class="form-control" id="unit_cost" name="unit_cost" inputmode="decimal" value="<?= e($num($v('unit_cost', 0), 4)) ?>" <?= can('products.view_cost') ? '' : 'readonly' ?>>
        <div class="form-text"><?= $isEdit ? 'Recalculé automatiquement (CMUP) à chaque entrée valorisée.' : 'Coût de départ ; ensuite calculé par moyenne pondérée.' ?></div></div>
      <div class="col-md-4"><label class="form-label" for="width_cm">Laize (cm)</label><input class="form-control" id="width_cm" name="width_cm" inputmode="decimal" value="<?= e($num($v('width_cm'))) ?>" placeholder="Rouleaux"></div>
      <div class="col-md-4"><label class="form-label" for="length_m">Longueur du rouleau (m)</label><input class="form-control" id="length_m" name="length_m" inputmode="decimal" value="<?= e($num($v('length_m'))) ?>"></div>
      <div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="5000"><?= e($v('notes')) ?></textarea></div>
    </div></div></div>
    <div class="col-xl-4">
      <div class="card mb-4"><div class="card-header fw-semibold">Stock</div><div class="card-body row g-3">
        <div class="col-6"><label class="form-label" for="min_stock">Stock minimum</label><input class="form-control" id="min_stock" name="min_stock" inputmode="decimal" value="<?= e($num($v('min_stock', 0), 3)) ?>"></div>
        <?php if (!$isEdit && can('stock.movements')): ?><div class="col-6"><label class="form-label" for="initial_stock">Stock initial</label><input class="form-control" id="initial_stock" name="initial_stock" inputmode="decimal" placeholder="0"></div><?php endif; ?>
        <?php if ($isEdit): ?><div class="col-6"><label class="form-label">Stock actuel</label><div class="form-control-plaintext fw-semibold"><?= e(number_fr($m['stock_quantity'], 3)) ?></div></div><?php endif; ?>
        <div class="col-12"><label class="form-label">Fournisseur principal</label><div class="hoc-lookup" data-lookup="<?= e(url('suppliers/lookup')) ?>">
          <input class="form-control" type="search" placeholder="Rechercher…" autocomplete="off" data-lookup-input value="<?= $supplier ? e($supplier['code'] . ' — ' . $supplier['name']) : '' ?>">
          <input type="hidden" name="supplier_id" data-lookup-value value="<?= (int) ($supplier['id'] ?? 0) ?: '' ?>"></div></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Matière active</label></div></div>
      </div></div>
      <div class="card"><div class="card-header fw-semibold">Machines qui consomment cette matière</div><div class="card-body">
        <?php foreach ($machines as $ma): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="machines[]" value="<?= (int) $ma['id'] ?>" id="ma<?= (int) $ma['id'] ?>" <?= in_array((int) $ma['id'], $linkedMachines, true) ? 'checked' : '' ?>><label class="form-check-label" for="ma<?= (int) $ma['id'] ?>"><?= e($ma['name']) ?></label></div><?php endforeach; ?>
      </div></div>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4"><a href="<?= e(url($isEdit ? 'materials/' . $m['id'] : 'materials')) ?>" class="btn btn-outline-secondary">Annuler</a><button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button></div>
</form>
