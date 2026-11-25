<?php /** Détail d'une vente. Variables : $sale, $items, $payments, $vat, $returns, $movements, $canCancel, $canReturn */
$due = max(0, round((float) $sale['total'] - (float) $sale['paid_amount'], 2));
$showCost = can('products.view_cost');
$cost = (float) $sale['cost_total']; $net = (float) $sale['total'] - (float) $sale['tax_total']; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Ticket <?= e($sale['number']) ?> <?= status_badge('sale', $sale['status']) ?></h1>
    <p class="text-body-secondary mb-0"><?= e(format_date($sale['sale_date'], true)) ?> · <?= e($sale['user_name'] ?? '') ?><?= $sale['session_number'] ? ' · caisse ' . e($sale['session_number']) : '' ?></p></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('sales/' . $sale['id'] . '/receipt')) ?>" target="hoc_receipt"><i class="bi bi-printer"></i> Ticket</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('sales/' . $sale['id'] . '/print')) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Bon A4</a>
    <?php if ($canReturn): ?><a class="btn btn-outline-dark" href="<?= e(url('sales/' . $sale['id'] . '/return')) ?>"><i class="bi bi-arrow-counterclockwise"></i> Retour / remboursement</a><?php endif; ?>
    <?php if ($canCancel): ?><button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancelBox"><i class="bi bi-x-circle"></i> Annuler la vente</button><?php endif; ?>
  </div>
</div>
<?php if ($canCancel): ?>
  <div class="collapse mb-3" id="cancelBox"><form method="post" action="<?= e(url('sales/' . $sale['id'] . '/cancel')) ?>" class="card border-danger" data-confirm="Annuler définitivement cette vente ? Les paiements seront contre-passés et le stock réintégré."><div class="card-body d-flex flex-wrap gap-2 align-items-end"><?= csrf_field() ?>
    <div class="flex-grow-1"><label class="form-label small" for="reason">Motif de l'annulation *</label><input class="form-control" id="reason" name="reason" required minlength="3" maxlength="255" placeholder="Ex. erreur de saisie, client parti sans payer…"></div>
    <button class="btn btn-danger">Confirmer l'annulation</button></div></form></div>
<?php endif; ?>
<?php if ($sale['status'] === 'cancelled'): ?><div class="alert alert-dark"><i class="bi bi-x-octagon"></i> Vente annulée le <?= e(format_date($sale['cancelled_at'], true)) ?> par <?= e($sale['cancelled_name'] ?? '') ?> : <?= e($sale['cancel_reason']) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-header fw-semibold">Articles</div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Désignation</th><th class="text-end">Qté</th><th class="text-end">PU HT</th><th class="text-end">Rem.</th><th class="text-end">TVA</th><th class="text-end">Total TTC</th><?php if ($showCost): ?><th class="text-end">Coût</th><?php endif; ?></tr></thead>
        <tbody><?php foreach ($items as $it): ?>
          <tr><td><?php if ($it['product_id']): ?><a class="text-reset" href="<?= e(url('products/' . $it['product_id'])) ?>"><?= e($it['description']) ?></a><?php elseif ($it['pack_id']): ?><a class="text-reset" href="<?= e(url('packs/' . $it['pack_id'])) ?>"><?= e($it['description']) ?></a><?php else: ?><?= e($it['description']) ?> <span class="badge text-bg-light border">libre</span><?php endif; ?>
            <?= $it['notes'] ? '<br><small class="text-body-secondary">' . e($it['notes']) . '</small>' : '' ?>
            <?= $it['price_overridden_by'] ? '<br><small class="text-warning-emphasis"><i class="bi bi-pencil"></i> prix catalogue ' . e(money($it['original_price'])) . ' modifié par ' . e($it['override_name'] ?? '') . '</small>' : '' ?>
            <?= (float) $it['returned_quantity'] > 0 ? '<br><small class="text-danger">Retourné : ' . e(number_fr($it['returned_quantity'], 3)) . '</small>' : '' ?></td>
            <td class="text-end text-nowrap"><?= e(number_fr($it['quantity'], 3)) ?> <?= e($it['symbol'] ?? '') ?></td><td class="text-end"><?= e(number_fr($it['unit_price'])) ?></td>
            <td class="text-end"><?= (float) $it['discount_percent'] > 0 ? e(number_fr($it['discount_percent'], 1)) . ' %' : '' ?></td><td class="text-end"><?= e(number_fr($it['tax_rate'], 0)) ?> %</td>
            <td class="text-end fw-semibold"><?= e(money($it['line_total'])) ?></td><?php if ($showCost): ?><td class="text-end text-body-secondary small"><?= e(money((float) $it['unit_cost'] * (float) $it['quantity'])) ?></td><?php endif; ?></tr>
        <?php endforeach; ?></tbody></table></div></div>
    <?php if ($returns): ?><div class="card mb-3"><div class="card-header fw-semibold">Retours</div><ul class="list-group list-group-flush">
      <?php foreach ($returns as $r): ?><li class="list-group-item d-flex justify-content-between"><span><code><?= e($r['number']) ?></code> · <?= e(format_date($r['return_date'], true)) ?> · <?= e($r['method'] ?? '') ?> · <?= e($r['reason']) ?> <span class="text-body-secondary">(<?= e($r['full_name'] ?? '') ?>)</span></span><strong class="text-danger">−<?= e(money($r['total'])) ?></strong></li><?php endforeach; ?>
    </ul></div><?php endif; ?>
    <?php if ($movements): ?><div class="card"><div class="card-header fw-semibold">Mouvements de stock liés</div><?php \App\Core\View::partial('stock_movements', ['movements' => $movements, 'showItem' => true]); ?></div><?php endif; ?>
  </div>
  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header fw-semibold">Client</div><div class="card-body">
      <?php if ($sale['is_walk_in']): ?><span class="text-body-secondary">Client comptoir</span>
      <?php else: ?><a class="fw-semibold" href="<?= e(url('customers/' . $sale['customer_id'])) ?>"><?= e(\App\Controllers\SaleController::customerName($sale)) ?></a><div class="small text-body-secondary"><?= e($sale['customer_code']) ?><?= $sale['phone'] ? ' · ' . e($sale['phone']) : '' ?></div><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header fw-semibold">Montants</div><div class="card-body">
      <div class="hoc-mini"><span>Sous-total HT</span><span><?= e(money($sale['subtotal'])) ?></span></div>
      <?php if ((float) $sale['discount_total'] > 0): ?><div class="hoc-mini"><span>Remise globale HT</span><span>−<?= e(money($sale['discount_total'])) ?></span></div><?php endif; ?>
      <?php foreach ($vat as $v): ?><div class="hoc-mini"><span class="text-body-secondary">TVA <?= e(number_fr($v['rate'], 0)) ?> % sur <?= e(money($v['base'])) ?></span><span><?= e(money($v['tax'])) ?></span></div><?php endforeach; ?>
      <div class="hoc-mini fw-bold fs-5"><span>Total TTC</span><span><?= e(money($sale['total'])) ?></span></div>
      <div class="hoc-mini"><span>Réglé</span><span><?= e(money($sale['paid_amount'])) ?></span></div>
      <?php if ((float) $sale['change_amount'] > 0): ?><div class="hoc-mini"><span>Monnaie rendue</span><span><?= e(money($sale['change_amount'])) ?></span></div><?php endif; ?>
      <?php if ($due > 0.004 && $sale['status'] !== 'cancelled'): ?><div class="hoc-mini text-danger fw-semibold"><span>Reste dû</span><span><?= e(money($due)) ?></span></div><?php endif; ?>
      <?php if ((float) $sale['refunded_amount'] > 0): ?><div class="hoc-mini text-danger"><span>Remboursé</span><span>−<?= e(money($sale['refunded_amount'])) ?></span></div><?php endif; ?>
      <?php if ($showCost && $net > 0): ?><div class="hoc-mini text-body-secondary small"><span>Coût · marge brute</span><span><?= e(money($cost)) ?> · <?= e(number_fr(($net - $cost) / $net * 100, 1)) ?> %</span></div><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header fw-semibold">Paiements</div>
      <?php if (!$payments): ?><div class="card-body text-body-secondary small">Aucun paiement (vente à crédit).</div><?php else: ?><ul class="list-group list-group-flush">
        <?php foreach ($payments as $p): $ref = $p['direction'] === 'refund'; ?><li class="list-group-item d-flex justify-content-between small<?= $p['status'] === 'cancelled' ? ' text-decoration-line-through text-body-secondary' : '' ?>">
          <span><code><?= e($p['number']) ?></code> <?= e($p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?><?= $ref ? ' <span class="badge text-bg-danger">remboursement</span>' : '' ?></span>
          <strong><?= $ref ? '−' : '' ?><?= e(money($p['amount'])) ?></strong></li><?php endforeach; ?>
      </ul><?php endif; ?></div>
    <?php if ($sale['notes']): ?><div class="card"><div class="card-header fw-semibold">Note</div><div class="card-body small" style="white-space:pre-line"><?= e($sale['notes']) ?></div></div><?php endif; ?>
  </div>
</div>
