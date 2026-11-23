<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Installation – HO CREATIVE</title><link rel="stylesheet" href="/assets/app.css"></head>
<body><div class="container section" style="max-width:820px">
<h1>Installation de HO CREATIVE</h1>
@if($done)
  <div class="alert alert-ok"><strong>Installation terminée.</strong> L'installateur est désormais verrouillé (storage/app/installed.lock). Ajoutez la tâche cron indiquée dans le guide, puis <a href="/admin/connexion">connectez-vous au back-office</a>.</div>
@else
  @if($error)<div class="alert alert-err">{{ $error }}</div>@endif
  @if($checks)
    <div class="card"><h2 style="font-size:1.1rem">Pré-requis</h2><ul>@foreach($checks as $k => $ok)<li class="{{ $ok ? 'check-ok' : 'error-text' }}">{{ $ok ? '✔' : '✘' }} {{ $k }}</li>@endforeach</ul></div>
  @endif
  <form class="card" method="post" action="/install" autocomplete="off">
    <div class="alert alert-info small">Sécurité : ouvrez <code>storage/app/install-token.txt</code> via le Gestionnaire de fichiers cPanel et copiez son contenu ci-dessous. Ce fichier est hors du dossier public.</div>
    <div class="field"><label>Jeton d'installation *</label><input name="token" required></div>
    <div class="field"><label>URL du site *</label><input name="app_url" required value="{{ $url }}"></div>
    <h3>Base de données MySQL / MariaDB</h3>
    <div class="grid-2"><div class="field"><label>Hôte</label><input name="db_host" value="localhost" required></div><div class="field"><label>Port</label><input name="db_port" value="3306" required></div>
      <div class="field"><label>Base</label><input name="db_database" required></div><div class="field"><label>Utilisateur</label><input name="db_username" required></div>
      <div class="field"><label>Mot de passe</label><input type="password" name="db_password"></div></div>
    <h3>Administrateur (propriétaire)</h3>
    <div class="grid-2"><div class="field"><label>Nom</label><input name="admin_name" required></div><div class="field"><label>E-mail</label><input type="email" name="admin_email" required></div>
      <div class="field"><label>Mot de passe (10 caractères min.)</label><input type="password" name="admin_password" required minlength="10"></div></div>
    <h3>E-mail SMTP (optionnel, modifiable ensuite dans .env)</h3>
    <div class="grid-2"><div class="field"><label>Serveur SMTP</label><input name="mail_host" placeholder="mail.votredomaine.ma"></div><div class="field"><label>Port</label><input name="mail_port" value="587"></div>
      <div class="field"><label>Utilisateur</label><input name="mail_username"></div><div class="field"><label>Mot de passe</label><input type="password" name="mail_password"></div>
      <div class="field"><label>Adresse d'expédition</label><input type="email" name="mail_from"></div></div>
    <label class="check"><input type="checkbox" name="demo" value="1"> Charger les données de démonstration (clairement marquées « DÉMO », supprimables)</label>
    <button class="btn btn-accent" style="margin-top:12px">Installer</button>
  </form>
@endif
</div></body></html>
