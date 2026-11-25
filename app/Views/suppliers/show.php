<?php /** Fiche fournisseur. Variables : $s, $stats, $orders, $invoices, $payments, $returns, $prices, $materials */
$tab = preg_replace('/[^a-z]/', '', (string) \App\Core\Request::get('tab', 'overview')) ?: 'overview';
$empty = static fn(string $t) => '<div class="hoc-empty"><i class="bi bi-inbox"></i>' . e($t) . '</div>';
$tabs = ['overview' => 'Aperçu', 'prices' => 'Prix des matières', 'invoices' => 'Factures', 'orders' => 'Commandes', 'payments' => 'Paiements', 'returns' => 'Retours'];
$wa = $s['whatsapp'] ?: $s['phone']; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($s['company'] ?: $s['name']) ?> <?= $s['is_active'] ? '' : '<span class="badge text-bg-secondary fs-6">Inactif</span>' ?></h1>
    <div class="text-body-secondary"><code><?= e($s['code']) ?></code><?= $s['company'] ? ' · Contact : ' . e($s['name']) : '' ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($wa): ?><a class="btn btn-outline-success" target="_blank" rel="noopener" href="<?= e(whatsapp_link($wa)) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a><?php endif; ?>
    <?php if ($s['email']): ?><a class="btn btn-outline-secondary" href="mailto:<?= e($s['email']) ?>"><i class="bi bi-envelope"></i> E-mail</a><?php endif; ?>
    <?php if (can('suppliers.edit')): ?><a class="btn btn-dark" href="<?= e(url('suppliers/' . $s['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-4">
  <?php foreach ([['Total des achats', money($stats['purchased']), (int) $stats['invoices'] . ' facture(s)'], ['Total payé', money($stats['paid']), 'Paiements validés'],
      ['Dette actuelle', money($stats['debt']), ((int) $stats['overdue']) . ' facture(s) échue(s)'], ['Matières fournies', (string) count($materials), 'Fournisseur principal']] as $i => [$l, $val, $sub]): ?>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label"><?= e($l) ?></div>
      <div class="hoc-kpi-value <?= $i === 2 && $stats['debt'] > 0.004 ? 'text-danger' : '' ?>"><?= e($val) ?></div><div class="hoc-kpi-sub"><?= e($sub) ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" role="tablist">
  <?php foreach ($tabs as $k => $l): ?><li class="nav-item"><button class="nav-link<?= $tab === $k ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#t-<?= $k ?>" type="button"><?= e($l) ?></button></li><?php endforeach; ?>
</ul>
<div class="tab-content">
  <div class="tab-pane fade<?= $tab === 'overview' ? ' show active' : '' ?>" id="t-overview"><div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Coordonnées</div><div class="card-body">
      <?php foreach ([['Téléphone', $s['phone']], ['WhatsApp', $s['whatsapp']], ['E-mail', $s['email']], ['Adresse', trim(($s['address'] ?? '') . ' ' . ($s['city'] ?? ''))],
          ['ICE', $s['ice']], ['IF', $s['if_number']], ['RC', $s['rc']], ['Délai de paiement', $s['payment_terms_days'] . ' jour(s)']] as [$l, $val]): ?>
        <div class="hoc-mini"><span class="text-body-secondary"><?= e($l) ?></span><span><?= e($val ?: '—') ?></span></div>
      <?php endforeach; ?></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Matières dont c'est le fournisseur principal</div>
      <?php if (!$materials): ?><?= $empty('Aucune matière associée.') ?><?php else: ?><ul class="list-group list-group-flush">
        <?php foreach ($materials as $m): ?><li class="list-group-item d-flex justify-content-between"><a class="text-reset" href="<?= e(url('materials/' . $m['id'])) ?>"><?= e($m['name']) ?></a><span class="small text-body-secondary"><?= e(number_fr($m['stock_quantity'], 2)) ?> <?= e($m['symbol']) ?> · <?= e(money($m['unit_cost'])) ?>/<?= e($m['symbol']) ?></span></li><?php endforeach; ?>
      </ul><?php endif; ?></div></div>
    <?php if ($s['notes']): ?><div class="col-12"><div class="card"><div class="card-header fw-semibold">Notes</div><div class="card-body" style="white-space:pre-line"><?= e($s['notes']) ?></div></div></div><?php endif; ?>
    <?php if (can('suppliers.delete')): ?><div class="col-12 text-end"><form method="post" action="<?= e(url('suppliers/' . $s['id'] . '/delete')) ?>" data-confirm="Supprimer ce fournisseur ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer</button></form></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'prices' ? ' show active' : '' ?>" id="t-prices"><div class="card">
    <?php if (can('suppliers.edit')): ?>
    <form method="post" action="<?= e(url('suppliers/' . $s['id'] . '/prices')) ?>" class="card-body border-bottom row g-2 align-items-end"><?= csrf_field() ?>
      <div class="col-md-4"><label class="form-label small">Matière</label><div class="hoc-lookup" data-lookup="<?= e(url('materials/lookup')) ?>">
        <input class="form-control" type="search" placeholder="Rechercher une matière…" autocomplete="off" data-lookup-input><input type="hidden" name="material_id" data-lookup-value required></div></div>
      <div class="col-6 col-md-2"><label class="form-label small" for="sp_price">Prix unitaire HT</label><input class="form-control" id="sp_price" name="price" inputmode="decimal" required></div>
      <div class="col-6 col-md-2"><label class="form-label small" for="sp_min">Qté minimum</label><input class="form-control" id="sp_min" name="min_quantity" inputmode="decimal" value="1"></div>
      <div class="col-6 col-md-2"><label class="form-label small" for="sp_from">Depuis le</label><input class="form-control" type="date" id="sp_from" name="valid_from" value="<?= date('Y-m-d') ?>"></div>
      <div class="col-6 col-md-2"><button class="btn btn-dark w-100">Ajouter</button></div>
    </form><?php endif; ?>
    <?php if (!$prices): ?><?= $empty('Aucun prix enregistré. Les prix permettent de comparer les fournisseurs sur la fiche de chaque matière.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Matière</th><th class="text-end">Prix fournisseur</th><th class="text-end">Coût moyen actuel</th><th class="text-end">Qté min.</th><th>Depuis</th><th></th></tr></thead><tbody>
      <?php foreach ($prices as $pr): ?><tr><td><a class="text-reset" href="<?= e(url('materials/' . $pr['material_id'])) ?>"><code><?= e($pr['code']) ?></code> <?= e($pr['name']) ?></a></td>
        <td class="text-end fw-semibold"><?= e(money($pr['price'])) ?> / <?= e($pr['symbol']) ?></td><td class="text-end text-body-secondary"><?= e(money($pr['unit_cost'])) ?></td>
        <td class="text-end"><?= e(number_fr($pr['min_quantity'], 2)) ?></td><td><?= e(format_date($pr['valid_from'])) ?></td>
        <td class="text-end"><?php if (can('suppliers.edit')): ?><form method="post" action="<?= e(url('suppliers/' . $s['id'] . '/prices/' . $pr['id'] . '/delete')) ?>" data-confirm="Supprimer ce prix ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div></div>

  <div class="tab-pane fade<?= $tab === 'invoices' ? ' show active' : '' ?>" id="t-invoices"><div class="card">
    <?php if (!$invoices): ?><?= $empty('Aucune facture fournisseur.') ?><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Réf.</th><th>N° fournisseur</th><th>Date</th><th>Échéance</th><th>Statut</th><th class="text-end">Total</th><th class="text-end">Payé</th></tr></thead><tbody>
      <?php foreach ($invoices as $i): ?><tr><td><code><?= e($i['number']) ?></code></td><td><?= e($i['supplier_invoice_number'] ?? '—') ?></td><td><?= e(format_date($i['invoice_date'])) ?></td><td><?= e(format_date($i['due_date'])) ?></td>
        <td><?= e(['unpaid' => 'Impayée', 'partial' => 'Partielle', 'paid' => 'Payée', 'cancelled' => 'Annulée'][$i['status']] ?? $i['status']) ?></td><td class="text-end"><?= e(money($i['total'])) ?></td><td class="text-end"><?= e(money($i['paid_amount'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div></div>
  <div class="tab-pane fade<?= $tab === 'orders' ? ' show active' : '' ?>" id="t-orders"><div class="card">
    <?php if (!$orders): ?><?= $empty('Aucune commande fournisseur.') ?><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numéro</th><th>Date</th><th>Livraison prévue</th><th>Statut</th><th class="text-end">Total</th></tr></thead><tbody>
      <?php foreach ($orders as $o): ?><tr><td><code><?= e($o['number']) ?></code></td><td><?= e(format_date($o['order_date'])) ?></td><td><?= e(format_date($o['expected_date'])) ?></td><td><?= e($o['status']) ?></td><td class="text-end"><?= e(money($o['total'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div></div>
  <div class="tab-pane fade<?= $tab === 'payments' ? ' show active' : '' ?>" id="t-payments"><div class="card">
    <?php if (!$payments): ?><?= $empty('Aucun paiement fournisseur.') ?><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numéro</th><th>Date</th><th>Mode</th><th>Référence</th><th class="text-end">Montant</th></tr></thead><tbody>
      <?php foreach ($payments as $pay): ?><tr><td><code><?= e($pay['number']) ?></code></td><td><?= e(format_date($pay['payment_date'])) ?></td><td><?= e($pay['method']) ?></td><td><?= e($pay['reference'] ?? '—') ?></td><td class="text-end"><?= e(money($pay['amount'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div></div>
  <div class="tab-pane fade<?= $tab === 'returns' ? ' show active' : '' ?>" id="t-returns"><div class="card">
    <?php if (!$returns): ?><?= $empty('Aucun retour fournisseur.') ?><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numéro</th><th>Date</th><th>Motif</th><th class="text-end">Total</th></tr></thead><tbody>
      <?php foreach ($returns as $r): ?><tr><td><code><?= e($r['number']) ?></code></td><td><?= e(format_date($r['return_date'])) ?></td><td><?= e($r['reason']) ?></td><td class="text-end"><?= e(money($r['total'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div></div>
</div>
