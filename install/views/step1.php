<?php
/** Étape 1 : vérification des prérequis du serveur. */
$checks = Installer::requirements();
$ok = Installer::requirementsMet($checks);
?>
<h2 class="h5 fw-bold mb-1">Bienvenue</h2>
<p class="text-body-secondary">Cet assistant installe votre logiciel de gestion en quelques minutes.
  Munissez-vous des identifiants de votre base de données MySQL (cPanel &gt; Bases de données MySQL).</p>

<div class="table-responsive mb-4">
  <table class="table align-middle">
    <thead><tr><th>Vérification</th><th>Détail</th><th class="text-end">État</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $c): ?>
      <tr>
        <td><?= $e($c['label']) ?><?= $c['required'] ? '' : ' <span class="badge text-bg-light border">recommandé</span>' ?></td>
        <td class="small text-body-secondary"><?= $e($c['detail']) ?></td>
        <td class="text-end">
          <?php if ($c['ok']): ?><i class="bi bi-check-circle-fill text-success fs-5" title="OK"></i>
          <?php elseif ($c['required']): ?><i class="bi bi-x-circle-fill text-danger fs-5" title="Obligatoire"></i>
          <?php else: ?><i class="bi bi-exclamation-triangle-fill text-warning fs-5" title="Recommandé"></i><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between align-items-center">
  <a href="index.php?step=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Revérifier</a>
  <button type="submit" class="btn btn-dark btn-lg" <?= $ok ? '' : 'disabled' ?>>Continuer <i class="bi bi-arrow-right"></i></button>
</div>
