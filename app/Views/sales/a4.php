<?php
/** Bon de vente A4 (impression / PDF navigateur). Variables : $sale, $items, $payments, $vat */
$due = max(0, round((float) $sale['total'] - (float) $sale['paid_amount'], 2));
$in = array_values(array_filter($payments, static fn($p) => $p['direction'] === 'in' && $p['status'] === 'valid'));
?>
<div class="d-flex justify-content-between align-items-start mb-4 gap-3">
  <div>
    <div class="doc-title">BON DE VENTE</div>
    <div>N° <strong><?= e($sale['number']) ?></strong> du <?= e(format_date($sale['sale_date'], true)) ?></div>
    <div class="small text-secondary">Vendeur : <?= e($sale['user_name'] ?? '') ?><?= $sale['session_number'] ? ' · caisse ' . e($sale['session_number']) : '' ?></div>
    <?php if ($sale['status'] === 'cancelled'): ?><div class="badge text-bg-dark fs-6 mt-2">VENTE ANNULÉE</div><?php endif; ?>
  </div>
  <div class="border rounded p-3" style="min-width:260px">
    <div class="small text-secondary">Client <?= e($sale['customer_code']) ?></div>
    <div class="fw-bold"><?= e(\App\Controllers\SaleController::customerName($sale)) ?></div>
    <?php if ($sale['company_name']): ?><div><?= e($sale['full_name']) ?></div><?php endif; ?>
    <div><?= e(trim(($sale['address'] ?? '') . ' ' . ($sale['city'] ?? ''))) ?></div>
    <?php if ($sale['phone']): ?><div class="small">Tél. <?= e($sale['phone']) ?></div><?php endif; ?>
    <?php if ($sale['ice']): ?><div class="small">ICE : <?= e($sale['ice']) ?></div><?php endif; ?>
  </div>
</div>
<table class="table table-sm table-bordered align-middle">
  <thead><tr><th>Désignation</th><th class="text-end">Qté</th><th class="text-end">PU HT</th><th class="text-end">Rem.</th><th class="text-end">TVA</th><th class="text-end">Total HT</th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr><td><?= e($it['description']) ?><?= $it['notes'] ? '<br><small class="text-secondary">' . e($it['notes']) . '</small>' : '' ?></td>
      <td class="text-end text-nowrap"><?= e(number_fr($it['quantity'], (float) $it['quantity'] == floor((float) $it['quantity']) ? 0 : 3)) ?> <?= e($it['symbol'] ?? '') ?></td>
      <td class="text-end"><?= e(number_fr($it['unit_price'])) ?></td>
      <td class="text-end"><?= (float) $it['discount_percent'] > 0 ? e(number_fr($it['discount_percent'], 1)) . ' %' : '' ?></td>
      <td class="text-end"><?= e(number_fr($it['tax_rate'], 0)) ?> %</td>
      <td class="text-end"><?= e(number_fr(round((float) $it['quantity'] * (float) $it['unit_price'] * (1 - (float) $it['discount_percent'] / 100), 2))) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<div class="row g-3 mt-1">
  <div class="col-7">
    <?php if (setting('tax_enabled', true)): ?>
    <table class="table table-sm table-bordered small"><thead><tr><th>Taux TVA</th><th class="text-end">Base HT</th><th class="text-end">TVA</th></tr></thead><tbody>
      <?php foreach ($vat as $v): ?><tr><td><?= e(number_fr($v['rate'], 0)) ?> %</td><td class="text-end"><?= e(number_fr($v['base'])) ?></td><td class="text-end"><?= e(number_fr($v['tax'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
    <p class="small mb-1"><strong>Arrêté le présent bon à la somme de :</strong><br><?= e(amount_in_words((float) $sale['total'])) ?> TTC.</p>
    <?php if ($in): ?><p class="small mb-0">Règlements : <?= e(implode(', ', array_map(static fn($p) => $p['method'] . ' ' . money($p['amount']) . ($p['reference'] ? ' (' . $p['reference'] . ')' : ''), $in))) ?></p><?php endif; ?>
  </div>
  <div class="col-5">
    <table class="table table-sm">
      <tr><td>Sous-total HT</td><td class="text-end"><?= e(money($sale['subtotal'])) ?></td></tr>
      <?php if ((float) $sale['discount_total'] > 0): ?><tr><td>Remise HT</td><td class="text-end">−<?= e(money($sale['discount_total'])) ?></td></tr><?php endif; ?>
      <tr><td>Total HT</td><td class="text-end"><?= e(money((float) $sale['total'] - (float) $sale['tax_total'])) ?></td></tr>
      <tr><td>TVA</td><td class="text-end"><?= e(money($sale['tax_total'])) ?></td></tr>
      <tr class="fw-bold fs-6"><td>Total TTC</td><td class="text-end doc-accent"><?= e(money($sale['total'])) ?></td></tr>
      <tr><td>Réglé</td><td class="text-end"><?= e(money($sale['paid_amount'])) ?></td></tr>
      <?php if ($due > 0): ?><tr class="fw-semibold"><td>Reste dû</td><td class="text-end"><?= e(money($due)) ?></td></tr><?php endif; ?>
      <?php if ((float) $sale['refunded_amount'] > 0): ?><tr><td>Remboursé</td><td class="text-end">−<?= e(money($sale['refunded_amount'])) ?></td></tr><?php endif; ?>
    </table>
  </div>
</div>
<div class="row mt-4 small"><div class="col-6"><div class="border rounded p-2" style="height:80px">Signature client</div></div><div class="col-6"><div class="border rounded p-2" style="height:80px">Cachet <?= e(setting('business_name')) ?></div></div></div>
<p class="small text-secondary mt-3 mb-0">Ce bon de vente ne remplace pas une facture. Une facture peut être établie sur demande à partir de ce ticket.</p>
