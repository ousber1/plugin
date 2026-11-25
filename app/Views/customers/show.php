<?php
/** Fiche client. Variables : $c, $stats, $quotations, $invoices, $sales, $payments, $orders, $creditNotes, $files, $loyalty, $prices */
$types = \App\Controllers\CustomerController::TYPES;
$name = \App\Services\CustomerService::displayName($c);
$tab = preg_replace('/[^a-z]/', '', (string) \App\Core\Request::get('tab', 'overview')) ?: 'overview';
$wa = $c['whatsapp'] ?: $c['phone'];
$limit = (float) $c['credit_limit'];
$tabs = [
    'overview' => ['Aperçu', null], 'quotations' => ['Devis', count($quotations)], 'invoices' => ['Factures', count($invoices)],
    'sales' => ['Ventes', count($sales)], 'payments' => ['Paiements', count($payments)], 'orders' => ['Commandes', count($orders)],
    'credits' => ['Avoirs', count($creditNotes)], 'files' => ['Fichiers', count($files)], 'loyalty' => ['Fidélité', null], 'prices' => ['Prix négociés', count($prices)],
];
$empty = static fn(string $t) => '<div class="hoc-empty"><i class="bi bi-inbox"></i>' . e($t) . '</div>';
?>
<div class="hoc-page-head">
  <div class="d-flex align-items-center gap-3">
    <span class="hoc-avatar" style="width:56px;height:56px;font-size:1.2rem"><?= e(mb_strtoupper(mb_substr($name, 0, 2))) ?></span>
    <div>
      <h1 class="h3 fw-bold mb-1"><?= e($name) ?> <?= $c['is_active'] ? '' : '<span class="badge text-bg-secondary fs-6">Inactif</span>' ?></h1>
      <div class="text-body-secondary"><code><?= e($c['code']) ?></code> · <?= e($types[$c['type']] ?? $c['type']) ?><?= $c['group_name'] ? ' · ' . e($c['group_name']) : '' ?><?= $c['company_name'] ? ' · Contact : ' . e($c['full_name']) : '' ?></div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($wa): ?><a class="btn btn-outline-success" target="_blank" rel="noopener" href="<?= e(whatsapp_link($wa, 'Bonjour ' . $c['full_name'] . ', ')) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a><?php endif; ?>
    <?php if ($c['phone']): ?><a class="btn btn-outline-secondary" href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['phone'])) ?>"><i class="bi bi-telephone"></i> Appeler</a><?php endif; ?>
    <?php if ($c['email']): ?><a class="btn btn-outline-secondary" href="mailto:<?= e($c['email']) ?>"><i class="bi bi-envelope"></i> E-mail</a><?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= e(url('customers/' . $c['id'] . '/statement')) ?>" target="_blank"><i class="bi bi-file-earmark-spreadsheet"></i> Relevé de compte</a>
    <?php if (can('customers.edit')): ?><a class="btn btn-dark" href="<?= e(url('customers/' . $c['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['Total des achats', money($stats['purchases']), $stats['sales'] . ' ticket(s) · ' . $stats['invoices'] . ' facture(s)'],
      ['Montant réglé', money($stats['paid']), $stats['last_purchase'] ? 'Dernier achat : ' . format_date($stats['last_purchase']) : 'Aucun achat'],
      ['Solde du compte', money($stats['balance']), $stats['open_invoices'] . ' facture(s) ouverte(s)' . ($stats['overdue'] ? ' · ' . $stats['overdue'] . ' en retard' : '')],
      ['Crédit autorisé', $limit > 0 ? money($limit) : 'Aucun', $limit > 0 ? 'Disponible : ' . money(max(0, $limit - max(0, $stats['balance']))) : 'Vente à crédit non autorisée'],
  ] as $i => [$label, $value, $sub]): ?>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body">
      <div class="hoc-kpi-label"><?= e($label) ?></div>
      <div class="hoc-kpi-value text-truncate <?= $i === 2 && $stats['balance'] > 0.004 ? 'text-danger' : '' ?>"><?= e($value) ?></div>
      <div class="hoc-kpi-sub"><?= e($sub) ?></div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" role="tablist" style="white-space:nowrap">
  <?php foreach ($tabs as $key => [$label, $count]): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link<?= $tab === $key ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#t-<?= $key ?>" type="button" role="tab"><?= e($label) ?><?= $count ? ' <span class="badge text-bg-light border">' . (int) $count . '</span>' : '' ?></button>
    </li>
  <?php endforeach; ?>
</ul>

<div class="tab-content">
  <div class="tab-pane fade<?= $tab === 'overview' ? ' show active' : '' ?>" id="t-overview" role="tabpanel">
    <div class="row g-3">
      <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Coordonnées</div><div class="card-body">
        <?php foreach ([
            ['Téléphone', $c['phone']], ['WhatsApp', $c['whatsapp']], ['E-mail', $c['email']],
            ['Adresse', trim(($c['address'] ?? '') . ' ' . ($c['city'] ?? ''))],
        ] as [$l, $val]): ?>
          <div class="hoc-mini"><span class="text-body-secondary"><?= e($l) ?></span><span class="text-end"><?= e($val ?: '—') ?></span></div>
        <?php endforeach; ?>
      </div></div></div>
      <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Informations</div><div class="card-body">
        <?php foreach ([
            ['ICE', $c['ice']], ['IF', $c['if_number']], ['RC', $c['rc']], ['CIN', $c['cin']],
            ['Liste de prix', $c['price_list_name']], ['Points de fidélité', ((int) $c['loyalty_points']) . ' point(s)'],
            ['Client depuis', format_date($c['created_at']) . ($c['creator'] ? ' (par ' . $c['creator'] . ')' : '')],
        ] as [$l, $val]): ?>
          <div class="hoc-mini"><span class="text-body-secondary"><?= e($l) ?></span><span class="text-end"><?= e($val ?: '—') ?></span></div>
        <?php endforeach; ?>
      </div></div></div>
      <div class="col-12"><div class="card"><div class="card-header fw-semibold">Notes</div><div class="card-body" style="white-space:pre-line"><?= $c['notes'] ? e($c['notes']) : '<span class="text-body-secondary">Aucune note.</span>' ?></div></div></div>
      <?php if (can('customers.delete') && !$c['is_walk_in']): ?>
        <div class="col-12 text-end">
          <form method="post" action="<?= e(url('customers/' . $c['id'] . '/delete')) ?>" data-confirm="Supprimer ce client ? Son historique sera conservé."><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer le client</button></form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="tab-pane fade<?= $tab === 'quotations' ? ' show active' : '' ?>" id="t-quotations" role="tabpanel"><div class="card">
    <?php if (!$quotations): ?><?= $empty('Aucun devis pour ce client.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numéro</th><th>Date</th><th>Validité</th><th>Statut</th><th class="text-end">Total TTC</th></tr></thead><tbody>
      <?php foreach ($quotations as $q): ?><tr><td><code><?= e($q['number']) ?></code></td><td><?= e(format_date($q['issue_date'])) ?></td><td><?= e(format_date($q['valid_until'])) ?></td><td><?= status_badge('quotation', $q['status']) ?></td><td class="text-end"><?= e(money($q['total'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'invoices' ? ' show active' : '' ?>" id="t-invoices" role="tabpanel"><div class="card">
    <?php if (!$invoices): ?><?= $empty('Aucune facture pour ce client.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numéro</th><th>Date</th><th>Échéance</th><th>Statut</th><th class="text-end">Total</th><th class="text-end">Payé</th><th class="text-end">Reste</th></tr></thead><tbody>
      <?php foreach ($invoices as $i): ?><tr><td><code><?= e($i['number']) ?></code><?= $i['invoice_type'] !== 'standard' ? ' <small class="text-body-secondary">' . ($i['invoice_type'] === 'deposit' ? 'acompte' : 'solde') . '</small>' : '' ?></td><td><?= e(format_date($i['issue_date'])) ?></td><td><?= e(format_date($i['due_date'])) ?></td><td><?= status_badge('invoice', $i['status']) ?></td><td class="text-end"><?= e(money($i['total'])) ?></td><td class="text-end"><?= e(money($i['paid_amount'])) ?></td><td class="text-end fw-semibold"><?= e(money($i['balance_due'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'sales' ? ' show active' : '' ?>" id="t-sales" role="tabpanel"><div class="card">
    <?php if (!$sales): ?><?= $empty('Aucune vente au comptoir pour ce client.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ticket</th><th>Date</th><th>Statut</th><th class="text-end">Total</th><th class="text-end">Payé</th></tr></thead><tbody>
      <?php foreach ($sales as $s): ?><tr><td><code><?= e($s['number'] ?? '—') ?></code><?= $s['invoice_id'] ? ' <small class="text-body-secondary">facturé</small>' : '' ?></td><td><?= e(format_date($s['sale_date'], true)) ?></td><td><?= status_badge('sale', $s['status']) ?></td><td class="text-end"><?= e(money($s['total'])) ?></td><td class="text-end"><?= e(money($s['paid_amount'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'payments' ? ' show active' : '' ?>" id="t-payments" role="tabpanel"><div class="card">
    <?php if (!$payments): ?><?= $empty('Aucun paiement enregistré.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Reçu</th><th>Date</th><th>Mode</th><th>Référence</th><th>Statut</th><th class="text-end">Montant</th></tr></thead><tbody>
      <?php foreach ($payments as $pay): $ref = $pay['direction'] === 'refund'; ?><tr><td><code><?= e($pay['number']) ?></code><?= $ref ? ' <small class="text-danger">remboursement</small>' : '' ?></td><td><?= e(format_date($pay['payment_date'], true)) ?></td><td><?= e($pay['method']) ?></td><td><?= e($pay['reference'] ?? '—') ?></td><td><?= status_badge('payment', $pay['status']) ?></td><td class="text-end <?= $ref ? 'text-danger' : '' ?>"><?= $ref ? '−' : '' ?><?= e(money($pay['amount'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'orders' ? ' show active' : '' ?>" id="t-orders" role="tabpanel"><div class="card">
    <?php if (!$orders): ?><?= $empty('Aucune commande de production.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numéro</th><th>Travail</th><th>Statut</th><th>Échéance</th></tr></thead><tbody>
      <?php foreach ($orders as $o): ?><tr><td><code><?= e($o['number']) ?></code></td><td><?= e($o['title']) ?></td><td><?= status_badge('production', $o['status']) ?></td><td><?= e(format_date($o['deadline'], true)) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'credits' ? ' show active' : '' ?>" id="t-credits" role="tabpanel"><div class="card">
    <?php if (!$creditNotes): ?><?= $empty('Aucun avoir.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numéro</th><th>Date</th><th>Motif</th><th>Statut</th><th class="text-end">Montant</th></tr></thead><tbody>
      <?php foreach ($creditNotes as $cn): ?><tr><td><code><?= e($cn['number']) ?></code></td><td><?= e(format_date($cn['issue_date'])) ?></td><td><?= e($cn['reason']) ?></td><td><?= status_badge('credit_note', $cn['status']) ?></td><td class="text-end"><?= e(money($cn['total'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'files' ? ' show active' : '' ?>" id="t-files" role="tabpanel"><div class="card">
    <?php if (can('customers.edit')): ?>
      <form method="post" action="<?= e(url('customers/' . $c['id'] . '/files')) ?>" enctype="multipart/form-data" class="card-body border-bottom row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-5"><label class="form-label small" for="cfile">Fichier (PDF, images, SVG, AI, EPS, ZIP)</label><input class="form-control" type="file" id="cfile" name="file" required></div>
        <div class="col-md-5"><label class="form-label small" for="cdesc">Description</label><input class="form-control" id="cdesc" name="description" maxlength="255" placeholder="Ex. Logo vectoriel, charte graphique…"></div>
        <div class="col-md-2"><button class="btn btn-dark w-100"><i class="bi bi-upload"></i> Ajouter</button></div>
      </form>
    <?php endif; ?>
    <?php if (!$files): ?><?= $empty('Aucun fichier.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fichier</th><th>Description</th><th>Taille</th><th>Ajouté</th><th class="text-end"></th></tr></thead><tbody>
      <?php foreach ($files as $file): $dl = url('customers/' . $c['id'] . '/files/' . $file['id']); ?><tr>
        <td class="fw-semibold"><i class="bi bi-file-earmark"></i> <?= e($file['original_name']) ?></td><td class="small"><?= e($file['description'] ?? '') ?></td>
        <td class="small"><?= e(number_fr($file['file_size'] / 1024 / 1024, 2)) ?> Mo</td><td class="small"><?= e(format_date($file['created_at'], true)) ?><br><span class="text-body-secondary"><?= e($file['uploader'] ?? '') ?></span></td>
        <td class="text-end text-nowrap">
          <?php if (in_array($file['mime_type'], ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true)): ?><a class="btn btn-sm btn-outline-dark" target="_blank" href="<?= e($dl . (str_contains($dl, '?') ? '&' : '?') . 'inline=1') ?>" title="Voir"><i class="bi bi-eye"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-outline-dark" href="<?= e($dl) ?>" title="Télécharger"><i class="bi bi-download"></i></a>
          <?php if (can('customers.edit')): ?><form class="d-inline" method="post" action="<?= e(url('customers/' . $c['id'] . '/files/' . $file['id'] . '/delete')) ?>" data-confirm="Supprimer ce fichier ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form><?php endif; ?>
        </td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'loyalty' ? ' show active' : '' ?>" id="t-loyalty" role="tabpanel"><div class="card">
    <div class="card-body border-bottom d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div><div class="hoc-kpi-label">Solde de points</div><div class="hoc-kpi-value"><?= (int) $c['loyalty_points'] ?></div>
        <small class="text-body-secondary"><?= setting('loyalty_enabled') ? 'Programme de fidélité actif.' : 'Programme de fidélité désactivé (Paramètres › Point de vente).' ?></small></div>
      <?php if (can('customers.edit')): ?>
        <form method="post" action="<?= e(url('customers/' . $c['id'] . '/loyalty')) ?>" class="d-flex flex-wrap gap-2 align-items-end"><?= csrf_field() ?>
          <div><label class="form-label small" for="lpts">Points (+/−)</label><input class="form-control" type="number" id="lpts" name="points" required style="width:110px"></div>
          <div><label class="form-label small" for="lrsn">Motif</label><input class="form-control" id="lrsn" name="reason" required maxlength="190"></div>
          <button class="btn btn-outline-dark">Ajuster</button></form>
      <?php endif; ?>
    </div>
    <?php if (!$loyalty): ?><?= $empty('Aucun mouvement de points.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Date</th><th>Motif</th><th>Par</th><th class="text-end">Points</th></tr></thead><tbody>
      <?php foreach ($loyalty as $l): ?><tr><td><?= e(format_date($l['created_at'], true)) ?></td><td><?= e($l['reason']) ?></td><td><?= e($l['user_name'] ?? '—') ?></td><td class="text-end fw-semibold <?= $l['points'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $l['points'] > 0 ? '+' : '' ?><?= (int) $l['points'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>

  <div class="tab-pane fade<?= $tab === 'prices' ? ' show active' : '' ?>" id="t-prices" role="tabpanel"><div class="card">
    <?php if (can('customers.edit')): ?>
      <form method="post" action="<?= e(url('customers/' . $c['id'] . '/prices')) ?>" class="card-body border-bottom row g-2 align-items-end"><?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label small">Produit ou service</label>
          <div class="hoc-lookup" data-lookup="<?= e(url('products/lookup')) ?>">
            <input class="form-control" type="search" placeholder="Rechercher par nom ou code…" autocomplete="off" data-lookup-input>
            <input type="hidden" name="product_id" data-lookup-value required>
          </div></div>
        <div class="col-md-2"><label class="form-label small" for="cprice">Prix HT</label><input class="form-control" id="cprice" name="price" inputmode="decimal" required></div>
        <div class="col-md-2"><label class="form-label small" for="cuntil">Valable jusqu'au</label><input class="form-control" type="date" id="cuntil" name="valid_until"></div>
        <div class="col-md-2"><button class="btn btn-dark w-100">Enregistrer</button></div>
      </form>
    <?php endif; ?>
    <?php if (!$prices): ?><?= $empty('Aucun prix négocié : le client paie les prix de sa liste de prix.') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Produit</th><th class="text-end">Prix catalogue</th><th class="text-end">Prix client</th><th>Validité</th><th></th></tr></thead><tbody>
      <?php foreach ($prices as $pr): $expired = $pr['valid_until'] && $pr['valid_until'] < date('Y-m-d'); ?><tr>
        <td><code><?= e($pr['code']) ?></code> <?= e($pr['name']) ?></td><td class="text-end"><?= e(money($pr['selling_price'])) ?> / <?= e($pr['symbol']) ?></td>
        <td class="text-end fw-semibold"><?= e(money($pr['price'])) ?></td><td><?= $pr['valid_until'] ? e(format_date($pr['valid_until'])) . ($expired ? ' <span class="badge text-bg-dark">Expiré</span>' : '') : 'Sans limite' ?></td>
        <td class="text-end"><?php if (can('customers.edit')): ?><form method="post" action="<?= e(url('customers/' . $c['id'] . '/prices/' . $pr['id'] . '/delete')) ?>" data-confirm="Supprimer ce prix négocié ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>
</div>
