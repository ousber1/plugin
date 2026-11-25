<?php /** Liste des devis. Variables : $rows, $f, $p, $stats */
$rate = (int) $stats['decided'] > 0 ? round((int) $stats['won'] / (int) $stats['decided'] * 100) : null; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Devis</h1><p class="text-body-secondary mb-0"><?= (int) $stats['n'] ?> devis · <?= e(money($stats['total'])) ?> TTC · en cours <?= e(money($stats['open_total'])) ?><?= $rate !== null ? ' · taux de transformation ' . $rate . ' %' : '' ?></p></div>
  <?php if (can('quotations.create')): ?><a class="btn btn-accent" href="<?= e(url('quotations/create')) ?>"><i class="bi bi-plus-lg"></i> Nouveau devis</a><?php endif; ?>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('quotations')) ?>"><?= route_field('quotations') ?>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Recherche</label><input class="form-control" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="N°, objet, client, téléphone…"></div>
    <div><label class="form-label small mb-1" for="status">Statut</label><select class="form-select" id="status" name="status"><option value="">Tous</option>
      <?php foreach (status_map('quotation') as $k => [$l]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small mb-1" for="from">Du</label><input class="form-control" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
    <div><label class="form-label small mb-1" for="to">Au</label><input class="form-control" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
    <?php if ($f['customer']): ?><input type="hidden" name="customer" value="<?= (int) $f['customer'] ?>"><?php endif; ?>
    <div><button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button></div>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Objet</th><th>Validité</th><th>Statut</th><th class="text-end">Total TTC</th><th class="text-end"></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="8" class="hoc-empty"><i class="bi bi-file-earmark-text"></i>Aucun devis trouvé.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $soon = in_array($r['status'], ['sent', 'pending'], true) && $r['valid_until'] && $r['valid_until'] <= date('Y-m-d', strtotime('+3 days')); ?>
      <tr>
        <td><a class="fw-semibold text-decoration-none" href="<?= e(url('quotations/' . $r['id'])) ?>"><code><?= e($r['number']) ?></code></a></td>
        <td class="small text-nowrap"><?= e(format_date($r['issue_date'])) ?><br><span class="text-body-secondary"><?= e($r['user_name'] ?? '') ?></span></td>
        <td><a class="text-reset" href="<?= e(url('customers/' . $r['customer_id'])) ?>"><?= e($r['customer_name']) ?></a><?= $r['phone'] ? '<br><small class="text-body-secondary">' . e($r['phone']) . '</small>' : '' ?></td>
        <td class="small"><?= e($r['title'] ?? '') ?></td>
        <td class="small text-nowrap <?= $soon ? 'text-warning-emphasis fw-semibold' : '' ?>"><?= e(format_date($r['valid_until'])) ?></td>
        <td><?= status_badge('quotation', $r['status']) ?></td>
        <td class="text-end fw-semibold"><?= e(money($r['total'])) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('documents/quotation/' . $r['id'] . '/pdf')) ?>" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('quotations/' . $r['id'])) ?>" title="Ouvrir"><i class="bi bi-eye"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
