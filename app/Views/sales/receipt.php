<?php
/**
 * Ticket de caisse thermique (58 ou 80 mm). Page autonome, sans gabarit.
 * Variables : $sale, $items, $payments, $vat, $width, $autoPrint
 */
$logo = (string) setting('business_logo', '');
$in = array_values(array_filter($payments, static fn($p) => $p['direction'] === 'in' && $p['status'] === 'valid'));
$due = max(0, round((float) $sale['total'] - (float) $sale['paid_amount'], 2));
$name = \App\Controllers\SaleController::customerName($sale);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title) ?></title>
<style>
  @page { size: <?= (int) $width ?>mm auto; margin: 0; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e4e4e7; font-family: "DejaVu Sans Mono", "Courier New", monospace; color: #000; }
  .ticket { width: <?= (int) $width ?>mm; margin: 12px auto; background: #fff; padding: 4mm <?= $width === 58 ? 2 : 4 ?>mm; font-size: <?= $width === 58 ? 10 : 11.5 ?>px; line-height: 1.35; }
  .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
  .logo { max-width: 70%; max-height: 22mm; filter: grayscale(1); }
  .big { font-size: 1.35em; }
  hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
  table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: .2mm 0; }
  .item td { padding-top: 1mm; }
  .muted { color: #333; font-size: .92em; }
  .toolbar { text-align: center; margin: 10px; font-family: system-ui, sans-serif; }
  .toolbar button, .toolbar a { padding: 6px 12px; margin: 0 3px; border-radius: 6px; border: 1px solid #999; background: #fff; color: #000; text-decoration: none; font-size: 13px; cursor: pointer; }
  @media print { body { background: #fff; } .ticket { margin: 0; } .toolbar { display: none; } }
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">Imprimer</button>
  <a href="?<?= e(http_build_query(array_merge(array_diff_key($_GET, ['print' => 1]), ['w' => $width === 80 ? 58 : 80]))) ?>">Format <?= $width === 80 ? '58' : '80' ?> mm</a>
  <button onclick="window.close()">Fermer</button>
</div>
<div class="ticket">
  <div class="c">
    <?php if ($logo !== ''): ?><img class="logo" src="<?= e(asset($logo)) ?>" alt=""><br><?php endif; ?>
    <div class="b big"><?= e(setting('business_name')) ?></div>
    <?php foreach (array_filter([setting('business_address'), setting('business_city'), setting('business_phone') ? 'Tél. ' . setting('business_phone') : '',
        setting('business_ice') ? 'ICE ' . setting('business_ice') : '']) as $line): ?><div class="muted"><?= e($line) ?></div><?php endforeach; ?>
    <?php if (setting('receipt_header')): ?><div style="white-space:pre-line;margin-top:1mm"><?= e(setting('receipt_header')) ?></div><?php endif; ?>
  </div>
  <hr>
  <table>
    <tr><td>Ticket</td><td class="r b"><?= e($sale['number']) ?></td></tr>
    <tr><td>Date</td><td class="r"><?= e(format_date($sale['sale_date'], true)) ?></td></tr>
    <tr><td>Vendeur</td><td class="r"><?= e($sale['user_name'] ?? '') ?></td></tr>
    <?php if (!$sale['is_walk_in']): ?><tr><td>Client</td><td class="r"><?= e($name) ?></td></tr><?php if ($sale['ice']): ?><tr><td>ICE</td><td class="r"><?= e($sale['ice']) ?></td></tr><?php endif; ?><?php endif; ?>
  </table>
  <?php if ($sale['status'] === 'cancelled'): ?><hr><div class="c b big">*** VENTE ANNULÉE ***</div><?php endif; ?>
  <hr>
  <table>
    <?php foreach ($items as $it): $ttcUnit = (float) $it['unit_price'] * (1 + (float) $it['tax_rate'] / 100); ?>
      <tr class="item"><td colspan="2" class="b"><?= e($it['description']) ?></td></tr>
      <tr><td class="muted"><?= e(number_fr($it['quantity'], (float) $it['quantity'] == floor((float) $it['quantity']) ? 0 : 2)) ?> <?= e($it['symbol'] ?? '') ?> × <?= e(number_fr($ttcUnit)) ?><?= (float) $it['discount_percent'] > 0 ? ' −' . e(number_fr($it['discount_percent'], 1)) . '%' : '' ?></td>
        <td class="r"><?= e(number_fr(\App\Controllers\SaleController::lineGross($it))) ?></td></tr>
      <?php if ($it['notes']): ?><tr><td colspan="2" class="muted">  › <?= e($it['notes']) ?></td></tr><?php endif; ?>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <?php if ((float) $sale['discount_total'] > 0): $grossTtc = array_sum(array_map([\App\Controllers\SaleController::class, 'lineGross'], $items)); ?>
      <tr><td>Sous-total</td><td class="r"><?= e(number_fr($grossTtc)) ?></td></tr>
      <tr><td>Remise</td><td class="r">−<?= e(number_fr($grossTtc - (float) $sale['total'])) ?></td></tr><?php endif; ?>
    <tr class="b big"><td>TOTAL TTC</td><td class="r"><?= e(money($sale['total'])) ?></td></tr>
    <?php foreach ($in as $p): ?><tr><td><?= e($p['method']) ?></td><td class="r"><?= e(number_fr((float) $p['amount'] + ((int) $p['affects_cash'] === 1 ? (float) $sale['change_amount'] : 0))) ?></td></tr><?php endforeach; ?>
    <?php if ((float) $sale['change_amount'] > 0): ?><tr class="b"><td>Rendu</td><td class="r"><?= e(number_fr($sale['change_amount'])) ?></td></tr><?php endif; ?>
    <?php if ($due > 0): ?><tr class="b"><td>Reste dû (compte client)</td><td class="r"><?= e(number_fr($due)) ?></td></tr><?php endif; ?>
    <?php if ((float) $sale['refunded_amount'] > 0): ?><tr><td>Remboursé</td><td class="r">−<?= e(number_fr($sale['refunded_amount'])) ?></td></tr><?php endif; ?>
  </table>
  <?php if (setting('tax_enabled', true)): ?>
  <hr>
  <table class="muted">
    <tr><td>TVA</td><td class="r">Base HT</td><td class="r">Montant</td></tr>
    <?php foreach ($vat as $v): ?><tr><td><?= e(number_fr($v['rate'], $v['rate'] == floor($v['rate']) ? 0 : 1)) ?> %</td><td class="r"><?= e(number_fr($v['base'])) ?></td><td class="r"><?= e(number_fr($v['tax'])) ?></td></tr><?php endforeach; ?>
  </table>
  <?php endif; ?>
  <hr>
  <div class="c muted"><?= e(amount_in_words((float) $sale['total'])) ?></div>
  <?php if (setting('receipt_footer')): ?><div class="c" style="white-space:pre-line;margin-top:2mm"><?= e(setting('receipt_footer')) ?></div><?php endif; ?>
  <div class="c muted" style="margin-top:2mm"><?= e(implode(' · ', array_filter([setting('business_website'), setting('business_whatsapp') ? 'WhatsApp ' . setting('business_whatsapp') : '']))) ?></div>
</div>
<?php if ($autoPrint): ?><script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });</script><?php endif; ?>
</body>
</html>
