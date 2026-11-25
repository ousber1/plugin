<?php
/** Formulaire client. Variables : $customer, $groups, $priceLists */
$isEdit = $customer !== null;
$v = static fn(string $k, $d = '') => old($k, $customer[$k] ?? $d);
$types = \App\Controllers\CustomerController::TYPES;
$canLimit = can('customers.credit_limit');
?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1>
    <p class="text-body-secondary mb-0"><?= $isEdit ? 'Code ' . e($customer['code']) : 'Le code est attribué automatiquement si vous le laissez vide.' ?></p>
  </div>
  <a href="<?= e(url($isEdit ? 'customers/' . $customer['id'] : 'customers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" action="<?= e(url($isEdit ? 'customers/' . $customer['id'] : 'customers')) ?>">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card mb-4">
        <div class="card-header fw-semibold">Identité</div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label" for="type">Type de client *</label>
            <select class="form-select" id="type" name="type">
              <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $v('type', 'particulier') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-4"><label class="form-label" for="code">Code client</label>
            <input class="form-control text-uppercase" id="code" name="code" value="<?= e($v('code')) ?>" maxlength="30" placeholder="Automatique"></div>
          <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Client actif</label></div></div>
          <div class="col-md-6"><label class="form-label" for="full_name">Nom complet / contact *</label>
            <input class="form-control" id="full_name" name="full_name" value="<?= e($v('full_name')) ?>" required maxlength="150" autofocus></div>
          <div class="col-md-6"><label class="form-label" for="company_name">Raison sociale</label>
            <input class="form-control" id="company_name" name="company_name" value="<?= e($v('company_name')) ?>" maxlength="190"></div>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header fw-semibold">Coordonnées</div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label" for="phone">Téléphone</label><input class="form-control" type="tel" id="phone" name="phone" value="<?= e($v('phone')) ?>"></div>
          <div class="col-md-4"><label class="form-label" for="whatsapp">WhatsApp</label><input class="form-control" type="tel" id="whatsapp" name="whatsapp" value="<?= e($v('whatsapp')) ?>"></div>
          <div class="col-md-4"><label class="form-label" for="email">E-mail</label><input class="form-control" type="email" id="email" name="email" value="<?= e($v('email')) ?>" maxlength="190"></div>
          <div class="col-md-8"><label class="form-label" for="address">Adresse</label><input class="form-control" id="address" name="address" value="<?= e($v('address')) ?>" maxlength="255"></div>
          <div class="col-md-4"><label class="form-label" for="city">Ville</label><input class="form-control" id="city" name="city" value="<?= e($v('city')) ?>" maxlength="100" list="citiesList">
            <datalist id="citiesList"><?php foreach (['Casablanca','Rabat','Marrakech','Fès','Tanger','Agadir','Meknès','Oujda','Kénitra','Tétouan','Salé','Mohammedia','El Jadida','Béni Mellal','Nador','Safi','Laâyoune','Khouribga','Settat','Berrechid'] as $city): ?><option value="<?= e($city) ?>"><?php endforeach; ?></datalist></div>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header fw-semibold">Identifiants légaux</div>
        <div class="card-body row g-3">
          <div class="col-md-3"><label class="form-label" for="ice">ICE</label><input class="form-control" id="ice" name="ice" value="<?= e($v('ice')) ?>" inputmode="numeric" maxlength="15" pattern="\d{15}" placeholder="15 chiffres"></div>
          <div class="col-md-3"><label class="form-label" for="if_number">IF</label><input class="form-control" id="if_number" name="if_number" value="<?= e($v('if_number')) ?>" maxlength="20"></div>
          <div class="col-md-3"><label class="form-label" for="rc">RC</label><input class="form-control" id="rc" name="rc" value="<?= e($v('rc')) ?>" maxlength="30"></div>
          <div class="col-md-3"><label class="form-label" for="cin">CIN</label><input class="form-control text-uppercase" id="cin" name="cin" value="<?= e($v('cin')) ?>" maxlength="20"></div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card mb-4">
        <div class="card-header fw-semibold">Conditions commerciales</div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label" for="group_id">Groupe</label>
            <select class="form-select" id="group_id" name="group_id">
              <option value="0">— Aucun —</option>
              <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $v('group_id') === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?><?= (float) $g['discount_percent'] > 0 ? ' (−' . e(number_fr($g['discount_percent'], 1)) . ' %)' : '' ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-12"><label class="form-label" for="price_list_id">Liste de prix</label>
            <select class="form-select" id="price_list_id" name="price_list_id">
              <option value="0">Selon le groupe</option>
              <?php foreach ($priceLists as $pl): ?><option value="<?= (int) $pl['id'] ?>" <?= (int) $v('price_list_id') === (int) $pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-12"><label class="form-label" for="credit_limit">Limite de crédit (<?= e(setting('currency_symbol', 'DH')) ?>)</label>
            <input class="form-control" id="credit_limit" name="credit_limit" inputmode="decimal" value="<?= e(number_fr($v('credit_limit', 0))) ?>" <?= $canLimit ? '' : 'disabled' ?>>
            <div class="form-text"><?= $canLimit ? '0 = aucune vente à crédit autorisée.' : 'Modification réservée aux utilisateurs autorisés.' ?></div></div>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header fw-semibold">Notes internes</div>
        <div class="card-body"><textarea class="form-control" name="notes" rows="6" maxlength="5000" aria-label="Notes"><?= e($v('notes')) ?></textarea></div>
      </div>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2">
    <a href="<?= e(url($isEdit ? 'customers/' . $customer['id'] : 'customers')) ?>" class="btn btn-outline-secondary">Annuler</a>
    <?php if (!$isEdit): ?><button class="btn btn-outline-dark" name="redirect" value="new">Enregistrer et nouveau</button><?php endif; ?>
    <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
  </div>
</form>
