<?php
/**
 * Encadrés communs de la fiche d'un document : historique des envois et lien de partage.
 * Variables : $type, $id, $send
 */
?>
<div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">Envois
  <?php if ($send['history'] && can(\App\Controllers\DocumentController::TYPES[$type][1])): ?>
    <form method="post" action="<?= e(url('documents/' . $type . '/' . $id . '/revoke')) ?>" data-confirm="Désactiver les liens de consultation déjà envoyés ? Le client ne pourra plus ouvrir le document avec ces liens."><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0" title="Désactiver les liens envoyés"><i class="bi bi-link-45deg"></i> Révoquer les liens</button></form>
  <?php endif; ?></div>
  <?php if (!$send['history']): ?><div class="card-body small text-body-secondary">Pas encore envoyé.</div><?php else: ?>
  <ul class="list-group list-group-flush small">
    <?php foreach ($send['history'] as $h): ?>
      <li class="list-group-item"><i class="bi bi-<?= $h['channel'] === 'email' ? 'envelope' : 'whatsapp' ?>"></i> <?= e($h['recipient']) ?>
        <?= $h['status'] === 'failed' ? '<span class="badge text-bg-danger">échec</span>' : '' ?>
        <span class="d-block text-body-secondary"><?= e(format_date($h['created_at'], true)) ?> · <?= e($h['full_name'] ?? '') ?></span>
        <?= $h['error'] ? '<span class="d-block text-danger">' . e($h['error']) . '</span>' : '' ?></li>
    <?php endforeach; ?>
  </ul><?php endif; ?>
</div>
