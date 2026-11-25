<?php
/** Fiche facture. Variables : $inv, $customer, $customerName, $payments, $creditNotes, $creditApplied, $deposits, $balanceInvoice, $quotation, $sale, $reminders, $send, $remind, $advances */
$st = $inv['status'];
$draft = $st === 'draft';
$open = in_array($st, ['validated', 'unpaid', 'partial', 'overdue'], true) && (float) $inv['balance_due'] > 0.004;
$typeLabel = \App\Services\InvoiceService::TYPES[$inv['invoice_type']] ?? 'Facture';
?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($typeLabel) ?> <?= e($inv['number'] ?? '(brouillon)') ?> <?= status_badge('invoice', $st) ?><?= (int) $inv['is_locked'] ? ' <i class="bi bi-lock-fill text-body-secondary fs-6" title="Document verrouillé"></i>' : '' ?></h1>
    <p class="text-body-secondary mb-0"><a href="<?= e(url('customers/' . $inv['customer_id'])) ?>"><?= e($customerName) ?></a> · <?= e(money($inv['total'])) ?> TTC<?= $open ? ' · <span class="text-danger fw-semibold">reste dû ' . e(money($inv['balance_due'])) . '</span>' : '' ?></p></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($draft): ?>
      <?php if (can('invoices.edit') && !$inv['sale_id']): ?><a class="btn btn-outline-dark" href="<?= e(url('invoices/' . $inv['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
      <?php if (can('invoices.validate')): ?><form method="post" action="<?= e(url('invoices/' . $inv['id'] . '/validate')) ?>" data-confirm="Valider la facture ? Elle recevra son numéro définitif et ne pourra plus être modifiée."><?= csrf_field() ?><button class="btn btn-accent"><i class="bi bi-check2-circle"></i> Valider la facture</button></form><?php endif; ?>
      <?php if (can('invoices.edit')): ?><form method="post" action="<?= e(url('invoices/' . $inv['id'] . '/delete')) ?>" data-confirm="Supprimer ce brouillon ?"><?= csrf_field() ?><button class="btn btn-outline-danger" title="Supprimer le brouillon"><i class="bi bi-trash"></i></button></form><?php endif; ?>
    <?php else: ?>
      <a class="btn btn-outline-secondary" href="<?= e(url('documents/invoice/' . $inv['id'] . '/pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
      <a class="btn btn-outline-secondary" href="<?= e(url('documents/invoice/' . $inv['id'] . '/pdf', ['download' => 1])) ?>" title="Télécharger"><i class="bi bi-download"></i></a>
      <div class="dropdown"><button class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-send"></i> Envoyer</button>
        <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mailModal"><i class="bi bi-envelope"></i> Par e-mail (PDF joint)</button></li>
          <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#waModal"><i class="bi bi-whatsapp"></i> Par WhatsApp</button></li></ul></div>
      <?php if ($open && can('payments.create')): ?><a class="btn btn-accent" href="<?= e(url('payments/create', ['customer' => $inv['customer_id'], 'invoice' => $inv['id']])) ?>"><i class="bi bi-cash-coin"></i> Encaisser</a><?php endif; ?>
      <?php if ($st !== 'cancelled' && can('invoices.credit_note')): ?><a class="btn btn-outline-dark" href="<?= e(url('invoices/' . $inv['id'] . '/credit-note')) ?>"><i class="bi bi-file-earmark-minus"></i> Avoir</a><?php endif; ?>
      <?php if ($st !== 'cancelled' && can('invoices.cancel')): ?><button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancelBox"><i class="bi bi-x-circle"></i> Annuler</button><?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php if (!$draft && $st !== 'cancelled' && can('invoices.cancel')): ?>
  <div class="collapse mb-3" id="cancelBox"><form method="post" action="<?= e(url('invoices/' . $inv['id'] . '/cancel')) ?>" class="card border-danger" data-confirm="Annuler cette facture ? Un avoir total sera émis automatiquement (une facture validée n'est jamais supprimée)."><div class="card-body d-flex flex-wrap gap-2 align-items-end"><?= csrf_field() ?>
    <div class="flex-grow-1"><label class="form-label small" for="reason">Motif de l'annulation *</label><input class="form-control" id="reason" name="reason" required minlength="3" maxlength="255" placeholder="Ex. erreur de client, commande annulée…"></div>
    <button class="btn btn-danger">Annuler par avoir</button></div></form></div>
<?php endif; ?>
<?php if ($draft): ?><div class="alert alert-secondary"><i class="bi bi-pencil-square"></i> Brouillon : non numéroté, non comptabilisé et non envoyable. Validez-le pour émettre la facture.</div><?php endif; ?>
<?php if ($st === 'cancelled'): ?><div class="alert alert-dark"><i class="bi bi-x-octagon"></i> Facture annulée le <?= e(format_date($inv['cancelled_at'], true)) ?> : <?= e($inv['cancel_reason']) ?></div><?php endif; ?>
<?php if ($st === 'overdue'): ?><div class="alert alert-danger"><i class="bi bi-alarm"></i> Échue le <?= e(format_date($inv['due_date'])) ?> (<?= (int) ((time() - strtotime($inv['due_date'])) / 86400) ?> jours de retard)<?= $inv['last_reminder_at'] ? ' · dernière relance le ' . e(format_date($inv['last_reminder_at'], true)) : '' ?>.</div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-8"><iframe class="hoc-doc-preview" src="<?= e(url('documents/invoice/' . $inv['id'] . '/preview')) ?>" title="Aperçu de la facture"></iframe></div>
  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header fw-semibold">Règlement</div><div class="card-body small">
      <div class="hoc-mini"><span>Total TTC</span><strong><?= e(money($inv['total'])) ?></strong></div>
      <?php if ((float) $inv['deposits_deducted'] > 0): ?><div class="hoc-mini text-body-secondary"><span>Dont acomptes déduits</span><span><?= e(money($inv['deposits_deducted'])) ?></span></div><?php endif; ?>
      <div class="hoc-mini"><span>Réglé</span><span><?= e(money($inv['paid_amount'])) ?></span></div>
      <?php if ((float) $inv['credited_amount'] > 0): ?><div class="hoc-mini"><span>Avoirs imputés</span><span><?= e(money($inv['credited_amount'])) ?></span></div><?php endif; ?>
      <div class="hoc-mini fw-bold <?= $open ? 'text-danger' : '' ?>"><span>Reste dû</span><span><?= e(money($draft ? $inv['total'] : $inv['balance_due'])) ?></span></div>
      <div class="hoc-mini"><span>Échéance</span><span><?= e(format_date($inv['due_date'])) ?></span></div>
      <?php if ($open && $advances && can('payments.create')): ?><div class="alert alert-info small mt-2 mb-0 py-2">Avance(s) disponible(s) du client :
        <?php foreach ($advances as $a): $amt = min((float) $a['unallocated_amount'], (float) $inv['balance_due']); ?>
          <form method="post" action="<?= e(url('payments/' . $a['id'] . '/allocate')) ?>" class="d-flex justify-content-between align-items-center mt-1"><?= csrf_field() ?>
            <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>"><input type="hidden" name="amount" value="<?= e((string) $amt) ?>">
            <span><?= e($a['number']) ?> · <?= e(money($a['unallocated_amount'])) ?></span><button class="btn btn-sm btn-dark">Imputer <?= e(money($amt)) ?></button></form>
        <?php endforeach; ?></div><?php endif; ?>
    </div>
    <?php if ($payments): ?><ul class="list-group list-group-flush small">
      <?php foreach ($payments as $p): ?><li class="list-group-item d-flex justify-content-between<?= $p['status'] === 'cancelled' ? ' text-decoration-line-through text-body-secondary' : '' ?>"><span><a href="<?= e(url('payments/' . $p['id'])) ?>"><code><?= e($p['number']) ?></code></a> <?= e($p['method']) ?> · <?= e(format_date($p['payment_date'])) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></span><strong><?= e(money($p['allocated'])) ?></strong></li><?php endforeach; ?>
    </ul><?php endif; ?></div>

    <?php if ($creditNotes || $creditApplied): ?><div class="card mb-3"><div class="card-header fw-semibold">Avoirs</div><ul class="list-group list-group-flush small">
      <?php foreach ($creditNotes as $c): ?><li class="list-group-item"><div class="d-flex justify-content-between"><a href="<?= e(url('credit-notes/' . $c['id'])) ?>"><code><?= e($c['number']) ?></code></a><span><?= status_badge('credit_note', $c['status']) ?> <strong>−<?= e(money($c['total'])) ?></strong></span></div><span class="text-body-secondary"><?= e(format_date($c['issue_date'])) ?> · <?= e($c['reason']) ?></span></li><?php endforeach; ?>
      <?php foreach ($creditApplied as $c): ?><li class="list-group-item d-flex justify-content-between"><span>Imputation de l'avoir <a href="<?= e(url('credit-notes/' . $c['id'])) ?>"><?= e($c['number']) ?></a></span><strong>−<?= e(money($c['amount'])) ?></strong></li><?php endforeach; ?>
    </ul></div><?php endif; ?>

    <?php if ($quotation || $sale || $deposits || $balanceInvoice): ?><div class="card mb-3"><div class="card-header fw-semibold">Documents liés</div><ul class="list-group list-group-flush small">
      <?php if ($quotation): ?><li class="list-group-item">Devis <a href="<?= e(url('quotations/' . $quotation['id'])) ?>"><?= e($quotation['number']) ?></a></li><?php endif; ?>
      <?php if ($sale): ?><li class="list-group-item">Ticket <a href="<?= e(url('sales/' . $sale['id'])) ?>"><?= e($sale['number']) ?></a></li><?php endif; ?>
      <?php foreach ($deposits as $d): ?><li class="list-group-item d-flex justify-content-between"><span>Acompte <a href="<?= e(url('invoices/' . $d['id'])) ?>"><?= e($d['number']) ?></a> déduit</span><span><?= e(money($d['amount'])) ?></span></li><?php endforeach; ?>
      <?php if ($balanceInvoice): ?><li class="list-group-item">Déduit sur la facture de solde <a href="<?= e(url('invoices/' . $balanceInvoice['id'])) ?>"><?= e($balanceInvoice['number'] ?? 'brouillon') ?></a></li><?php endif; ?>
    </ul></div><?php endif; ?>

    <?php if ($open): ?>
    <div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">Relances
      <div class="dropdown"><button class="btn btn-sm btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-bell"></i> Relancer</button>
        <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mailModalR"><i class="bi bi-envelope"></i> Par e-mail</button></li>
          <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#waModalR"><i class="bi bi-whatsapp"></i> Par WhatsApp</button></li>
          <li><button class="dropdown-item" data-bs-toggle="collapse" data-bs-target="#manualReminder"><i class="bi bi-telephone"></i> Appel / courrier (noter)</button></li></ul></div></div>
      <div class="collapse" id="manualReminder"><form class="card-body border-bottom" method="post" action="<?= e(url('invoices/' . $inv['id'] . '/remind')) ?>"><?= csrf_field() ?>
        <select class="form-select form-select-sm mb-2" name="channel" aria-label="Canal"><option value="phone">Appel téléphonique</option><option value="sms">SMS</option><option value="letter">Courrier</option></select>
        <input class="form-control form-control-sm mb-2" name="message" maxlength="2000" placeholder="Compte rendu (ex. promet de payer vendredi)">
        <button class="btn btn-sm btn-dark">Enregistrer la relance</button></form></div>
      <?php if (!$reminders): ?><div class="card-body small text-body-secondary">Aucune relance.</div><?php else: ?><ul class="list-group list-group-flush small">
        <?php foreach ($reminders as $r): ?><li class="list-group-item"><?= e(['email' => 'E-mail', 'whatsapp' => 'WhatsApp', 'phone' => 'Appel', 'sms' => 'SMS', 'letter' => 'Courrier'][$r['channel']] ?? $r['channel']) ?> · <?= e(format_date($r['sent_at'], true)) ?> · <?= e($r['full_name'] ?? '') ?><?= $r['message'] && in_array($r['channel'], ['phone', 'sms', 'letter'], true) ? '<span class="d-block text-body-secondary">' . e($r['message']) . '</span>' : '' ?></li><?php endforeach; ?>
      </ul><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$draft) { \App\Core\View::partial('doc_side', ['type' => 'invoice', 'id' => (int) $inv['id'], 'send' => $send]); } ?>
    <?php if ($inv['internal_notes']): ?><div class="card"><div class="card-body small"><i class="bi bi-lock"></i> <?= nl2br(e($inv['internal_notes'])) ?></div></div><?php endif; ?>
  </div>
</div>
<?php if (!$draft) {
    \App\Core\View::partial('doc_send', ['type' => 'invoice', 'id' => (int) $inv['id'], 'send' => $send]);
    if ($open) { \App\Core\View::partial('doc_send', ['type' => 'invoice', 'id' => (int) $inv['id'], 'send' => $remind, 'reminder' => true]); }
} ?>
