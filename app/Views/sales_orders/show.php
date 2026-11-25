<?php /** Fiche bon de commande. Variables : $so, $customerName, $quotation, $invoices, $next, $send */
$billed = (bool) array_filter($invoices, static fn($i) => $i['status'] !== 'cancelled' && $i['invoice_type'] !== 'deposit'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Bon de commande <?= e($so['number']) ?> <?= status_badge('sales_order', $so['status']) ?></h1>
    <p class="text-body-secondary mb-0"><a href="<?= e(url('customers/' . $so['customer_id'])) ?>"><?= e($customerName) ?></a> · <?= e(money($so['total'])) ?> TTC<?= $quotation ? ' · devis <a href="' . e(url('quotations/' . $quotation['id'])) . '">' . e($quotation['number']) . '</a>' : '' ?></p></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('documents/sales_order/' . $so['id'] . '/pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <?php if (can('quotations.send')): ?><div class="dropdown"><button class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-send"></i> Envoyer</button>
      <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mailModal"><i class="bi bi-envelope"></i> Par e-mail</button></li>
        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#waModal"><i class="bi bi-whatsapp"></i> Par WhatsApp</button></li></ul></div><?php endif; ?>
    <?php if (!$billed && $so['status'] !== 'cancelled' && can('invoices.create')): ?><form method="post" action="<?= e(url('sales-orders/' . $so['id'] . '/invoice')) ?>"><?= csrf_field() ?><button class="btn btn-accent"><i class="bi bi-file-earmark-ruled"></i> Facturer</button></form><?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-8"><iframe class="hoc-doc-preview" src="<?= e(url('documents/sales_order/' . $so['id'] . '/preview')) ?>" title="Aperçu du bon de commande"></iframe></div>
  <div class="col-xl-4">
    <?php if ($next && can('quotations.convert')): ?><div class="card mb-3"><div class="card-header fw-semibold">Avancement</div><div class="card-body d-flex flex-wrap gap-2">
      <?php foreach ($next as $s): ?><form method="post" action="<?= e(url('sales-orders/' . $so['id'] . '/status')) ?>" <?= $s === 'cancelled' ? 'data-confirm="Annuler ce bon de commande ?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="status" value="<?= $s ?>">
        <button class="btn btn-sm <?= $s === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-dark' ?>"><?= e(status_map('sales_order')[$s][0]) ?></button></form><?php endforeach; ?>
    </div></div><?php endif; ?>
    <div class="card mb-3"><div class="card-header fw-semibold">Informations</div><div class="card-body small">
      <div class="hoc-mini"><span>Date</span><span><?= e(format_date($so['order_date'])) ?></span></div>
      <div class="hoc-mini"><span>Échéance de réalisation</span><span><?= e(format_date($so['due_date'])) ?></span></div>
      <div class="hoc-mini"><span>Total TTC</span><strong><?= e(money($so['total'])) ?></strong></div>
    </div></div>
    <?php if ($invoices): ?><div class="card mb-3"><div class="card-header fw-semibold">Factures</div><ul class="list-group list-group-flush small">
      <?php foreach ($invoices as $i): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('invoices/' . $i['id'])) ?>"><?= e(\App\Services\InvoiceService::TYPES[$i['invoice_type']]) ?> <?= e($i['number'] ?? '(brouillon)') ?></a><span><?= status_badge('invoice', $i['status']) ?> <?= e(money($i['total'])) ?></span></li><?php endforeach; ?>
    </ul></div><?php endif; ?>
    <?php \App\Core\View::partial('doc_side', ['type' => 'sales_order', 'id' => (int) $so['id'], 'send' => $send]); ?>
  </div>
</div>
<?php if (can('quotations.send')) { \App\Core\View::partial('doc_send', ['type' => 'sales_order', 'id' => (int) $so['id'], 'send' => $send]); } ?>
