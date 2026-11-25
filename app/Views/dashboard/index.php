<?php
/**
 * Tableau de bord. Toutes les valeurs proviennent de DashboardService (base de données).
 * Variables : $financial, $counters, $lowStock, $urgent, $machines, $activity
 *   (+ si $financial : $kpis, $monthly, $daily, $byCategory, $byMachine, $topProducts, $topServices, $topCustomers, $payments)
 */
$c = $counters;
$delta = static function (?float $pct, string $vs): string {
    if ($pct === null) {
        return '<span class="hoc-delta text-body-secondary">— ' . e($vs) . '</span>';
    }
    $up = $pct >= 0;
    return '<span class="hoc-delta ' . ($up ? 'text-success' : 'text-danger') . '"><i class="bi bi-arrow-' . ($up ? 'up' : 'down') . '-right"></i> '
        . ($up ? '+' : '') . e(number_fr($pct, 1)) . ' % <span class="text-body-secondary fw-normal">' . e($vs) . '</span></span>';
};
$empty = static fn(string $icon, string $text): string => '<div class="hoc-empty"><i class="bi ' . e($icon) . '"></i>' . e($text) . '</div>';
$statusLabels = [
    'order' => 'Commande', 'design' => 'Design', 'design_validation' => 'Validation design', 'production' => 'Production',
    'finishing' => 'Finition', 'quality_control' => 'Contrôle qualité', 'ready' => 'Prêt', 'delivery' => 'Livraison',
];
$machineStatus = ['operational' => ['Opérationnelle', 'success'], 'maintenance' => ['En maintenance', 'warning'], 'down' => ['En panne', 'danger'], 'retired' => ['Retirée', 'secondary']];
?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1">Tableau de bord</h1>
    <p class="text-body-secondary mb-0">Bonjour <?= e(explode(' ', (string) auth_user()['full_name'])[0]) ?> — activité au <?= e(format_date(date('Y-m-d'))) ?>.</p>
  </div>
</div>

<?php if ($financial): $k = $kpis; ?>
<div class="row g-3 mb-3">
  <?php foreach ([
      ['revenue_today', 'CA aujourd\'hui', 'vs hier', 'bi-sun'],
      ['revenue_week', 'CA cette semaine', 'vs sem. préc.', 'bi-calendar-week'],
      ['revenue_month', 'CA ce mois', 'vs mois préc.', 'bi-calendar3'],
      ['revenue_year', 'CA ' . date('Y'), 'vs ' . (date('Y') - 1), 'bi-graph-up-arrow'],
  ] as [$key, $label, $vs, $icon]): ?>
    <div class="col-6 col-xl-3">
      <div class="card hoc-kpi hoc-stat h-100"><div class="card-body">
        <div class="hoc-stat-icon"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="hoc-kpi-label"><?= e($label) ?></div>
        <div class="hoc-kpi-value text-truncate"><?= e(money($k[$key]['value'])) ?></div>
        <?= $delta($k[$key]['change'], $vs) ?>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body">
    <div class="hoc-kpi-label">Dépenses du mois</div>
    <div class="hoc-kpi-value text-truncate"><?= e(money($k['expenses_month'])) ?></div>
    <div class="hoc-kpi-sub">Année : <?= e(money($k['expenses_year'])) ?></div>
  </div></div></div>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body">
    <div class="hoc-kpi-label">Bénéfice estimé du mois</div>
    <div class="hoc-kpi-value text-truncate <?= $k['profit_month'] < 0 ? 'text-danger' : '' ?>"><?= e(money($k['profit_month'])) ?></div>
    <div class="hoc-kpi-sub">Marge brute : <?= $k['margin_month'] === null ? '—' : e(number_fr($k['margin_month'], 1)) . ' %' ?> · Année : <?= e(money($k['profit_year'])) ?></div>
  </div></div></div>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body">
    <div class="hoc-kpi-label">Encours clients</div>
    <div class="hoc-kpi-value text-truncate"><?= e(money($c['invoices_outstanding'])) ?></div>
    <div class="hoc-kpi-sub"><?= (int) $c['invoices_overdue'] ?> facture(s) en retard</div>
  </div></div></div>
  <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body">
    <div class="hoc-kpi-label">Devis en attente</div>
    <div class="hoc-kpi-value text-truncate"><?= e(money($c['quotes_pending_amount'])) ?></div>
    <div class="hoc-kpi-sub"><?= (int) $c['quotes_pending'] ?> devis à relancer ou convertir</div>
  </div></div></div>
</div>
<p class="small text-body-secondary mt-n2 mb-4"><i class="bi bi-info-circle"></i> Chiffre d'affaires HT : tickets non facturés + factures validées − avoirs. Bénéfice estimé = CA HT − coût de revient des ventes − dépenses HT.</p>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php if ($financial): ?>
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-header fw-semibold">Chiffre d'affaires, dépenses et bénéfice — 12 derniers mois</div>
      <div class="card-body">
        <?php if (array_sum($monthly['revenue']) == 0 && array_sum($monthly['expenses']) == 0): ?>
          <?= $empty('bi-bar-chart', 'Aucune vente ni dépense enregistrée sur les 12 derniers mois.') ?>
        <?php else: ?>
          <div class="hoc-chart"><canvas id="chartMonthly" role="img" aria-label="Chiffre d'affaires, dépenses et bénéfice par mois"></canvas></div>
          <button class="btn btn-link btn-sm px-0 mt-2 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#monthlyTable" aria-expanded="false">
            <i class="bi bi-table"></i> Afficher le tableau des données
          </button>
          <div class="collapse" id="monthlyTable">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Mois</th><th class="text-end">CA HT</th><th class="text-end">Dépenses HT</th><th class="text-end">Bénéfice estimé</th></tr></thead>
                <tbody>
                <?php foreach ($monthly['labels'] as $i => $label): ?>
                  <tr><td><?= e($label) ?></td><td class="text-end"><?= e(money($monthly['revenue'][$i])) ?></td>
                    <td class="text-end"><?= e(money($monthly['expenses'][$i])) ?></td>
                    <td class="text-end <?= $monthly['profit'][$i] < 0 ? 'text-danger' : '' ?>"><?= e(money($monthly['profit'][$i])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="<?= $financial ? 'col-xl-4' : 'col-12' ?>">
    <div class="card h-100">
      <div class="card-header fw-semibold">Activité commerciale</div>
      <div class="card-body">
        <div class="hoc-mini"><span><i class="bi bi-receipt me-1"></i> Ventes du mois</span><strong><?= (int) $c['sales_month'] ?></strong></div>
        <div class="hoc-mini"><span><i class="bi bi-receipt-cutoff me-1"></i> Total ventes</span><strong><?= (int) $c['sales_total'] ?></strong></div>
        <div class="hoc-mini"><span><i class="bi bi-file-earmark-text me-1"></i> Factures émises</span><strong><?= (int) $c['invoices_total'] ?></strong></div>
        <div class="hoc-mini ps-3"><span><span class="hoc-status-dot bg-success"></span>Payées</span><strong><?= (int) $c['invoices_paid'] ?></strong></div>
        <div class="hoc-mini ps-3"><span><span class="hoc-status-dot bg-warning"></span>Partiellement payées</span><strong><?= (int) $c['invoices_partial'] ?></strong></div>
        <div class="hoc-mini ps-3"><span><span class="hoc-status-dot bg-danger"></span>Impayées</span><strong><?= (int) $c['invoices_unpaid'] ?></strong></div>
        <div class="hoc-mini"><span><i class="bi bi-file-earmark-ruled me-1"></i> Devis</span><strong><?= (int) $c['quotes_total'] ?></strong></div>
        <div class="hoc-mini ps-3"><span>En attente</span><strong><?= (int) $c['quotes_pending'] ?></strong></div>
        <div class="hoc-mini ps-3"><span>Acceptés</span><strong><?= (int) $c['quotes_accepted'] ?></strong></div>
        <div class="hoc-mini"><span><i class="bi bi-people me-1"></i> Clients</span><strong><?= (int) $c['customers_total'] ?></strong></div>
        <div class="hoc-mini ps-3"><span>Nouveaux ce mois</span><strong><?= (int) $c['customers_new'] ?></strong></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php if ($financial): ?>
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-header fw-semibold">Ventes journalières — 30 derniers jours (CA HT)</div>
      <div class="card-body">
        <?php if (array_sum($daily['revenue']) == 0): ?>
          <?= $empty('bi-graph-up', 'Aucune vente sur les 30 derniers jours.') ?>
        <?php else: ?>
          <div class="hoc-chart"><canvas id="chartDaily" role="img" aria-label="Chiffre d'affaires par jour"></canvas></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="<?= $financial ? 'col-xl-4' : 'col-12' ?>">
    <div class="card h-100">
      <div class="card-header fw-semibold">Production</div>
      <div class="card-body">
        <div class="row text-center g-2 mb-3">
          <div class="col-4"><div class="fs-4 fw-bold"><?= (int) $c['production_progress'] ?></div><div class="small text-body-secondary">En cours</div></div>
          <div class="col-4"><div class="fs-4 fw-bold <?= $c['production_late'] ? 'text-danger' : '' ?>"><?= (int) $c['production_late'] ?></div><div class="small text-body-secondary">En retard</div></div>
          <div class="col-4"><div class="fs-4 fw-bold"><?= (int) $c['to_deliver'] ?></div><div class="small text-body-secondary">À livrer</div></div>
        </div>
        <?php if (!$urgent): ?>
          <?= $empty('bi-check2-circle', 'Aucune échéance de production dans les 48 h.') ?>
        <?php else: foreach ($urgent as $o): $late = strtotime((string) $o['deadline']) < time(); ?>
          <div class="hoc-mini">
            <span class="text-truncate me-2"><strong><?= e($o['number']) ?></strong> · <?= e($o['title']) ?><br>
              <small class="text-body-secondary"><?= e($o['customer']) ?> · <?= e($statusLabels[$o['status']] ?? $o['status']) ?></small></span>
            <span class="text-end small <?= $late ? 'text-danger fw-semibold' : '' ?>"><?= $late ? '<i class="bi bi-exclamation-triangle"></i> En retard<br>' : '' ?><?= e(format_date($o['deadline'], true)) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($financial): ?>
<div class="row g-3 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Ventes par catégorie — <?= date('Y') ?></div>
      <div class="card-body">
        <?php if (!$byCategory): ?><?= $empty('bi-tags', 'Aucune vente cette année.') ?>
        <?php else: ?><div class="hoc-chart-sm"><canvas id="chartCategory" role="img" aria-label="Chiffre d'affaires par catégorie"></canvas></div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Ventes par machine — <?= date('Y') ?></div>
      <div class="card-body">
        <?php if (!$byMachine): ?><?= $empty('bi-printer', 'Aucune vente de produit associé à une machine cette année.') ?>
        <?php else: ?><div class="hoc-chart-sm"><canvas id="chartMachine" role="img" aria-label="Chiffre d'affaires par machine"></canvas></div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['Produits les plus vendus', $topProducts, 'bi-box', true],
      ['Services les plus vendus', $topServices, 'bi-brush', true],
      ['Meilleurs clients', $topCustomers, 'bi-person-check', false],
  ] as [$heading, $rows, $icon, $withQty]): ?>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header fw-semibold"><?= e($heading) ?> — <?= date('Y') ?></div>
        <?php if (!$rows): ?>
          <div class="card-body"><?= $empty($icon, 'Aucune donnée pour le moment.') ?></div>
        <?php else: $max = max(array_map(static fn($r) => (float) $r['value'], $rows)) ?: 1; ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($rows as $r): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between gap-2">
                  <span class="text-truncate"><?= e($r['label']) ?></span>
                  <strong class="text-nowrap"><?= e(money($r['value'])) ?></strong>
                </div>
                <div class="progress mt-1" style="height:4px" aria-hidden="true"><div class="progress-bar hoc-bar" style="width:<?= max(2, round((float) $r['value'] / $max * 100)) ?>%"></div></div>
                <small class="text-body-secondary"><?= $withQty ? e(number_fr($r['quantity'], 0)) . ' ' . e($r['unit'] ?? '') . ' vendus' : (int) $r['documents'] . ' document(s)' ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php if (can('machines.view')): ?>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Performance des machines — ce mois</div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Machine</th><th>État</th><th class="text-end">Heures</th><th class="text-end">Travaux actifs</th><th class="text-end">Maintenance</th></tr></thead>
          <tbody>
          <?php if (!$machines): ?><tr><td colspan="5"><?= $empty('bi-printer', 'Aucune machine active.') ?></td></tr><?php endif; ?>
          <?php foreach ($machines as $m): [$sl, $sc] = $machineStatus[$m['status']] ?? [$m['status'], 'secondary'];
            $due = $m['next_maintenance_at'] !== null && strtotime((string) $m['next_maintenance_at']) <= strtotime('+7 days'); ?>
            <tr>
              <td class="fw-semibold"><?= e($m['name']) ?></td>
              <td><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span></td>
              <td class="text-end"><?= e(number_fr($m['hours'], 1)) ?> h</td>
              <td class="text-end"><?= (int) $m['active_jobs'] ?></td>
              <td class="text-end small <?= $due ? 'text-danger fw-semibold' : 'text-body-secondary' ?>">
                <?= $m['next_maintenance_at'] ? ($due ? '<i class="bi bi-wrench"></i> ' : '') . e(format_date($m['next_maintenance_at'])) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if (can('stock.view') || can('products.view')): ?>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Stock faible</div>
      <?php if (!$lowStock): ?>
        <div class="card-body"><?= $empty('bi-check2-circle', 'Aucun article sous le seuil minimum.') ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Article</th><th>Type</th><th class="text-end">Stock</th><th class="text-end">Minimum</th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $s): ?>
              <tr>
                <td><span class="fw-semibold"><?= e($s['name']) ?></span><br><small class="text-body-secondary"><?= e($s['code']) ?></small></td>
                <td><?= e($s['kind']) ?></td>
                <td class="text-end <?= (float) $s['qty'] <= 0 ? 'text-danger fw-bold' : 'text-warning-emphasis fw-semibold' ?>"><?= e(number_fr($s['qty'], 2)) ?> <?= e($s['symbol']) ?></td>
                <td class="text-end text-body-secondary"><?= e(number_fr($s['min_stock'], 2)) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3">
  <?php if ($financial && can('payments.view')): ?>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Paiements récents</div>
      <?php if (!$payments): ?>
        <div class="card-body"><?= $empty('bi-cash-stack', 'Aucun paiement enregistré.') ?></div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($payments as $pay): $refund = $pay['direction'] === 'refund'; ?>
            <li class="list-group-item d-flex justify-content-between gap-2">
              <span class="text-truncate"><strong><?= e($pay['customer']) ?></strong><br>
                <small class="text-body-secondary"><?= e($pay['number']) ?> · <?= e($pay['method']) ?> · <?= e(format_date($pay['payment_date'], true)) ?></small></span>
              <strong class="text-nowrap <?= $refund ? 'text-danger' : '' ?>"><?= $refund ? '−' : '' ?><?= e(money($pay['amount'])) ?></strong>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php if (can('logs.view')): ?>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header fw-semibold d-flex justify-content-between">Activité récente <a href="<?= e(url('logs')) ?>" class="small text-decoration-none">Tout voir</a></div>
      <ul class="list-group list-group-flush">
        <?php if (!$activity): ?><li class="list-group-item"><?= $empty('bi-clock-history', 'Aucune activité.') ?></li><?php endif; ?>
        <?php foreach ($activity as $a): ?>
          <li class="list-group-item">
            <div class="fw-semibold small"><?= e($a['description'] ?: $a['action']) ?></div>
            <small class="text-body-secondary"><?= e($a['full_name'] ?? 'Système') ?> · <?= e(format_date($a['created_at'], true)) ?></small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($financial): ?>
<script type="application/json" id="dashData"><?= json_encode([
    'monthly'    => $monthly,
    'daily'      => $daily,
    'byCategory' => array_map(static fn($r) => ['label' => $r['label'], 'value' => round((float) $r['value'], 2)], $byCategory),
    'byMachine'  => array_map(static fn($r) => ['label' => $r['label'], 'value' => round((float) $r['value'], 2)], $byMachine),
    'currency'   => (string) setting('currency_symbol', 'DH'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= e(asset('js/dashboard.js')) ?>"></script>
<?php endif; ?>
