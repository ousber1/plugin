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
<?php
$hocBase = rtrim((string) \App\Core\Config::get('app.url', ''), '/');
$hocRewritePending = (bool) \App\Core\Config::get('app.pretty_urls', true) && !pretty_urls_active();
$hocProbeSecurity = can('settings.manage');
if ($hocRewritePending || $hocProbeSecurity): ?>
<script>
  /* Vérifications d'hébergement (une fois par session de navigateur) :
     1. la réécriture d'URL fonctionne-t-elle ? → active les adresses simplifiées ;
     2. (administrateurs) les dossiers privés sont-ils bien protégés par .htaccess ? */
  document.addEventListener('DOMContentLoaded', function () {
    var base = <?= json_encode($hocBase) ?>, ss = window.sessionStorage;
    var once = function (k) { try { if (ss.getItem(k)) { return false; } ss.setItem(k, '1'); } catch (e) {} return true; };
    <?php if ($hocRewritePending): ?>
    if (once('hoc-rw-probe')) {
      fetch(base + '/__hoc-rewrite-test', { cache: 'no-store', credentials: 'same-origin' }).catch(function () {});
    }
    <?php endif; ?>
    <?php if ($hocProbeSecurity): ?>
    var show = function () {
      var box = document.createElement('div');
      box.className = 'alert alert-danger d-flex gap-2';
      box.setAttribute('role', 'alert');
      box.innerHTML = '<i class="bi bi-shield-exclamation fs-5"></i><div><strong>Sécurité : les dossiers privés sont accessibles depuis Internet.</strong> '
        + 'Le serveur n\'applique pas le fichier <code>.htaccess</code> (fichier absent, ou réécriture / « AllowOverride » désactivés par l\'hébergeur). '
        + 'Vérifiez que <code>.htaccess</code> est présent à la racine de l\'application (fichier caché : activez « Afficher les fichiers cachés » dans le gestionnaire de fichiers), '
        + 'sinon demandez à votre hébergeur d\'activer la prise en charge de .htaccess. Voir docs/INSTALLATION.md.</div>';
      var main = document.querySelector('.hoc-content, .hoc-auth-card');
      if (main) { main.insertBefore(box, main.firstChild); }
    };
    var cached = null;
    try { cached = ss.getItem('hoc-sec-probe'); } catch (e) {}
    if (cached === 'exposed') { show(); }
    else if (cached === null) {
      fetch(base + '/storage/hoc-probe.txt', { cache: 'no-store', credentials: 'omit' })
        .then(function (r) { return r.ok ? r.text() : ''; })
        .then(function (t) {
          var exposed = t.indexOf('HOC-PRIVATE-PROBE') === 0;
          try { ss.setItem('hoc-sec-probe', exposed ? 'exposed' : 'ok'); } catch (e) {}
          if (exposed) { show(); }
        }).catch(function () {});
    }
    <?php endif; ?>
  });
</script>
<?php endif; ?>
<style>:root { --hoc-accent: <?= e(preg_match('/^#[0-9a-f]{6}$/i', (string) setting('accent_color', '')) ? setting('accent_color') : '#ff3d00') ?>; }</style>
