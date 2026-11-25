<?php /** Liste des bons de commande. Variables : $rows, $q, $status, $p */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Bons de commande</h1><p class="text-body-secondary mb-0">Commandes confirmées par les clients, créées depuis un devis.</p></div>
  <?php if (can('quotations.view')): ?><a class="btn btn-outline-dark" href="<?= e(url('quotations', ['status' => 'accepted'])) ?>"><i class="bi bi-file-earmark-check"></i> Devis acceptés</a><?php endif; ?>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('sales-orders')) ?>"><?= route_field('sales-orders') ?>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Recherche</label><input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="N°, client…"></div>
    <div><label class="form-label small mb-1" for="status">Statut</label><select class="form-select" id="status" name="status"><option value="">Tous</option>
      <?php foreach (status_map('sales_order') as $k => [$l]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button></div>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Devis</th><th>Échéance</th><th>Statut</th><th class="text-end">Total TTC</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="8" class="hoc-empty"><i class="bi bi-clipboard2-check"></i>Aucun bon de commande. Transformez un devis accepté depuis sa fiche.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr><td><a class="fw-semibold text-decoration-none" href="<?= e(url('sales-orders/' . $r['id'])) ?>"><code><?= e($r['number']) ?></code></a></td>
        <td class="small"><?= e(format_date($r['order_date'])) ?></td><td><?= e($r['customer_name']) ?></td>
        <td class="small"><?= $r['quotation_id'] ? '<a href="' . e(url('quotations/' . $r['quotation_id'])) . '">' . e($r['quotation_number']) . '</a>' : '—' ?></td>
        <td class="small <?= $r['due_date'] && $r['due_date'] < date('Y-m-d') && !in_array($r['status'], ['delivered', 'completed', 'cancelled'], true) ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($r['due_date'])) ?></td>
        <td><?= status_badge('sales_order', $r['status']) ?><?= (int) $r['invoiced'] ? ' <span class="badge text-bg-light border">facturé</span>' : '' ?></td>
        <td class="text-end fw-semibold"><?= e(money($r['total'])) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('documents/sales_order/' . $r['id'] . '/pdf')) ?>" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
