<?php
/** Étape 4 : devise, TVA, échéances et numérotation des documents. */
$sv = static fn(string $k, $d = '') => $val('settings', $k, $d);
$taxes = [1 => 'TVA 20 % (taux normal)', 2 => 'TVA 14 %', 3 => 'TVA 10 %', 4 => 'TVA 7 %', 5 => 'Exonéré (0 %)'];
?>
<h2 class="h5 fw-bold mb-1">Paramètres de gestion</h2>
<p class="text-body-secondary">Devise, fiscalité et numérotation de vos documents commerciaux.</p>

<h3 class="h6 fw-bold">Devise</h3>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <label class="form-label" for="currency_code">Code devise</label>
    <input class="form-control" id="currency_code" name="currency_code" maxlength="3" value="<?= $e($sv('currency_code', 'MAD')) ?>">
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label" for="currency_symbol">Symbole</label>
    <input class="form-control" id="currency_symbol" name="currency_symbol" maxlength="5" value="<?= $e($sv('currency_symbol', 'DH')) ?>">
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label" for="currency_position">Position</label>
    <select class="form-select" id="currency_position" name="currency_position">
      <option value="after" <?= $sv('currency_position', 'after') === 'after' ? 'selected' : '' ?>>Après (1 250,00 DH)</option>
      <option value="before" <?= $sv('currency_position') === 'before' ? 'selected' : '' ?>>Avant (DH 1 250,00)</option>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label" for="currency_decimals">Décimales</label>
    <select class="form-select" id="currency_decimals" name="currency_decimals">
      <?php foreach ([0, 2, 3] as $d): ?><option value="<?= $d ?>" <?= (int) $sv('currency_decimals', 2) === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?>
    </select>
  </div>
</div>

<h3 class="h6 fw-bold">TVA et échéances</h3>
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <label class="form-label" for="default_tax_id">Taux de TVA par défaut</label>
    <select class="form-select" id="default_tax_id" name="default_tax_id">
      <?php foreach ($taxes as $id => $label): ?><option value="<?= $id ?>" <?= (int) $sv('default_tax_id', 1) === $id ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6 d-flex flex-column justify-content-end">
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" id="tax_enabled" name="tax_enabled" value="1" <?= $sv('tax_enabled', true) ? 'checked' : '' ?>>
      <label class="form-check-label" for="tax_enabled">Assujetti à la TVA</label>
    </div>
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" id="prices_include_tax" name="prices_include_tax" value="1" <?= $sv('prices_include_tax', false) ? 'checked' : '' ?>>
      <label class="form-check-label" for="prices_include_tax">Les prix du catalogue sont saisis TTC</label>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label" for="quotation_validity_days">Validité des devis (jours)</label>
    <input class="form-control" type="number" min="0" max="365" id="quotation_validity_days" name="quotation_validity_days" value="<?= $e($sv('quotation_validity_days', 30)) ?>">
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label" for="invoice_due_days">Échéance factures (jours)</label>
    <input class="form-control" type="number" min="0" max="365" id="invoice_due_days" name="invoice_due_days" value="<?= $e($sv('invoice_due_days', 30)) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="default_payment_terms">Conditions de paiement par défaut</label>
    <input class="form-control" id="default_payment_terms" name="default_payment_terms" value="<?= $e($sv('default_payment_terms', 'Paiement à 30 jours à compter de la date de facture.')) ?>">
  </div>
</div>

<h3 class="h6 fw-bold">Numérotation des documents</h3>
<div class="row g-3 mb-3">
  <div class="col-md-5">
    <label class="form-label" for="number_format">Format</label>
    <select class="form-select" id="number_format" name="number_format">
      <?php foreach (Installer::NUMBER_FORMATS as $fmt => $example): ?>
        <option value="<?= $e($fmt) ?>" <?= $sv('number_format', '{PREFIX}-{YYYY}-{SEQ}') === $fmt ? 'selected' : '' ?>><?= $e($example) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label" for="number_padding">Nombre de chiffres</label>
    <input class="form-control" type="number" min="3" max="8" id="number_padding" name="number_padding" value="<?= $e($sv('number_padding', 4)) ?>">
  </div>
  <div class="col-6 col-md-4">
    <label class="form-label" for="reset_policy">Remise à zéro</label>
    <select class="form-select" id="reset_policy" name="reset_policy">
      <option value="yearly" <?= $sv('reset_policy', 'yearly') === 'yearly' ? 'selected' : '' ?>>Chaque année</option>
      <option value="monthly" <?= $sv('reset_policy') === 'monthly' ? 'selected' : '' ?>>Chaque mois</option>
      <option value="never" <?= $sv('reset_policy') === 'never' ? 'selected' : '' ?>>Jamais</option>
    </select>
  </div>
</div>
<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr><th>Document</th><th style="width:140px">Préfixe</th><th style="width:160px">N° de départ</th></tr></thead>
    <tbody>
    <?php foreach (Installer::NUMBERED_DOCS as $type => [$label, $defPrefix]): ?>
      <tr>
        <td><?= $e($label) ?></td>
        <td><input class="form-control form-control-sm text-uppercase" name="prefix_<?= $type ?>" maxlength="10" value="<?= $e($sv('prefix_' . $type, $defPrefix)) ?>"></td>
        <td><input class="form-control form-control-sm" type="number" min="1" name="start_<?= $type ?>" value="<?= $e($sv('start_' . $type, 1)) ?>"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="form-text mb-2">Si vous reprenez une numérotation existante, indiquez le prochain numéro à utiliser. Les autres séquences (achats, dépenses, inventaires…) sont configurables dans les paramètres.</div>

<div class="d-flex justify-content-between mt-4">
  <a href="index.php?step=3" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
  <button type="submit" class="btn btn-dark btn-lg">Continuer <i class="bi bi-arrow-right"></i></button>
</div>
