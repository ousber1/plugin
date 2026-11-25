<?php
/** Étape 6 : récapitulatif et lancement de l'installation. */
$d = $s['database'] ?? [];
$b = $s['business'] ?? [];
$st = $s['settings'] ?? [];
$a = $s['admin'] ?? [];
$tables = count(Installer::schemaTables());
?>
<h2 class="h5 fw-bold mb-1">Récapitulatif</h2>
<p class="text-body-secondary">Vérifiez les informations puis lancez l'installation. <?= (int) $tables ?> tables seront créées.</p>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="border rounded-3 p-3 h-100">
      <div class="fw-semibold mb-2"><i class="bi bi-database me-1"></i> Base de données</div>
      <div class="small"><?= $e($d['user'] ?? '') ?>@<?= $e($d['host'] ?? '') ?>:<?= $e($d['port'] ?? '') ?> / <strong><?= $e($d['name'] ?? '') ?></strong></div>
      <div class="small text-body-secondary text-break"><?= $e($d['app_url'] ?? '') ?></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="border rounded-3 p-3 h-100">
      <div class="fw-semibold mb-2"><i class="bi bi-building me-1"></i> Entreprise</div>
      <div class="small"><strong><?= $e($b['business_name'] ?? '') ?></strong> — <?= $e($b['business_city'] ?? '') ?></div>
      <div class="small text-body-secondary">ICE : <?= $e(($b['business_ice'] ?? '') ?: '—') ?> · IF : <?= $e(($b['business_if'] ?? '') ?: '—') ?> · RC : <?= $e(($b['business_rc'] ?? '') ?: '—') ?></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="border rounded-3 p-3 h-100">
      <div class="fw-semibold mb-2"><i class="bi bi-cash-coin me-1"></i> Devise et TVA</div>
      <div class="small"><?= $e($st['currency_code'] ?? '') ?> (<?= $e($st['currency_symbol'] ?? '') ?>) · TVA par défaut n° <?= (int) ($st['default_tax_id'] ?? 1) ?></div>
      <div class="small text-body-secondary">Prix <?= !empty($st['prices_include_tax']) ? 'TTC' : 'HT' ?> · Devis valables <?= (int) ($st['quotation_validity_days'] ?? 30) ?> j · Échéance <?= (int) ($st['invoice_due_days'] ?? 30) ?> j</div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="border rounded-3 p-3 h-100">
      <div class="fw-semibold mb-2"><i class="bi bi-person-badge me-1"></i> Administrateur</div>
      <div class="small"><strong><?= $e($a['full_name'] ?? '') ?></strong> (<?= $e($a['username'] ?? '') ?>)</div>
      <div class="small text-body-secondary"><?= $e($a['email'] ?? '') ?></div>
    </div>
  </div>
  <div class="col-12">
    <div class="border rounded-3 p-3">
      <div class="fw-semibold mb-2"><i class="bi bi-123 me-1"></i> Premiers numéros</div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach (Installer::NUMBERED_DOCS as $type => [$label]): ?>
          <span class="badge text-bg-light border fw-normal p-2"><?= $e($label) ?> :
            <code><?= $e(Installer::previewNumber($st['number_format'] ?? '{PREFIX}-{YYYY}-{SEQ}', $st['prefix_' . $type] ?? '', (int) ($st['start_' . $type] ?? 1), (int) ($st['number_padding'] ?? 4))) ?></code></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between">
  <a href="index.php?step=5" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
  <button type="submit" class="btn btn-accent btn-lg" onclick="this.disabled=true;this.innerHTML='Installation en cours…';this.form.submit();">
    <i class="bi bi-rocket-takeoff me-1"></i> Installer HO CREATIVE
  </button>
</div>
