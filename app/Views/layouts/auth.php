<?php /** Gabarit des pages publiques (connexion, erreurs hors session). Variables : $content, $title */ ?>
<!doctype html>
<html lang="fr">
<head>
<?php \App\Core\View::partial('head', ['title' => $title ?? '']); ?>
</head>
<body class="hoc-auth">
  <main class="hoc-auth-wrap">
    <section class="hoc-auth-brand d-none d-lg-flex">
      <div>
        <div class="hoc-logo-mark mb-4">HO</div>
        <h1 class="display-6 fw-bold mb-3"><?= e(setting('business_name', 'HO CREATIVE')) ?></h1>
        <p class="lead opacity-75 mb-4"><?= e(setting('business_activity', 'Impression • Publicité • Design • Communication')) ?></p>
        <ul class="list-unstyled hoc-auth-features">
          <li><i class="bi bi-cart3"></i> Point de vente rapide</li>
          <li><i class="bi bi-file-earmark-text"></i> Devis, factures et avoirs</li>
          <li><i class="bi bi-printer"></i> Production et calcul des coûts</li>
          <li><i class="bi bi-box-seam"></i> Stock, achats et machines</li>
        </ul>
      </div>
      <small class="opacity-50">POS &amp; ERP — v<?= e(APP_VERSION) ?></small>
    </section>
    <section class="hoc-auth-panel">
      <div class="hoc-auth-card">
        <?php \App\Core\View::partial('flash'); ?>
        <?= $content ?>
      </div>
    </section>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
