<?php /** Gabarit de consultation publique d'un document (lien sécurisé). Variables : $content, $title */ ?>
<!doctype html>
<html lang="fr">
<head>
<?php \App\Core\View::partial('head', ['title' => $title ?? '']); ?>
<meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-body-tertiary">
  <header class="bg-dark text-white py-3 mb-4"><div class="container hoc-public d-flex justify-content-between align-items-center gap-2">
    <strong class="fs-5"><?= e(setting('business_name', 'HO CREATIVE')) ?></strong>
    <span class="small opacity-75 d-none d-sm-inline"><?= e(setting('business_phone', '')) ?></span>
  </div></header>
  <main class="container hoc-public pb-5">
    <?php \App\Core\View::partial('flash'); ?>
    <?= $content ?>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
