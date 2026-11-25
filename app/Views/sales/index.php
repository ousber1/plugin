<?php /** Historique des ventes. Variables : $sales, $f, $p, $totals, $users, $methods */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Ventes</h1><p class="text-body-secondary mb-0"><?= (int) $totals['n'] ?> ticket(s) · <?= e(money($totals['total'])) ?> TTC net<?= (float) $totals['credit'] > 0 ? ' · <span class="text-danger">' . e(money($totals['credit'])) . ' à crédit</span>' : '' ?></p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('sales/export', current_query())) ?>"><i class="bi bi-download"></i> Exporter</a>
    <?php if (can('pos.access')): ?><a class="btn btn-accent" href="<?= e(url('pos')) ?>"><i class="bi bi-cart3"></i> Point de vente</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('sales')) ?>"><?= route_field('sales') ?>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Recherche</label><input class="form-control" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="N° de ticket, client, téléphone…"></div>
    <div><label class="form-label small mb-1" for="from">Du</label><input class="form-control" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
    <div><label class="form-label small mb-1" for="to">Au</label><input class="form-control" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
    <div><label class="form-label small mb-1" for="status">Statut</label><select class="form-select" id="status" name="status"><option value="">Tous</option>
      <?php foreach (status_map('sale') as $k => [$l]): if (in_array($k, ['draft', 'suspended'], true)) { continue; } ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small mb-1" for="method">Paiement</label><select class="form-select" id="method" name="method"><option value="0">Tous</option>
      <?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $f['method'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small mb-1" for="user">Vendeur</label><select class="form-select" id="user" name="user"><option value="0">Tous</option>
      <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $f['user'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="credit" value="1" id="credit" <?= $f['credit'] ? 'checked' : '' ?>><label class="form-check-label small" for="credit">À crédit</label></div>
    <div><button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button></div>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Ticket</th><th>Date</th><th>Client</th><th>Paiement</th><th>Statut</th><th class="text-end">Total TTC</th><th class="text-end">Reste dû</th><th class="text-end"></th></tr></thead>
    <tbody>
    <?php if (!$sales): ?><tr><td colspan="8" class="hoc-empty"><i class="bi bi-receipt"></i>Aucune vente trouvée.</td></tr><?php endif; ?>
    <?php foreach ($sales as $s): $due = max(0, (float) $s['total'] - (float) $s['paid_amount']); ?>
      <tr>
        <td><a class="fw-semibold text-decoration-none" href="<?= e(url('sales/' . $s['id'])) ?>"><code><?= e($s['number']) ?></code></a></td>
        <td class="small text-nowrap"><?= e(format_date($s['sale_date'], true)) ?><br><span class="text-body-secondary"><?= e($s['user_name'] ?? '') ?></span></td>
        <td><?= $s['is_walk_in'] ? '<span class="text-body-secondary">Comptoir</span>' : e($s['customer_name']) ?></td>
        <td class="small"><?= e($s['methods'] ?? ($due > 0 ? 'Crédit' : '—')) ?></td>
        <td><?= status_badge('sale', $s['status']) ?></td>
        <td class="text-end fw-semibold <?= $s['status'] === 'cancelled' ? 'text-decoration-line-through text-body-secondary' : '' ?>"><?= e(money($s['total'])) ?></td>
        <td class="text-end <?= $due > 0.004 && $s['status'] !== 'cancelled' ? 'text-danger fw-semibold' : 'text-body-secondary' ?>"><?= $due > 0.004 && $s['status'] !== 'cancelled' ? e(money($due)) : '—' ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('sales/' . $s['id'] . '/receipt')) ?>" target="hoc_receipt" title="Ticket"><i class="bi bi-printer"></i></a>
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('sales/' . $s['id'])) ?>" title="Détail"><i class="bi bi-eye"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
