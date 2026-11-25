<?php
/** En-tête HTML commun. Variables : $title */
$appName = (string) setting('business_name', 'HO CREATIVE');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($title ?? '') !== '' ? $title . ' — ' . $appName : $appName) ?></title>
<script>
  // Thème appliqué avant le rendu pour éviter le flash
  (function () {
    var t = 'auto';
    try { t = localStorage.getItem('hoc-theme') || <?= json_encode((string) (auth_user()['theme'] ?? setting('default_theme', 'auto'))) ?>; } catch (e) {}
    if (t === 'auto') { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    document.documentElement.setAttribute('data-bs-theme', t);
  })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
<style>:root { --hoc-accent: <?= e(preg_match('/^#[0-9a-f]{6}$/i', (string) setting('accent_color', '')) ? setting('accent_color') : '#ff3d00') ?>; }</style>
