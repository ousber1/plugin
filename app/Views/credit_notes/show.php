<?php /** Fiche avoir. Variables : $cn, $remaining, $customerName, $allocations, $refunds, $openInvoices, $methods, $send */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Avoir <?= e($cn['number']) ?> <?= status_badge('credit_note', $cn['status']) ?> <i class="bi bi-lock-fill text-body-secondary fs-6" title="Document verrouillé"></i></h1>
    <p class="text-body-secondary mb-0"><a href="<?= e(url('customers/' . $cn['customer_id'])) ?>"><?= e($customerName) ?></a> · <?= e(money($cn['total'])) ?> TTC<?= $cn['invoice_id'] ? ' · facture <a href="' . e(url('invoices/' . $cn['invoice_id'])) . '">' . e($cn['invoice_number']) . '</a>' : '' ?></p></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('documents/credit_note/' . $cn['id'] . '/pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <div class="dropdown"><button class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-send"></i> Envoyer</button>
      <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mailModal"><i class="bi bi-envelope"></i> Par e-mail</button></li>
        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#waModal"><i class="bi bi-whatsapp"></i> Par WhatsApp</button></li></ul></div>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-8"><iframe class="hoc-doc-preview" src="<?= e(url('documents/credit_note/' . $cn['id'] . '/preview')) ?>" title="Aperçu de l'avoir"></iframe></div>
  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header fw-semibold">Utilisation</div><div class="card-body small">
      <div class="hoc-mini"><span>Montant de l'avoir</span><strong><?= e(money($cn['total'])) ?></strong></div>
      <div class="hoc-mini"><span>Imputé sur factures</span><span><?= e(money($cn['applied_amount'])) ?></span></div>
      <div class="hoc-mini"><span>Remboursé</span><span><?= e(money($cn['refunded_amount'])) ?></span></div>
      <div class="hoc-mini fw-bold"><span>Disponible (crédit client)</span><span><?= e(money($remaining)) ?></span></div>
      <div class="hoc-mini"><span>Motif</span><span><?= e($cn['reason']) ?></span></div>
      <div class="hoc-mini"><span>Émis par</span><span><?= e($cn['user_name'] ?? '') ?> · <?= e(format_date($cn['created_at'], true)) ?></span></div>
      <?php if ((int) $cn['restock']): ?><div class="hoc-mini"><span>Stock</span><span>Articles réintégrés</span></div><?php endif; ?>
    </div>
    <?php if ($allocations || $refunds): ?><ul class="list-group list-group-flush small">
      <?php foreach ($allocations as $a): ?><li class="list-group-item d-flex justify-content-between"><span>Imputé sur <a href="<?= e(url('invoices/' . $a['invoice_id'])) ?>"><?= e($a['number']) ?></a> · <?= e(format_date($a['created_at'])) ?></span><span><?= e(money($a['amount'])) ?></span></li><?php endforeach; ?>
      <?php foreach ($refunds as $r): ?><li class="list-group-item d-flex justify-content-between"><span>Remboursement <a href="<?= e(url('payments/' . $r['id'])) ?>"><?= e($r['number']) ?></a> · <?= e($r['method']) ?></span><span><?= e(money($r['amount'])) ?></span></li><?php endforeach; ?>
    </ul><?php endif; ?></div>
    <?php if ($remaining > 0.004 && $cn['status'] !== 'cancelled'): ?>
      <div class="card mb-3"><div class="card-header fw-semibold">Utiliser le solde</div><div class="card-body">
        <?php if ($openInvoices && can('invoices.credit_note')): ?>
          <form method="post" action="<?= e(url('credit-notes/' . $cn['id'] . '/apply')) ?>" class="input-group input-group-sm mb-3"><?= csrf_field() ?>
            <select class="form-select" name="invoice_id" aria-label="Facture"><?php foreach ($openInvoices as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['number']) ?> — reste <?= e(money($i['balance_due'])) ?></option><?php endforeach; ?></select>
            <button class="btn btn-dark">Imputer</button></form>
        <?php endif; ?>
        <?php if (can('payments.refund')): ?>
          <form method="post" action="<?= e(url('credit-notes/' . $cn['id'] . '/refund')) ?>" class="input-group input-group-sm" data-confirm="Rembourser <?= e(money($remaining)) ?> au client ?"><?= csrf_field() ?>
            <select class="form-select" name="method_id" aria-label="Mode de remboursement"><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
            <button class="btn btn-outline-danger">Rembourser <?= e(money($remaining)) ?></button></form>
        <?php endif; ?>
      </div></div>
    <?php endif; ?>
    <?php \App\Core\View::partial('doc_side', ['type' => 'credit_note', 'id' => (int) $cn['id'], 'send' => $send]); ?>
  </div>
</div>
<?php \App\Core\View::partial('doc_send', ['type' => 'credit_note', 'id' => (int) $cn['id'], 'send' => $send]); ?>
