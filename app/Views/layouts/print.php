<?php
/** Gabarit d'impression (relevés, fiches). Variables : $content, $title */
$logo = (string) setting('business_logo', '');
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title ?? 'Document') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: #e4e4e7; font-family: Inter, system-ui, sans-serif; font-size: 13px; color: #18181b; }
    .sheet { background: #fff; max-width: 210mm; min-height: 297mm; margin: 1.5rem auto; padding: 16mm 14mm; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
    .doc-head { display: flex; justify-content: space-between; gap: 2rem; border-bottom: 3px solid #0b0b0c; padding-bottom: 1rem; margin-bottom: 1.25rem; }
    .doc-head img { max-height: 64px; max-width: 200px; }
    .doc-title { font-size: 1.5rem; font-weight: 800; letter-spacing: .02em; }
    .doc-accent { color: #ff3d00; }
    .table { font-size: 12px; }
    .table thead th { background: #0b0b0c; color: #fff; font-weight: 600; }
    .toolbar { max-width: 210mm; margin: 1rem auto 0; display: flex; gap: .5rem; justify-content: flex-end; }
    @media print {
      body { background: #fff; }
      .sheet { box-shadow: none; margin: 0; max-width: none; min-height: auto; padding: 0; }
      .toolbar, .no-print { display: none !important; }
      @page { size: A4; margin: 12mm; }
      .table thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <button class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer / Enregistrer en PDF</button>
    <button class="btn btn-outline-secondary btn-sm" onclick="history.back()">Retour</button>
  </div>
  <div class="sheet">
    <div class="doc-head">
      <div>
        <?php if ($logo !== ''): ?><img src="<?= e(asset($logo)) ?>" alt="" class="mb-2 d-block"><?php endif; ?>
        <div class="fw-bold fs-5"><?= e(setting('business_name')) ?></div>
        <div class="text-secondary"><?= e(setting('business_activity')) ?></div>
        <div><?= e(trim(setting('business_address') . ' ' . setting('business_city'))) ?></div>
        <div><?= e(implode(' · ', array_filter([setting('business_phone') ? 'Tél. ' . setting('business_phone') : '', setting('business_email')]))) ?></div>
        <div class="small text-secondary"><?= e(implode(' · ', array_filter([
            setting('business_ice') ? 'ICE ' . setting('business_ice') : '', setting('business_if') ? 'IF ' . setting('business_if') : '',
            setting('business_rc') ? 'RC ' . setting('business_rc') : '']))) ?></div>
      </div>
      <?= $docHeadRight ?? '' ?>
    </div>
    <?= $content ?>
  </div>
</body>
</html>
