<?php /** Liste des encaissements. Variables : $rows, $f, $p, $totals, $methods, $customer */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Encaissements<?= $customer ? ' — ' . e(\App\Services\CustomerService::displayName($customer)) : '' ?></h1>
    <p class="text-body-secondary mb-0">Reçu <?= e(money($totals['received'])) ?><?= (float) $totals['refunded'] > 0 ? ' · remboursé ' . e(money($totals['refunded'])) : '' ?><?= (float) $totals['advances'] > 0 ? ' · avances non imputées ' . e(money($totals['advances'])) : '' ?></p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('payments/export', current_query())) ?>"><i class="bi bi-download"></i> Exporter</a>
    <?php if (can('payments.create')): ?><a class="btn btn-accent" href="<?= e(url('payments/create', $customer ? ['customer' => $customer['id']] : [])) ?>"><i class="bi bi-cash-coin"></i> Encaisser un règlement</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('payments')) ?>"><?= route_field('payments') ?>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Recherche</label><input class="form-control" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="N° de reçu, référence, client…"></div>
    <div><label class="form-label small mb-1" for="from">Du</label><input class="form-control" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
    <div><label class="form-label small mb-1" for="to">Au</label><input class="form-control" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
    <div><label class="form-label small mb-1" for="method">Mode</label><select class="form-select" id="method" name="method"><option value="0">Tous</option>
      <?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $f['method'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="advances" value="1" id="adv" <?= $f['advances'] ? 'checked' : '' ?>><label class="form-check-label small" for="adv">Avances non imputées</label></div>
    <?php if ($f['customer']): ?><input type="hidden" name="customer" value="<?= (int) $f['customer'] ?>"><?php endif; ?>
    <div><button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button></div>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Reçu</th><th>Date</th><th>Client</th><th>Mode</th><th>Imputation</th><th class="text-end">Montant</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="hoc-empty"><i class="bi bi-wallet2"></i>Aucun encaissement.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $refund = $r['direction'] === 'refund'; $off = $r['status'] === 'cancelled'; ?>
      <tr class="<?= $off ? 'text-body-secondary' : '' ?>">
        <td><a class="fw-semibold text-decoration-none" href="<?= e(url('payments/' . $r['id'])) ?>"><code><?= e($r['number']) ?></code></a><?= $off ? ' <span class="badge text-bg-dark">annulé</span>' : '' ?><?= $refund ? ' <span class="badge text-bg-danger">remboursement</span>' : '' ?></td>
        <td class="small text-nowrap"><?= e(format_date($r['payment_date'], true)) ?><br><span class="text-body-secondary"><?= e($r['user_name'] ?? '') ?></span></td>
        <td><?= $r['is_walk_in'] ? '<span class="text-body-secondary">Comptoir</span>' : '<a class="text-reset" href="' . e(url('customers/' . $r['customer_id'])) . '">' . e($r['customer_name']) . '</a>' ?></td>
        <td class="small"><?= e($r['method']) ?><?= $r['reference'] ? '<br><span class="text-body-secondary">' . e($r['reference']) . '</span>' : '' ?></td>
        <td class="small"><?= $r['invoices'] ? 'Factures ' . e($r['invoices']) : '' ?><?= $r['sale_number'] ? 'Ticket ' . e($r['sale_number']) : '' ?><?= (float) $r['unallocated_amount'] > 0.004 ? '<br><span class="text-warning-emphasis">Avance ' . e(money($r['unallocated_amount'])) . '</span>' : '' ?></td>
        <td class="text-end fw-semibold <?= $off ? 'text-decoration-line-through' : '' ?> <?= $refund ? 'text-danger' : '' ?>"><?= $refund ? '−' : '' ?><?= e(money($r['amount'])) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('documents/payment/' . $r['id'] . '/pdf')) ?>" target="_blank" title="Reçu PDF"><i class="bi bi-file-earmark-pdf"></i></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
