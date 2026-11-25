<?php /** Liste des avoirs. Variables : $rows, $q, $p, $total */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Avoirs</h1><p class="text-body-secondary mb-0"><?= (int) $p->total ?> avoir(s) · <?= e(money($total)) ?> TTC</p></div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('credit-notes')) ?>"><?= route_field('credit-notes') ?>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Recherche</label><input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="N° d'avoir, n° de facture, client…"></div>
    <div><button class="btn btn-outline-dark"><i class="bi bi-search"></i> Rechercher</button></div>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Facture</th><th>Motif</th><th>Statut</th><th class="text-end">Montant TTC</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="8" class="hoc-empty"><i class="bi bi-file-earmark-minus"></i>Aucun avoir. Un avoir s'émet depuis la fiche d'une facture validée.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr><td><a class="fw-semibold text-decoration-none" href="<?= e(url('credit-notes/' . $r['id'])) ?>"><code><?= e($r['number']) ?></code></a></td>
        <td class="small"><?= e(format_date($r['issue_date'])) ?></td><td><?= e($r['customer_name']) ?></td>
        <td><?= $r['invoice_id'] ? '<a href="' . e(url('invoices/' . $r['invoice_id'])) . '">' . e($r['invoice_number']) . '</a>' : '—' ?></td>
        <td class="small"><?= e($r['reason']) ?></td><td><?= status_badge('credit_note', $r['status']) ?></td>
        <td class="text-end fw-semibold">−<?= e(money($r['total'])) ?></td>
        <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('documents/credit_note/' . $r['id'] . '/pdf')) ?>" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
