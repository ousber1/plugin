<?php
/** Étape 3 : informations de l'entreprise (modifiables ensuite dans Paramètres). */
$f = static function (string $name, string $label, string $type = 'text', string $help = '', string $col = 'col-md-6', string $default = '') use ($val, $e): void { ?>
  <div class="<?= $col ?>">
    <label class="form-label" for="<?= $name ?>"><?= $e($label) ?></label>
    <input class="form-control" id="<?= $name ?>" name="<?= $name ?>" type="<?= $type ?>" value="<?= $e($val('business', $name, $default)) ?>">
    <?php if ($help !== ''): ?><div class="form-text"><?= $e($help) ?></div><?php endif; ?>
  </div>
<?php };
?>
<h2 class="h5 fw-bold mb-1">Votre entreprise</h2>
<p class="text-body-secondary">Ces informations apparaissent sur vos devis, factures et reçus. Elles restent modifiables à tout moment.</p>

<div class="row g-3">
  <?php $f('business_name', 'Raison sociale *', 'text', '', 'col-md-6', 'HO CREATIVE'); ?>
  <?php $f('business_activity', 'Activité', 'text', '', 'col-md-6', 'Impression • Publicité • Design • Communication'); ?>
  <?php $f('business_address', 'Adresse', 'text', '', 'col-md-8'); ?>
  <?php $f('business_city', 'Ville', 'text', '', 'col-md-4'); ?>
  <?php $f('business_phone', 'Téléphone', 'tel', '', 'col-md-4'); ?>
  <?php $f('business_whatsapp', 'WhatsApp', 'tel', '', 'col-md-4'); ?>
  <?php $f('business_email', 'E-mail', 'email', '', 'col-md-4'); ?>
  <?php $f('business_website', 'Site web', 'url', 'Ex. https://www.hocreative.ma', 'col-md-12'); ?>
  <div class="col-12"><h3 class="h6 fw-bold mt-2 mb-0">Identifiants légaux</h3></div>
  <?php $f('business_ice', 'ICE', 'text', '15 chiffres', 'col-md-4'); ?>
  <?php $f('business_if', 'Identifiant fiscal (IF)', 'text', '', 'col-md-4'); ?>
  <?php $f('business_rc', 'Registre de commerce (RC)', 'text', '', 'col-md-4'); ?>
  <?php $f('business_patente', 'Patente / Taxe professionnelle', 'text', '', 'col-md-4'); ?>
  <?php $f('business_cnss', 'CNSS', 'text', '', 'col-md-4'); ?>
  <?php $f('business_capital', 'Capital social', 'text', 'Ex. 100 000 DH', 'col-md-4'); ?>
  <div class="col-12"><h3 class="h6 fw-bold mt-2 mb-0">Coordonnées bancaires</h3></div>
  <?php $f('bank_name', 'Banque', 'text', '', 'col-md-4'); ?>
  <?php $f('bank_rib', 'RIB', 'text', '24 chiffres', 'col-md-5'); ?>
  <?php $f('bank_swift', 'Code SWIFT', 'text', '', 'col-md-3'); ?>
</div>

<div class="d-flex justify-content-between mt-4">
  <a href="index.php?step=2" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
  <button type="submit" class="btn btn-dark btn-lg">Continuer <i class="bi bi-arrow-right"></i></button>
</div>
