<div class="text-center py-5">
  <div class="display-1 fw-bold hoc-accent-text">500</div>
  <h2 class="h4 mb-3">Erreur interne</h2>
  <p class="text-body-secondary mb-4">Une erreur inattendue est survenue. Elle a été enregistrée dans le journal technique.</p>
  <?php if (!empty($debugMessage)): ?>
    <pre class="text-start small bg-body-tertiary p-3 rounded"><?= e($debugMessage) ?></pre>
  <?php endif; ?>
  <a href="<?= e(url('')) ?>" class="btn btn-dark"><i class="bi bi-house me-1"></i> Retour à l'accueil</a>
</div>
