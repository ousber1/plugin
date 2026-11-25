<?php
/** Étape 2 : connexion à la base de données. */
$hasPass = !empty($s['database']['pass']);
?>
<h2 class="h5 fw-bold mb-1">Base de données</h2>
<p class="text-body-secondary">Sur cPanel, créez d'abord une base et un utilisateur MySQL, puis associez-les avec « Tous les privilèges ».
  Les noms sont souvent préfixés par votre identifiant cPanel (ex. <code>moncompte_erp</code>).</p>

<div class="row g-3">
  <div class="col-md-8">
    <label class="form-label" for="db_host">Serveur MySQL</label>
    <input class="form-control" id="db_host" name="db_host" value="<?= $e($val('database', 'host', 'localhost')) ?>" required>
  </div>
  <div class="col-md-4">
    <label class="form-label" for="db_port">Port</label>
    <input class="form-control" id="db_port" name="db_port" type="number" min="1" max="65535" value="<?= $e($val('database', 'port', 3306)) ?>">
  </div>
  <div class="col-md-12">
    <label class="form-label" for="db_name">Nom de la base de données</label>
    <input class="form-control" id="db_name" name="db_name" value="<?= $e($val('database', 'name')) ?>" required pattern="[A-Za-z0-9_\-$]+">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="db_user">Utilisateur MySQL</label>
    <input class="form-control" id="db_user" name="db_user" value="<?= $e($val('database', 'user')) ?>" required autocomplete="off">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="db_pass">Mot de passe MySQL</label>
    <input class="form-control" id="db_pass" name="db_pass" type="password" autocomplete="new-password"
           placeholder="<?= $hasPass ? 'Laisser vide pour conserver' : '' ?>">
    <?php if ($hasPass): ?><input type="hidden" name="keep_pass" value="1"><?php endif; ?>
  </div>
  <div class="col-12">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="db_create" name="db_create" value="1" <?= $val('database', 'create') ? 'checked' : '' ?>>
      <label class="form-check-label" for="db_create">Créer la base si elle n'existe pas <span class="text-body-secondary small">(nécessite le droit CREATE ; souvent indisponible sur hébergement mutualisé)</span></label>
    </div>
  </div>
  <div class="col-12"><hr></div>
  <div class="col-md-12">
    <label class="form-label" for="app_url">URL de l'application</label>
    <input class="form-control" id="app_url" name="app_url" type="url" value="<?= $e($val('database', 'app_url', Installer::detectAppUrl())) ?>" required>
    <div class="form-text">Adresse publique d'accès au logiciel, sans barre finale. Détectée automatiquement.</div>
  </div>
  <div class="col-12">
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" id="pretty_urls" name="pretty_urls" value="1" <?= $val('database', 'pretty_urls', true) ? 'checked' : '' ?>>
      <label class="form-check-label" for="pretty_urls">Adresses simplifiées (…/login) lorsque le serveur le permet</label>
    </div>
    <div class="form-text">Le logiciel vérifie lui-même que la réécriture d'URL fonctionne ; sinon il utilise des adresses en <code>index.php?route=…</code>, qui fonctionnent partout.</div>
  </div>
  <div class="col-12" id="hostChecks">
    <div class="small text-body-secondary"><span class="spinner-border spinner-border-sm"></span> Vérification de l'hébergement…</div>
  </div>
</div>
<script>
  /* Vérifie depuis le navigateur : réécriture d'URL et protection des dossiers privés (.htaccess) */
  (function () {
    var box = document.getElementById('hostChecks'), input = document.getElementById('app_url');
    var row = function (ok, warn, text) {
      var icon = ok ? 'check-circle-fill text-success' : (warn ? 'exclamation-triangle-fill text-warning' : 'x-circle-fill text-danger');
      return '<div class="d-flex gap-2 small mb-1"><i class="bi bi-' + icon + '"></i><span>' + text + '</span></div>';
    };
    var run = function () {
      var base = input.value.replace(/\/+$/, '');
      var get = function (u) {
        return fetch(base + u, { cache: 'no-store' }).then(function (r) { return r.ok ? r.text() : ''; }).catch(function () { return ''; });
      };
      Promise.all([get('/__hoc-rewrite-test'), get('/storage/hoc-probe.txt')]).then(function (res) {
        var rewrite = res[0].indexOf('HOC-REWRITE-OK') === 0, exposed = res[1].indexOf('HOC-PRIVATE-PROBE') === 0;
        box.innerHTML = '<div class="border rounded-3 p-3">'
          + row(rewrite, true, rewrite ? 'Réécriture d\'URL active : adresses simplifiées disponibles.'
                                       : 'Réécriture d\'URL non détectée : le logiciel utilisera des adresses en <code>index.php?route=…</code> (aucun impact fonctionnel).')
          + row(!exposed, false, !exposed ? 'Dossiers privés protégés (.htaccess appliqué).'
                                          : '<strong>Dossiers privés accessibles depuis Internet</strong> : le fichier <code>.htaccess</code> n\'est pas appliqué. '
                                            + 'Vérifiez qu\'il a bien été envoyé à la racine (fichier caché) ou demandez à votre hébergeur d\'activer « AllowOverride ». '
                                            + 'Vous pouvez poursuivre, mais corrigez ce point avant d\'utiliser le logiciel avec des données réelles.')
          + '</div>';
      });
    };
    run();
    input.addEventListener('change', run);
  })();
</script>

<div class="d-flex justify-content-between mt-4">
  <a href="index.php?step=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
  <button type="submit" class="btn btn-dark btn-lg">Tester et continuer <i class="bi bi-arrow-right"></i></button>
</div>
