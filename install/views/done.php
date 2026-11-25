<?php
/** Écran de fin d'installation (affiché une seule fois). Variable : $done */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$appUrl = (string) ($done['url'] ?? '../');
if (!preg_match('#^https?://#', $appUrl)) { $appUrl = '../'; }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
  <title>Installation terminée — HO CREATIVE</title>
  <script>document.documentElement.setAttribute('data-bs-theme', window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');</script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
</head>
<body class="hoc-install">
<div class="container px-3"><div class="hoc-install-card">
  <div class="card shadow-sm"><div class="card-body p-4 p-md-5 text-center">
    <div class="display-4 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
    <h1 class="h3 fw-bold">Installation terminée</h1>
    <p class="text-body-secondary">HO CREATIVE POS &amp; ERP est prêt. Connectez-vous avec le compte <strong><?= $e($done['username'] ?? '') ?></strong>.</p>
    <div class="alert alert-warning text-start mx-auto" style="max-width:560px">
      <div class="fw-semibold mb-1"><i class="bi bi-shield-lock me-1"></i> Sécurité — à faire maintenant</div>
      <ul class="mb-0 small">
        <li>Supprimez le dossier <code>install/</code> du serveur (l'assistant est déjà verrouillé).</li>
        <li>Activez HTTPS (certificat SSL gratuit dans cPanel &gt; SSL/TLS Status).</li>
        <li>Vérifiez que <code><?= $e($appUrl) ?>/config/config.php</code> renvoie une erreur 403.</li>
      </ul>
    </div>
    <a href="<?= $e($appUrl) ?>/index.php?route=login" class="btn btn-dark btn-lg mt-2"><i class="bi bi-box-arrow-in-right me-1"></i> Se connecter</a>
  </div></div>
</div></div>
</body>
</html>
