<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Installation – HO CREATIVE</title><link rel="stylesheet" href="/assets/app.css"></head>
<body><div class="container section" style="max-width:820px">
<h1>Installation de HO CREATIVE</h1>
@if($done)
  <div class="alert alert-ok"><strong>Installation terminée.</strong> L'installateur est désormais verrouillé (storage/app/installed.lock). Ajoutez la tâche cron indiquée dans le guide, puis <a href="/admin/connexion">connectez-vous au back-office</a>. Boutique : <a href="/">accueil</a>.</div>
@else
  @if($error)<div class="alert alert-err">{{ $error }}</div>@endif
  @if($checks)
    <div class="card"><h2 style="font-size:1.1rem">Pré-requis</h2><ul>@foreach($checks as $k => $ok)<li class="{{ $ok ? 'check-ok' : 'error-text' }}">{{ $ok ? '✔' : '✘' }} {{ $k }}</li>@endforeach</ul></div>
  @endif
  <form class="card" method="post" action="/install" autocomplete="off">
    <div class="alert alert-info small">Avant de commencer : cPanel → <b>Bases de données MySQL</b> → créez une base, un utilisateur, puis ajoutez l'utilisateur à la base avec <b>tous les privilèges</b>. Notez les noms complets (ils commencent souvent par votre identifiant cPanel, ex. <code>moncompte_hoc</code>).</div>
    <div class="field"><label>URL du site *</label><input name="app_url" required value="{{ $url }}"></div>
    <h3>Base de données MySQL / MariaDB</h3>
    <div class="grid-2"><div class="field"><label>Serveur</label><input name="db_host" value="localhost" required readonly></div><div class="field"><label>Port</label><input name="db_port" value="3306" required></div>
      <div class="field"><label>Nom de la base</label><input name="db_database" required placeholder="moncompte_hoc"></div><div class="field"><label>Utilisateur MySQL</label><input name="db_username" required placeholder="moncompte_hocuser"></div>
      <div class="field"><label>Mot de passe</label><input type="password" name="db_password"></div></div>
    <h3>Administrateur (propriétaire)</h3>
    <div class="grid-2"><div class="field"><label>Nom</label><input name="admin_name" required></div><div class="field"><label>E-mail</label><input type="email" name="admin_email" required></div>
      <div class="field"><label>Mot de passe (10 caractères min.)</label><input type="password" name="admin_password" required minlength="10"></div></div>
    <h3>E-mail SMTP (optionnel – laissé vide, le serveur d'e-mail de l'hébergement est utilisé)</h3>
    <div class="grid-2"><div class="field"><label>Serveur SMTP</label><input name="mail_host" placeholder="mail.votredomaine.ma"></div><div class="field"><label>Port</label><input name="mail_port" value="587"></div>
      <div class="field"><label>Utilisateur</label><input name="mail_username"></div><div class="field"><label>Mot de passe</label><input type="password" name="mail_password"></div>
      <div class="field"><label>Adresse d'expédition</label><input type="email" name="mail_from"></div></div>
    <label class="check"><input type="checkbox" name="demo" value="1"> Charger les données de démonstration (clairement marquées « DÉMO », supprimables)</label>
    <button class="btn btn-accent" style="margin-top:12px">Installer</button>
  </form>
@endif
</div></body></html>
