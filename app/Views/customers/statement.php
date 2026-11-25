<?php
/** Relevé de compte client (impression). Variables : $c, $from, $to, $st */
$kinds = ['invoice' => 'Facture', 'sale' => 'Ticket', 'payment' => 'Paiement', 'refund' => 'Remboursement', 'credit_note' => 'Avoir'];
?>
<div class="d-flex justify-content-between align-items-start mb-4 gap-3">
  <div>
    <div class="doc-title">RELEVÉ DE COMPTE</div>
    <div>Période du <strong><?= e(format_date($from)) ?></strong> au <strong><?= e(format_date($to)) ?></strong></div>
    <div class="text-secondary small">Édité le <?= e(format_date(date('Y-m-d H:i:s'), true)) ?></div>
  </div>
  <div class="border rounded p-3" style="min-width:260px">
    <div class="small text-secondary">Client <?= e($c['code']) ?></div>
    <div class="fw-bold"><?= e(\App\Services\CustomerService::displayName($c)) ?></div>
    <?php if ($c['company_name']): ?><div><?= e($c['full_name']) ?></div><?php endif; ?>
    <div><?= e(trim(($c['address'] ?? '') . ' ' . ($c['city'] ?? ''))) ?></div>
    <?php if ($c['ice']): ?><div class="small">ICE : <?= e($c['ice']) ?></div><?php endif; ?>
  </div>
</div>

<form method="get" class="no-print d-flex flex-wrap gap-2 align-items-end mb-3" action="<?= e(url('customers/' . $c['id'] . '/statement')) ?>"><?= route_field('customers/' . $c['id'] . '/statement') ?>
  <div><label class="form-label small mb-0" for="from">Du</label><input class="form-control form-control-sm" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
  <div><label class="form-label small mb-0" for="to">Au</label><input class="form-control form-control-sm" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-sm btn-outline-dark">Afficher</button>
</form>

<table class="table table-sm table-bordered align-middle">
  <thead><tr><th style="width:90px">Date</th><th>Type</th><th>Pièce</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Solde</th></tr></thead>
  <tbody>
    <tr class="table-light"><td colspan="5" class="fw-semibold">Solde au <?= e(format_date($from)) ?></td><td class="text-end fw-semibold"><?= e(money($st['opening'])) ?></td></tr>
    <?php if (!$st['rows']): ?><tr><td colspan="6" class="text-center text-secondary">Aucune opération sur la période.</td></tr><?php endif; ?>
    <?php foreach ($st['rows'] as $r): ?>
      <tr>
        <td><?= e(format_date($r['entry_date'])) ?></td>
        <td><?= e($kinds[$r['kind']] ?? $r['kind']) ?></td>
        <td><?= e($r['ref']) ?></td>
        <td class="text-end"><?= (float) $r['debit'] ? e(money($r['debit'])) : '' ?></td>
        <td class="text-end"><?= (float) $r['credit'] ? e(money($r['credit'])) : '' ?></td>
        <td class="text-end"><?= e(money($r['balance'])) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="fw-semibold"><td colspan="3" class="text-end">Totaux de la période</td><td class="text-end"><?= e(money($st['debit'])) ?></td><td class="text-end"><?= e(money($st['credit'])) ?></td><td></td></tr>
    <tr class="fw-bold fs-6"><td colspan="5" class="text-end">Solde au <?= e(format_date($to)) ?> <?= $st['closing'] > 0.004 ? '(dû par le client)' : ($st['closing'] < -0.004 ? '(en faveur du client)' : '') ?></td><td class="text-end doc-accent"><?= e(money($st['closing'])) ?></td></tr>
  </tfoot>
</table>
<p class="small text-secondary mt-4">Débit : factures et tickets émis, remboursements. Crédit : paiements reçus et avoirs.
  Sauf erreur ou omission. Pour toute question, contactez-nous<?= setting('business_phone') ? ' au ' . e(setting('business_phone')) : '' ?>.</p>
<?php if (setting('bank_rib')): ?><p class="small mb-0">Règlement par virement : <?= e(setting('bank_name')) ?> — RIB <strong><?= e(setting('bank_rib')) ?></strong></p><?php endif; ?>
