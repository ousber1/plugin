<?php /** Compte rendu d'import CSV. Variables : $report (créés, mis à jour, erreurs), $back */
if (!$report) { return; } ?>
<div class="alert <?= $report['error_count'] ? 'alert-warning' : 'alert-success' ?>">
  <div class="fw-semibold"><i class="bi bi-clipboard-check"></i> Import terminé : <?= (int) $report['created'] ?> créé(s), <?= (int) $report['updated'] ?> mis à jour, <?= (int) $report['error_count'] ?> ligne(s) ignorée(s).</div>
  <?php if ($report['errors']): ?>
    <ul class="small mb-0 mt-2"><?php foreach ($report['errors'] as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php if ($report['error_count'] > count($report['errors'])): ?><div class="small">… et <?= (int) ($report['error_count'] - count($report['errors'])) ?> autre(s).</div><?php endif; ?>
  <?php endif; ?>
  <a class="btn btn-sm btn-outline-dark mt-2" href="<?= e(url($back)) ?>">Voir la liste</a>
</div>
