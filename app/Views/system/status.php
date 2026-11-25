<?php
/**
 * État du système. Toutes les valeurs proviennent de la base de données.
 * Variables : $tables, $counts, $sequences, $dbVersion, $schema, $phpVersion, $installedAt, $lastLogs
 */
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1>
    <p class="text-body-secondary mb-0">Installation de <?= e(setting('business_name')) ?> opérationnelle.</p>
  </div>
  <span class="badge text-bg-success fs-6"><i class="bi bi-check2-circle me-1"></i>Système installé</span>
</div>


<div class="row g-3 mb-4">
  <?php foreach ([
      ['Tables', $tables, 'bi-database'],
      ['Version du schéma', $schema, 'bi-diagram-3'],
      ['MySQL / MariaDB', $dbVersion, 'bi-hdd-stack'],
      ['PHP', $phpVersion, 'bi-filetype-php'],
  ] as [$label, $value, $icon]): ?>
    <div class="col-6 col-xl-3">
      <div class="card hoc-stat h-100"><div class="card-body">
        <div class="hoc-stat-icon"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="text-body-secondary small"><?= e($label) ?></div>
        <div class="fs-5 fw-bold text-truncate"><?= e($value) ?></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header fw-semibold">Données de référence</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($counts as $label => $n): ?>
          <li class="list-group-item d-flex justify-content-between"><span><?= e($label) ?></span><span class="fw-semibold"><?= e($n) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header fw-semibold">Prochains numéros de documents</div>
      <div class="table-responsive" style="max-height: 420px">
        <table class="table table-sm mb-0 align-middle">
          <tbody>
          <?php foreach ($sequences as $s): ?>
            <tr><td><?= e($s['label']) ?></td><td class="text-end"><code><?= e($s['next']) ?></code></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header fw-semibold">Activité récente</div>
      <ul class="list-group list-group-flush small">
        <?php if (!$lastLogs): ?><li class="list-group-item text-body-secondary">Aucune activité.</li><?php endif; ?>
        <?php foreach ($lastLogs as $log): ?>
          <li class="list-group-item">
            <div class="fw-semibold"><?= e($log['description'] ?: $log['action']) ?></div>
            <div class="text-body-secondary"><?= e($log['full_name'] ?? 'Système') ?> · <?= e(format_date($log['created_at'], true)) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php if ($installedAt !== ''): ?>
  <p class="text-body-secondary small mt-3 mb-0">Installé le <?= e(format_date($installedAt, true)) ?>.</p>
<?php endif; ?>
