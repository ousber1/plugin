<?php
/** Fiche d'un devis. Variables : $q, $customer, $customerName, $invoices, $depositsBilled, $orders, $sales, $history, $send, $cost, $statuses, $views */
$st = $q['status'];
$editable = !in_array($st, ['accepted', 'converted'], true);
$convertible = !in_array($st, ['refused', 'expired', 'converted'], true);
$hasDeposits = (bool) array_filter($invoices, static fn($i) => $i['invoice_type'] === 'deposit' && !in_array($i['status'], ['draft', 'cancelled'], true));
$hasBalance = (bool) array_filter($invoices, static fn($i) => $i['invoice_type'] === 'balance' && $i['status'] !== 'cancelled');
$net = (float) $q['total'] - (float) $q['tax_total'];
?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Devis <?= e($q['number']) ?> <?= status_badge('quotation', $st) ?></h1>
    <p class="text-body-secondary mb-0"><a href="<?= e(url('customers/' . $q['customer_id'])) ?>"><?= e($customerName) ?></a><?= $q['title'] ? ' · ' . e($q['title']) : '' ?> · <?= e(money($q['total'])) ?> TTC</p></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($editable && can('quotations.edit')): ?><a class="btn btn-outline-dark" href="<?= e(url('quotations/' . $q['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= e(url('documents/quotation/' . $q['id'] . '/pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('documents/quotation/' . $q['id'] . '/pdf', ['download' => 1])) ?>"><i class="bi bi-download"></i></a>
    <?php if (can('quotations.send')): ?>
      <div class="dropdown"><button class="btn btn-accent dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-send"></i> Envoyer</button>
        <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mailModal"><i class="bi bi-envelope"></i> Par e-mail (PDF joint)</button></li>
          <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#waModal"><i class="bi bi-whatsapp"></i> Par WhatsApp</button></li></ul></div>
    <?php endif; ?>
    <?php if (can('quotations.create')): ?><form method="post" action="<?= e(url('quotations/' . $q['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" title="Dupliquer"><i class="bi bi-copy"></i> Dupliquer</button></form><?php endif; ?>
    <?php if (can('quotations.delete') && in_array($st, ['draft', 'refused', 'expired'], true) && !$invoices): ?>
      <form method="post" action="<?= e(url('quotations/' . $q['id'] . '/delete')) ?>" data-confirm="Supprimer ce devis ?"><?= csrf_field() ?><button class="btn btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($st === 'expired'): ?><div class="alert alert-warning"><i class="bi bi-hourglass-bottom"></i> Ce devis a expiré le <?= e(format_date($q['valid_until'])) ?>. Modifiez la date de validité pour le réactiver, ou dupliquez-le.</div><?php endif; ?>
<?php if ($st === 'refused' && $q['refused_reason']): ?><div class="alert alert-danger"><i class="bi bi-x-circle"></i> Refusé : <?= e($q['refused_reason']) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-8"><iframe class="hoc-doc-preview" src="<?= e(url('documents/quotation/' . $q['id'] . '/preview')) ?>" title="Aperçu du devis"></iframe></div>
  <div class="col-xl-4">
    <?php if ($convertible || ($hasDeposits && !$hasBalance)): ?>
    <div class="card mb-3 border-dark"><div class="card-header fw-semibold"><i class="bi bi-arrow-right-circle"></i> Transformer le devis</div><div class="card-body d-grid gap-2">
      <?php if (can('invoices.create')): ?>
        <?php if ($hasDeposits): ?>
          <?php if (!$hasBalance): ?><form method="post" action="<?= e(url('quotations/' . $q['id'] . '/convert/balance')) ?>" class="d-grid"><?= csrf_field() ?><button class="btn btn-dark"><i class="bi bi-file-earmark-check"></i> Facture de solde</button><div class="form-text">Acomptes déjà facturés : <?= e(money($depositsBilled)) ?>, déduits automatiquement.</div></form><?php endif; ?>
        <?php elseif ($convertible): ?>
          <form method="post" action="<?= e(url('quotations/' . $q['id'] . '/convert/invoice')) ?>" class="d-grid"><?= csrf_field() ?><button class="btn btn-dark"><i class="bi bi-file-earmark-ruled"></i> Facturer la totalité</button></form>
        <?php endif; ?>
        <?php if ($convertible || ($hasDeposits && !$hasBalance)): ?>
        <form method="post" action="<?= e(url('quotations/' . $q['id'] . '/convert/deposit')) ?>" class="input-group"><?= csrf_field() ?>
          <input class="form-control" type="number" name="percent" min="1" max="99" step="1" value="<?= (int) setting('default_deposit_percent', 50) ?>" aria-label="Pourcentage d'acompte"><span class="input-group-text">%</span>
          <button class="btn btn-outline-dark">Facture d'acompte</button></form>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($convertible && can('quotations.convert')): ?><form method="post" action="<?= e(url('quotations/' . $q['id'] . '/convert/order')) ?>" class="d-grid"><?= csrf_field() ?><button class="btn btn-outline-dark"><i class="bi bi-clipboard2-check"></i> Bon de commande</button></form><?php endif; ?>
      <?php if ($convertible && can('pos.access') && !$hasDeposits): ?><form method="post" action="<?= e(url('quotations/' . $q['id'] . '/convert/pos')) ?>" class="d-grid"><?= csrf_field() ?><button class="btn btn-outline-dark"><i class="bi bi-cart3"></i> Encaisser au comptoir</button></form><?php endif; ?>
    </div></div>
    <?php endif; ?>

    <?php if (can('quotations.edit') && $st !== 'converted'): ?>
    <div class="card mb-3"><div class="card-header fw-semibold">Suivi commercial</div><div class="card-body">
      <form method="post" action="<?= e(url('quotations/' . $q['id'] . '/status')) ?>" class="row g-2"><?= csrf_field() ?>
        <div class="col-12"><label class="form-label small" for="qStatus">Statut</label><select class="form-select" id="qStatus" name="status">
          <?php foreach ($statuses as $k => [$l]): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label small" for="qNote">Commentaire <span class="text-body-secondary">(obligatoire en cas de refus)</span></label><input class="form-control" id="qNote" name="note" maxlength="255" placeholder="Ex. relancé par téléphone, prix trop élevé…"></div>
        <div class="col-12 d-grid"><button class="btn btn-outline-dark">Mettre à jour</button></div>
      </form></div></div>
    <?php endif; ?>

    <div class="card mb-3"><div class="card-header fw-semibold">Informations</div><div class="card-body small">
      <div class="hoc-mini"><span>Date</span><span><?= e(format_date($q['issue_date'])) ?></span></div>
      <div class="hoc-mini"><span>Valable jusqu'au</span><span><?= e(format_date($q['valid_until'])) ?></span></div>
      <?php if ($q['delivery_date']): ?><div class="hoc-mini"><span>Livraison prévue</span><span><?= e(format_date($q['delivery_date'])) ?></span></div><?php endif; ?>
      <?php if ($q['sent_at']): ?><div class="hoc-mini"><span>Envoyé le</span><span><?= e(format_date($q['sent_at'], true)) ?></span></div><?php endif; ?>
      <?php if ($q['accepted_at']): ?><div class="hoc-mini"><span>Accepté le</span><span><?= e(format_date($q['accepted_at'], true)) ?></span></div><?php endif; ?>
      <div class="hoc-mini"><span>Consultations en ligne</span><span><?= (int) $views ?></span></div>
      <?php if (can('products.view_cost') && $net > 0): ?><div class="hoc-mini text-body-secondary"><span>Coût estimé · marge</span><span><?= e(money($cost)) ?> · <?= e(number_fr(($net - $cost) / $net * 100, 1)) ?> %</span></div><?php endif; ?>
      <?php if ($q['internal_notes']): ?><div class="mt-2 p-2 bg-body-tertiary rounded"><i class="bi bi-lock"></i> <?= nl2br(e($q['internal_notes'])) ?></div><?php endif; ?>
    </div></div>

    <?php if ($invoices || $orders || $sales): ?>
    <div class="card mb-3"><div class="card-header fw-semibold">Documents liés</div><ul class="list-group list-group-flush small">
      <?php foreach ($invoices as $i): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('invoices/' . $i['id'])) ?>"><?= e(\App\Services\InvoiceService::TYPES[$i['invoice_type']]) ?> <?= e($i['number'] ?? '(brouillon)') ?></a><span><?= status_badge('invoice', $i['status']) ?> <?= e(money($i['total'])) ?></span></li><?php endforeach; ?>
      <?php foreach ($orders as $o): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('sales-orders/' . $o['id'])) ?>">Bon de commande <?= e($o['number']) ?></a><span><?= status_badge('sales_order', $o['status']) ?></span></li><?php endforeach; ?>
      <?php foreach ($sales as $s): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= e(url($s['status'] === 'suspended' ? 'pos' : 'sales/' . $s['id'])) ?>">Vente <?= e($s['number'] ?? '') ?></a><span><?= status_badge('sale', $s['status']) ?> <?= e(money($s['total'])) ?></span></li><?php endforeach; ?>
    </ul></div>
    <?php endif; ?>

    <?php \App\Core\View::partial('doc_side', ['type' => 'quotation', 'id' => (int) $q['id'], 'send' => $send]); ?>

    <div class="card"><div class="card-header fw-semibold">Historique</div><div class="card-body"><ul class="hoc-timeline">
      <?php foreach ($history as $h): ?><li><strong><?= e($h['to_status'] && $h['from_status'] !== $h['to_status'] ? (status_map('quotation')[$h['to_status']][0] ?? $h['to_status']) : (['create' => 'Création', 'update' => 'Modification', 'status' => 'Statut', 'send' => 'Envoi', 'duplicate' => 'Copie', 'convert' => 'Conversion', 'pos' => 'Point de vente', 'expire' => 'Expiration'][$h['action']] ?? $h['action'])) ?></strong> — <?= e($h['note'] ?? '') ?>
        <span class="d-block text-body-secondary"><?= e(format_date($h['created_at'], true)) ?><?= $h['full_name'] ? ' · ' . e($h['full_name']) : '' ?></span></li><?php endforeach; ?>
    </ul></div></div>
  </div>
</div>
<?php if (can('quotations.send')) { \App\Core\View::partial('doc_send', ['type' => 'quotation', 'id' => (int) $q['id'], 'send' => $send]); } ?>
