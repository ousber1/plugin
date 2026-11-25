<?php /** Liste des factures. Variables : $rows, $f, $p, $stats */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Factures</h1><p class="text-body-secondary mb-0"><?= (int) $stats['n'] ?> facture(s) · facturé <?= e(money($stats['total'])) ?> · reste à encaisser <strong class="<?= (float) $stats['due'] > 0 ? 'text-danger' : '' ?>"><?= e(money($stats['due'])) ?></strong><?= (float) $stats['overdue'] > 0 ? ' dont ' . e(money($stats['overdue'])) . ' en retard' : '' ?></p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('invoices/export', current_query())) ?>"><i class="bi bi-download"></i> Exporter</a>
    <?php if (can('invoices.create')): ?><a class="btn btn-accent" href="<?= e(url('invoices/create')) ?>"><i class="bi bi-plus-lg"></i> Nouvelle facture</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('invoices')) ?>"><?= route_field('invoices') ?>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Recherche</label><input class="form-control" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="N°, client, téléphone, ICE…"></div>
    <div><label class="form-label small mb-1" for="status">Statut</label><select class="form-select" id="status" name="status"><option value="">Tous</option>
      <?php foreach (status_map('invoice') as $k => [$l]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small mb-1" for="type">Type</label><select class="form-select" id="type" name="type"><option value="">Tous</option>
      <?php foreach (\App\Services\InvoiceService::TYPES as $k => $l): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small mb-1" for="from">Du</label><input class="form-control" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
    <div><label class="form-label small mb-1" for="to">Au</label><input class="form-control" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="due" value="1" id="due" <?= $f['due'] ? 'checked' : '' ?>><label class="form-check-label small" for="due">Non soldées</label></div>
    <?php if ($f['customer']): ?><input type="hidden" name="customer" value="<?= (int) $f['customer'] ?>"><?php endif; ?>
    <div><button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button></div>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Échéance</th><th>Statut</th><th class="text-end">Total TTC</th><th class="text-end">Reste dû</th><th class="text-end"></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="8" class="hoc-empty"><i class="bi bi-file-earmark-ruled"></i>Aucune facture trouvée.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a class="fw-semibold text-decoration-none" href="<?= e(url('invoices/' . $r['id'])) ?>"><code><?= e($r['number'] ?? 'Brouillon') ?></code></a>
          <?= $r['invoice_type'] !== 'standard' ? '<br><span class="badge text-bg-light border">' . e($r['invoice_type'] === 'deposit' ? 'Acompte' : 'Solde') . '</span>' : '' ?></td>
        <td class="small text-nowrap"><?= e(format_date($r['issue_date'])) ?></td>
        <td><a class="text-reset" href="<?= e(url('customers/' . $r['customer_id'])) ?>"><?= e($r['customer_name']) ?></a></td>
        <td class="small text-nowrap <?= $r['status'] === 'overdue' ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($r['due_date'])) ?><?= $r['status'] === 'overdue' ? '<br>' . (int) $r['late_days'] . ' j de retard' : '' ?></td>
        <td><?= status_badge('invoice', $r['status']) ?></td>
        <td class="text-end fw-semibold <?= $r['status'] === 'cancelled' ? 'text-decoration-line-through text-body-secondary' : '' ?>"><?= e(money($r['total'])) ?></td>
        <td class="text-end <?= (float) $r['balance_due'] > 0.004 && !in_array($r['status'], ['draft', 'cancelled'], true) ? 'text-danger fw-semibold' : 'text-body-secondary' ?>"><?= (float) $r['balance_due'] > 0.004 && !in_array($r['status'], ['draft', 'cancelled'], true) ? e(money($r['balance_due'])) : '—' ?></td>
        <td class="text-end text-nowrap">
          <?php if ($r['status'] !== 'draft'): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('documents/invoice/' . $r['id'] . '/pdf')) ?>" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('invoices/' . $r['id'])) ?>" title="Ouvrir"><i class="bi bi-eye"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
