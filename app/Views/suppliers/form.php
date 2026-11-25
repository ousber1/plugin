<?php /** Formulaire fournisseur. Variables : $supplier */
$isEdit = $supplier !== null;
$v = static fn(string $k, $d = '') => old($k, $supplier[$k] ?? $d);
$field = static function (string $name, string $label, string $col, string $type = 'text', string $extra = '') use ($v): void { ?>
  <div class="<?= $col ?>"><label class="form-label" for="<?= $name ?>"><?= e($label) ?></label>
    <input class="form-control" type="<?= $type ?>" id="<?= $name ?>" name="<?= $name ?>" value="<?= e($v($name)) ?>" <?= $extra ?>></div>
<?php }; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1><p class="text-body-secondary mb-0"><?= $isEdit ? 'Code ' . e($supplier['code']) : 'Code attribué automatiquement si laissé vide.' ?></p></div>
  <a href="<?= e(url($isEdit ? 'suppliers/' . $supplier['id'] : 'suppliers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" action="<?= e(url($isEdit ? 'suppliers/' . $supplier['id'] : 'suppliers')) ?>" class="card">
  <div class="card-body row g-3"><?= csrf_field() ?>
    <?php $field('name', 'Nom du contact / fournisseur *', 'col-md-5', 'text', 'required maxlength="150" autofocus'); ?>
    <?php $field('company', 'Société', 'col-md-4', 'text', 'maxlength="190"'); ?>
    <?php $field('code', 'Code', 'col-md-3', 'text', 'maxlength="30" placeholder="Automatique"'); ?>
    <?php $field('phone', 'Téléphone', 'col-md-4', 'tel'); ?>
    <?php $field('whatsapp', 'WhatsApp', 'col-md-4', 'tel'); ?>
    <?php $field('email', 'E-mail', 'col-md-4', 'email', 'maxlength="190"'); ?>
    <?php $field('address', 'Adresse', 'col-md-8', 'text', 'maxlength="255"'); ?>
    <?php $field('city', 'Ville', 'col-md-4', 'text', 'maxlength="100"'); ?>
    <?php $field('ice', 'ICE', 'col-md-3', 'text', 'maxlength="15" inputmode="numeric"'); ?>
    <?php $field('if_number', 'IF', 'col-md-3', 'text', 'maxlength="20"'); ?>
    <?php $field('rc', 'RC', 'col-md-3', 'text', 'maxlength="30"'); ?>
    <?php $field('payment_terms_days', 'Délai de paiement (jours)', 'col-md-3', 'number', 'min="0" max="365"'); ?>
    <div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="5000"><?= e($v('notes')) ?></textarea></div>
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Fournisseur actif</label></div></div>
  </div>
  <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
    <a href="<?= e(url($isEdit ? 'suppliers/' . $supplier['id'] : 'suppliers')) ?>" class="btn btn-outline-secondary">Annuler</a>
    <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
  </div>
</form>
