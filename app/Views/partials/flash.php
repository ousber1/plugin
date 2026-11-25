<?php
/** Messages flash (succès, erreur, avertissement). */
$icons = ['success' => 'check-circle', 'danger' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];
foreach (\App\Core\Session::getFlashes() as $type => $message):
    $type = array_key_exists($type, $icons) ? $type : 'info'; ?>
  <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-<?= e($icons[$type]) ?>"></i>
    <div><?= e($message) ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
  </div>
<?php endforeach; ?>
