<div class="text-center py-5">
  <div class="display-1 fw-bold hoc-accent-text">404</div>
  <h2 class="h4 mb-3"><?= e($title ?? 'Page introuvable') ?></h2>
  <p class="text-body-secondary mb-4">La page demandée n'existe pas ou a été déplacée.</p>
  <a href="<?= e(url('')) ?>" class="btn btn-dark"><i class="bi bi-house me-1"></i> Retour à l'accueil</a>
</div>
