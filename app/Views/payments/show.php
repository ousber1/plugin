<?php /** Fiche d'un paiement. Variables : $pay, $customerName, $allocations, $group, $openInvoices, $send */
$refund = $pay['direction'] === 'refund'; $off = $pay['status'] === 'cancelled'; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= $refund ? 'Remboursement' : 'Reçu' ?> <?= e($pay['number']) ?> <?= status_badge('payment', $pay['status']) ?></h1>
    <p class="text-body-secondary mb-0"><a href="<?= e(url('customers/' . $pay['customer_id'])) ?>"><?= e($customerName) ?></a> · <?= e(money($pay['amount'])) ?> · <?= e($pay['method']) ?> · <?= e(format_date($pay['payment_date'], true)) ?></p></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('documents/payment/' . $pay['id'] . '/pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Reçu PDF</a>
    <div class="dropdown"><button class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-send"></i> Envoyer</button>
      <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mailModal"><i class="bi bi-envelope"></i> Par e-mail</button></li>
        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#waModal"><i class="bi bi-whatsapp"></i> Par WhatsApp</button></li></ul></div>
    <?php if (!$off && !$refund && can('payments.cancel')): ?><button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancelBox"><i class="bi bi-x-circle"></i> Annuler</button><?php endif; ?>
  </div>
</div>
<?php if (!$off && !$refund && can('payments.cancel')): ?>
  <div class="collapse mb-3" id="cancelBox"><form method="post" action="<?= e(url('payments/' . $pay['id'] . '/cancel')) ?>" class="card border-danger" data-confirm="Annuler ce paiement ? Les factures imputées redeviendront dues<?= $pay['cash_session_id'] ? ' et la caisse sera contre-passée' : '' ?>."><div class="card-body d-flex flex-wrap gap-2 align-items-end"><?= csrf_field() ?>
    <div class="flex-grow-1"><label class="form-label small" for="reason">Motif *</label><input class="form-control" id="reason" name="reason" required minlength="3" maxlength="255" placeholder="Ex. chèque impayé, erreur de saisie…"></div>
    <button class="btn btn-danger">Confirmer l'annulation</button></div></form></div>
<?php endif; ?>
<?php if ($off): ?><div class="alert alert-dark"><i class="bi bi-x-octagon"></i> Annulé le <?= e(format_date($pay['cancelled_at'], true)) ?> par <?= e($pay['cancelled_name'] ?? '') ?> : <?= e($pay['cancel_reason']) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-8"><iframe class="hoc-doc-preview" style="min-height:820px" src="<?= e(url('documents/payment/' . $pay['id'] . '/preview')) ?>" title="Aperçu du reçu"></iframe></div>
  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header fw-semibold">Détail</div><div class="card-body small">
      <div class="hoc-mini"><span>Montant</span><strong><?= e(money($pay['amount'])) ?></strong></div>
      <div class="hoc-mini"><span>Mode</span><span><?= e($pay['method']) ?></span></div>
      <?php if ($pay['reference']): ?><div class="hoc-mini"><span>Référence</span><span><?= e($pay['reference']) ?></span></div><?php endif; ?>
      <div class="hoc-mini"><span>Saisi par</span><span><?= e($pay['user_name'] ?? '') ?></span></div>
      <?php if ($pay['sale_number']): ?><div class="hoc-mini"><span>Ticket</span><a href="<?= e(url('sales/' . $pay['sale_id'])) ?>"><?= e($pay['sale_number']) ?></a></div><?php endif; ?>
      <?php if ($pay['credit_number']): ?><div class="hoc-mini"><span>Avoir remboursé</span><a href="<?= e(url('credit-notes/' . $pay['credit_note_id'])) ?>"><?= e($pay['credit_number']) ?></a></div><?php endif; ?>
      <?php if ((float) $pay['unallocated_amount'] > 0.004): ?><div class="hoc-mini text-warning-emphasis fw-semibold"><span>Avance non imputée</span><span><?= e(money($pay['unallocated_amount'])) ?></span></div><?php endif; ?>
      <?php if ($pay['notes']): ?><div class="mt-2"><?= e($pay['notes']) ?></div><?php endif; ?>
    </div>
    <?php if ($allocations): ?><ul class="list-group list-group-flush small">
      <?php foreach ($allocations as $a): ?><li class="list-group-item d-flex justify-content-between"><span>Facture <a href="<?= e(url('invoices/' . $a['invoice_id'])) ?>"><?= e($a['number']) ?></a> <?= status_badge('invoice', $a['status']) ?></span><strong><?= e(money($a['amount'])) ?></strong></li><?php endforeach; ?>
    </ul><?php endif; ?></div>
    <?php if ($openInvoices && !$off && can('payments.create')): ?>
      <div class="card mb-3"><div class="card-header fw-semibold">Imputer l'avance</div><div class="card-body">
        <form method="post" action="<?= e(url('payments/' . $pay['id'] . '/allocate')) ?>" class="row g-2"><?= csrf_field() ?>
          <div class="col-12"><select class="form-select form-select-sm" name="invoice_id" aria-label="Facture"><?php foreach ($openInvoices as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['number']) ?> — reste <?= e(money($i['balance_due'])) ?></option><?php endforeach; ?></select></div>
          <div class="col-7"><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="amount" value="<?= e((string) min((float) $pay['unallocated_amount'], (float) $openInvoices[0]['balance_due'])) ?>" aria-label="Montant"></div>
          <div class="col-5 d-grid"><button class="btn btn-sm btn-dark">Imputer</button></div>
        </form></div></div>
    <?php endif; ?>
    <?php if ($group): ?><div class="card mb-3"><div class="card-header fw-semibold">Même règlement</div><ul class="list-group list-group-flush small">
      <?php foreach ($group as $g): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('payments/' . $g['id'])) ?>"><?= e($g['number']) ?></a><span><?= e(money($g['amount'])) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php \App\Core\View::partial('doc_side', ['type' => 'payment', 'id' => (int) $pay['id'], 'send' => $send]); ?>
  </div>
</div>
<?php \App\Core\View::partial('doc_send', ['type' => 'payment', 'id' => (int) $pay['id'], 'send' => $send]); ?>
