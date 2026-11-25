<?php
/**
 * Gabarit de l'assistant d'installation.
 * Variables : $step, $errors, $notice, $csrf, $val, $s
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Installation — HO CREATIVE POS &amp; ERP</title>
  <script>document.documentElement.setAttribute('data-bs-theme', window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');</script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
</head>
<body class="hoc-install">
<div class="container px-3">
  <div class="hoc-install-card">
    <div class="d-flex align-items-center gap-3 mb-4">
      <span class="hoc-logo-mark">HO</span>
      <div>
        <h1 class="h4 fw-bold mb-0">HO CREATIVE — POS &amp; ERP</h1>
        <div class="text-body-secondary">Assistant d'installation · version <?= $e(APP_VERSION) ?></div>
      </div>
    </div>

    <div class="hoc-steps mb-4" role="list">
      <?php foreach (STEPS as $n => $label):
        $cls = $n < $step ? 'done' : ($n === $step ? 'current' : ''); ?>
        <div class="hoc-step <?= $cls ?>" role="listitem"<?= $n === $step ? ' aria-current="step"' : '' ?>>
          <?php if ($n < $step): ?>
            <a href="?step=<?= $n ?>" class="text-reset text-decoration-none"><i class="bi bi-check2"></i> <?= $e($label) ?></a>
          <?php else: ?>
            <?= $n ?>. <?= $e($label) ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-4 p-md-5">
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1"></i> Veuillez corriger les points suivants :</div>
            <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        <?php if ($notice): ?>
          <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> <?= $e($notice) ?></div>
        <?php endif; ?>

        <form method="post" action="index.php?step=<?= (int) $step ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
          <input type="hidden" name="step" value="<?= (int) $step ?>">
          <?php include __DIR__ . '/step' . (int) $step . '.php'; ?>
        </form>
      </div>
    </div>
    <p class="text-center text-body-secondary small mt-4">© <?= date('Y') ?> HO CREATIVE — Impression, publicité, design et communication · Maroc</p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
