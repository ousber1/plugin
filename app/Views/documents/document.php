<?php
/**
 * Gabarit unique des documents commerciaux (aperçu écran, impression et PDF dompdf).
 * Variables : $type, $d, $items, $customer, $business, $tpl, $title, $number, $meta, $status, $vat, $fees,
 *             $payments, $allocations, $qr, $shareUrl, $isDraft, $images, $showSignature, $accent, $forPdf
 * CSS volontairement simple (tableaux, pas de flex/grid) pour un rendu identique dans dompdf.
 */
$b = $business;
$nf = static fn($v, int $dec = 2): string => number_fr((float) $v, $dec);
$cur = (string) setting('currency_symbol', 'DH');
$isPayment = $type === 'payment';
$total = $isPayment ? (float) $d['group_total'] : (float) $d['total'];
$net = $isPayment ? 0 : (float) $d['total'] - (float) $d['tax_total'];
$legal = array_filter([
    $b['business_ice'] ? 'ICE : ' . $b['business_ice'] : '', $b['business_if'] ? 'IF : ' . $b['business_if'] : '',
    $b['business_rc'] ? 'RC : ' . $b['business_rc'] : '', $b['business_patente'] ? 'Patente : ' . $b['business_patente'] : '',
    $b['business_cnss'] ? 'CNSS : ' . $b['business_cnss'] : '', $b['business_capital'] ? 'Capital : ' . $b['business_capital'] : '',
]);
$contact = array_filter([trim($b['business_address'] . ' ' . $b['business_city']), $b['business_phone'] ? 'Tél. ' . $b['business_phone'] : '', $b['business_email'], $b['business_website']]);
$terms = trim((string) ($tpl['terms'] ?? ''));
$intro = trim((string) ($tpl['header_text'] ?? '')) ?: ($type === 'quotation' ? trim((string) setting('quotation_intro', '')) : '');
$tplFooter = trim((string) ($tpl['footer_text'] ?? ''));
$cName = trim((string) ($customer['company_name'] ?? '')) !== '' ? $customer['company_name'] : ($customer['full_name'] ?? '');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e(strip_tags($title) . ' ' . ($number ?? '')) ?></title>
<style>
  @page { margin: 12mm 13mm 22mm 13mm; }
  * { box-sizing: border-box; }
  body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9pt; color: #1a1a1a; margin: 0; line-height: 1.35; }
  <?php if (!$forPdf): ?>
  html { background: #d4d4d8; } body { background: #fff; width: auto; max-width: 210mm; min-height: 297mm; margin: 12px auto; padding: 14mm 13mm 30mm; box-shadow: 0 2px 18px rgba(0,0,0,.15); position: relative; }
  @media screen and (max-width: 820px) { body { margin: 0; padding: 5mm 4mm 26mm; font-size: 8pt; min-height: 0; } .footer { left: 4mm !important; right: 4mm !important; } .lines th, .lines td { padding-left: 1mm; padding-right: 1mm; } }
  @media print { html { background: #fff; } body { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; } }
  <?php endif; ?>
  table { border-collapse: collapse; width: 100%; }
  td, th { vertical-align: top; }
  .head td { padding: 0; }
  .logo { max-height: 22mm; max-width: 60mm; margin-bottom: 2mm; }
  .bname { font-size: 13pt; font-weight: bold; }
  .muted { color: #6b6b6b; }
  .small { font-size: 7.5pt; }
  .titlebox { border: 1px solid <?= e($accent) ?>; border-top: 4px solid #ff3d00; padding: 3mm 4mm; }
  .doctitle { font-size: 16pt; font-weight: bold; letter-spacing: .5px; color: <?= e($accent) ?>; }
  .meta td { padding: .4mm 0; font-size: 8.5pt; }
  .meta td.k { color: #6b6b6b; width: 42%; }
  .client { border: 1px solid #d4d4d8; border-radius: 2mm; padding: 2.5mm 4mm; margin-top: 4mm; }
  .client .lbl { font-size: 7pt; text-transform: uppercase; letter-spacing: .6px; color: #6b6b6b; }
  .lines { margin-top: 5mm; }
  .lines th { background: <?= e($accent) ?>; color: #fff; font-weight: bold; font-size: 8pt; padding: 2mm 1.6mm; text-align: left; }
  .lines td { padding: 1.4mm 1.6mm; border-bottom: 1px solid #e4e4e7; }
  .lines tr.alt td { background: #fafafa; }
  .r { text-align: right; } .c { text-align: center; } .nw { white-space: nowrap; }
  .totals td { padding: .9mm 2mm; }
  .totals tr.grand td { background: <?= e($accent) ?>; color: #fff; font-weight: bold; font-size: 11pt; }
  .totals tr.due td { font-weight: bold; color: #b91c1c; font-size: 10pt; border-top: 1px solid #d4d4d8; }
  .box { border: 1px solid #e4e4e7; padding: 2.5mm 3mm; }
  .boxtitle { font-weight: bold; font-size: 8pt; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 1.5mm; }
  .vat th, .vat td { font-size: 7.5pt; padding: 1mm 1.5mm; border-bottom: 1px solid #eee; }
  .words { margin-top: 3mm; font-size: 8.5pt; }
  .sign { height: 19mm; border: 1px dashed #c4c4c8; padding: 2mm; }
  .sign img { max-height: 14mm; max-width: 45mm; }
  .footer { position: fixed; bottom: -15mm; left: 0; right: 0; border-top: 1px solid #d4d4d8; padding-top: 1.5mm; text-align: center; font-size: 7pt; color: #6b6b6b; }
  <?php if (!$forPdf): ?>.footer { position: absolute; bottom: 10mm; left: 13mm; right: 13mm; }<?php endif; ?>
  .draft { position: fixed; top: 110mm; left: 0; right: 0; text-align: center; font-size: 60pt; color: rgba(185, 28, 28, .12); font-weight: bold; transform: rotate(-30deg); }
  .badge { display: inline-block; padding: .5mm 2mm; border-radius: 1.5mm; font-size: 7.5pt; font-weight: bold; color: #fff; background: #6b6b6b; }
  .stamp-paid { color: #15803d; border: 2px solid #15803d; padding: 1mm 3mm; font-weight: bold; font-size: 12pt; display: inline-block; transform: rotate(-6deg); }
</style>
</head>
<body>
<?php if ($isDraft): ?><div class="draft">BROUILLON</div><?php endif; ?>

<table class="head"><tr>
  <td style="width:55%">
    <?php if ($images['logo']): ?><img class="logo" src="<?= $images['logo'] ?>" alt=""><br><?php endif; ?>
    <div class="bname"><?= e($b['business_name']) ?></div>
    <?php if ($b['business_activity']): ?><div class="muted"><?= e($b['business_activity']) ?></div><?php endif; ?>
    <?php foreach ($contact as $line): ?><div class="small"><?= e($line) ?></div><?php endforeach; ?>
  </td>
  <td style="width:45%">
    <div class="titlebox">
      <div class="doctitle"><?= e($title) ?></div>
      <table class="meta">
        <tr><td class="k">N°</td><td><strong><?= e($number ?? 'Non numéroté (brouillon)') ?></strong></td></tr>
        <?php foreach ($meta as $k => $v): ?><tr><td class="k"><?= e($k) ?></td><td><?= e($v) ?></td></tr><?php endforeach; ?>
        <?php if ($type === 'invoice' && in_array($d['status'], ['paid'], true)): ?><tr><td class="k">Statut</td><td><span class="stamp-paid">PAYÉE</span></td></tr><?php endif; ?>
        <?php if ($type === 'invoice' && $d['status'] === 'cancelled'): ?><tr><td class="k">Statut</td><td><span class="badge" style="background:#111">ANNULÉE</span></td></tr><?php endif; ?>
      </table>
    </div>
  </td>
</tr></table>

<div class="client">
  <table><tr>
    <td style="width:60%">
      <div class="lbl"><?= $isPayment ? 'Reçu de' : 'Client' ?></div>
      <div style="font-size:10.5pt;font-weight:bold"><?= e($cName) ?></div>
      <?php if (!empty($customer['company_name']) && !empty($customer['full_name'])): ?><div>À l'attention de : <?= e($customer['full_name']) ?></div><?php endif; ?>
      <?php if (trim(($customer['address'] ?? '') . ($customer['city'] ?? '')) !== ''): ?><div><?= e(trim(($customer['address'] ?? '') . ' ' . ($customer['city'] ?? ''))) ?></div><?php endif; ?>
      <?php if (!empty($customer['phone'])): ?><div class="small">Tél. <?= e($customer['phone']) ?><?= !empty($customer['email']) ? ' · ' . e($customer['email']) : '' ?></div><?php endif; ?>
    </td>
    <td style="width:40%" class="small">
      <div class="lbl">Identifiants</div>
      <div>Code client : <?= e($customer['code'] ?? '—') ?></div>
      <?php if (!empty($customer['ice'])): ?><div>ICE : <?= e($customer['ice']) ?></div><?php endif; ?>
      <?php if (!empty($customer['if_number'])): ?><div>IF : <?= e($customer['if_number']) ?></div><?php endif; ?>
      <?php if (!empty($customer['rc'])): ?><div>RC : <?= e($customer['rc']) ?></div><?php endif; ?>
    </td>
  </tr></table>
</div>

<?php if (!empty($d['title'])): ?><p style="margin:5mm 0 0"><strong>Objet :</strong> <?= e($d['title']) ?></p><?php endif; ?>
<?php if ($intro !== ''): ?><p style="margin:3mm 0 0"><?= nl2br(e($intro)) ?></p><?php endif; ?>

<?php if (!$isPayment): ?>
<table class="lines">
  <thead><tr><th style="width:44%">Désignation</th><th class="r">Qté</th><th>Unité</th><th class="r">P.U. HT</th><th class="r">Rem.</th><th class="r">TVA</th><th class="r">Total HT</th></tr></thead>
  <tbody>
  <?php foreach ($items as $i => $it): $gross = round((float) $it['quantity'] * (float) $it['unit_price'] * (1 - (float) ($it['discount_percent'] ?? 0) / 100), 2); ?>
    <tr<?= $i % 2 ? ' class="alt"' : '' ?>>
      <td><?= nl2br(e($it['description'])) ?></td>
      <td class="r nw"><?= e($nf($it['quantity'], (float) $it['quantity'] == floor((float) $it['quantity']) ? 0 : 3)) ?></td>
      <td><?= e($it['symbol'] ?? '') ?></td>
      <td class="r nw"><?= e($nf($it['unit_price'])) ?></td>
      <td class="r nw"><?= (float) ($it['discount_percent'] ?? 0) > 0 ? e($nf($it['discount_percent'], 1)) . ' %' : '' ?></td>
      <td class="r nw"><?= e($nf($it['tax_rate'], 0)) ?> %</td>
      <td class="r nw"><?= e($nf($type === 'credit_note' ? $it['line_subtotal'] : $gross)) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php foreach ($fees as $label => $v): ?>
    <tr><td><?= e($label) ?></td><td class="r">1</td><td></td><td class="r nw"><?= e($nf($v)) ?></td><td></td><td></td><td class="r nw"><?= e($nf($v)) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<table class="lines">
  <thead><tr><th>Affectation du règlement</th><th class="r">Montant du document</th><th class="r">Montant réglé</th><th class="r">Reste dû</th></tr></thead>
  <tbody>
  <?php foreach ($allocations as $a): ?>
    <tr><td><?= e($a['label']) ?></td><td class="r"><?= $a['total'] !== null ? e($nf($a['total'])) : '' ?></td><td class="r"><strong><?= e($nf($a['amount'])) ?></strong></td><td class="r"><?= $a['rest'] !== null ? e($nf($a['rest'])) : '' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<table style="margin-top:5mm"><tr>
  <td style="width:55%;padding-right:5mm">
    <?php if (!$isPayment && setting('tax_enabled', true) && $vat): ?>
      <table class="vat"><thead><tr><th style="text-align:left">Taux TVA</th><th class="r">Base HT</th><th class="r">Montant TVA</th></tr></thead><tbody>
        <?php foreach ($vat as $v): ?><tr><td><?= e($nf($v['rate'], $v['rate'] == floor($v['rate']) ? 0 : 1)) ?> %</td><td class="r"><?= e($nf($v['base'])) ?></td><td class="r"><?= e($nf($v['tax'])) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <?php if ($payments): ?>
      <div class="box" style="margin-top:3mm"><div class="boxtitle">Règlements reçus</div>
        <table class="vat"><?php foreach ($payments as $p): ?><tr><td><?= e(format_date($p['payment_date'])) ?></td><td><?= e($p['method']) ?><?= $p['reference'] ? ' (' . e($p['reference']) . ')' : '' ?></td><td class="r"><?= e($nf($p['amount'])) ?></td></tr><?php endforeach; ?></table></div>
    <?php endif; ?>
  </td>
  <td style="width:45%">
    <table class="totals">
      <?php if (!$isPayment): ?>
        <?php if ((float) ($d['discount_total'] ?? 0) > 0): ?>
          <tr><td>Sous-total HT</td><td class="r nw"><?= e($nf($d['subtotal'])) ?></td></tr>
          <tr><td>Remise</td><td class="r nw">− <?= e($nf($d['discount_total'])) ?></td></tr>
        <?php endif; ?>
        <tr><td>Total HT</td><td class="r nw"><?= e($nf($net)) ?></td></tr>
        <tr><td>TVA</td><td class="r nw"><?= e($nf($d['tax_total'])) ?></td></tr>
        <tr class="grand"><td>Total TTC</td><td class="r nw"><?= e($nf($total)) ?> <?= e($cur) ?></td></tr>
        <?php if ($type === 'invoice' && (float) ($d['deposits_deducted'] ?? 0) > 0): ?><tr><td class="small muted" colspan="2">Acomptes déjà facturés et déduits : <?= e($nf($d['deposits_deducted'])) ?> <?= e($cur) ?></td></tr><?php endif; ?>
        <?php if ($type === 'invoice' && !$isDraft): ?>
          <tr><td>Déjà réglé</td><td class="r nw"><?= e($nf((float) $d['paid_amount'] + (float) $d['credited_amount'])) ?></td></tr>
          <tr class="due"><td>Reste à payer</td><td class="r nw"><?= e($nf($d['balance_due'])) ?> <?= e($cur) ?></td></tr>
        <?php endif; ?>
      <?php else: ?>
        <tr class="grand"><td>Montant reçu</td><td class="r nw"><?= e($nf($total)) ?> <?= e($cur) ?></td></tr>
      <?php endif; ?>
    </table>
  </td>
</tr></table>

<p class="words"><?= $isPayment ? 'Reçu la somme de' : ($type === 'credit_note' ? 'Arrêté le présent avoir à la somme de' : ($type === 'quotation' ? 'Arrêté le présent devis à la somme de' : 'Arrêtée la présente ' . ($type === 'sales_order' ? 'commande' : 'facture') . ' à la somme de')) ?> :
  <strong><?= e(amount_in_words($total)) ?><?= $isPayment ? '' : ' TTC' ?></strong>.</p>

<?php
$blocks = [];
if (!empty($d['payment_terms'])) { $blocks['Conditions de paiement'] = $d['payment_terms']; }
if (!empty($d['notes'])) { $blocks['Remarques'] = $d['notes']; }
if ($type === 'quotation' && setting('quotation_footer')) { $blocks['Conditions'] = setting('quotation_footer'); }
if ($type === 'invoice' && setting('invoice_footer')) { $blocks['Mentions'] = setting('invoice_footer'); }
if ($terms !== '') { $blocks['Conditions générales'] = $terms; }
?>
<?php foreach ($blocks as $label => $text): ?>
  <div class="small" style="margin-top:2mm"><strong><?= e($label) ?> :</strong> <?= nl2br(e($text)) ?></div>
<?php endforeach; ?>
<?php if (in_array($type, ['invoice', 'quotation'], true) && $b['bank_rib']): ?>
  <div class="small" style="margin-top:2mm"><strong>Règlement par virement :</strong> <?= e($b['bank_name']) ?> — RIB <?= e($b['bank_rib']) ?><?= $b['bank_swift'] ? ' — SWIFT ' . e($b['bank_swift']) : '' ?></div>
<?php endif; ?>

<table style="margin-top:4mm;page-break-inside:avoid"><tr>
  <?php if ($qr): ?>
    <td style="width:24%" class="small muted"><img src="<?= $qr ?>" alt="" style="width:18mm;height:18mm"><br>Scannez pour vérifier<br>ce document en ligne</td>
  <?php endif; ?>
  <?php if ($type === 'quotation'): ?>
    <td style="width:38%;padding-right:3mm"><div class="sign"><div class="small"><strong>Bon pour accord</strong> — date, signature et cachet du client</div></div></td>
  <?php endif; ?>
  <?php if ($showSignature): ?>
    <td style="width:38%"><div class="sign"><div class="small"><strong><?= e($b['business_name']) ?></strong></div>
      <?php if ($images['signature']): ?><img src="<?= $images['signature'] ?>" alt=""><?php endif; ?>
      <?php if ($images['stamp']): ?><img src="<?= $images['stamp'] ?>" alt=""><?php endif; ?></div></td>
  <?php endif; ?>
</tr></table>

<div class="footer">
  <strong><?= e($b['business_name']) ?></strong><?= $contact ? ' — ' . e(implode(' · ', $contact)) : '' ?><br>
  <?= e(implode(' · ', $legal)) ?><?= $tplFooter !== '' ? '<br>' . e($tplFooter) : '' ?>
</div>
</body>
</html>
