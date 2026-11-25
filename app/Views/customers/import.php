<?php /** Import CSV de clients. Variables : $report */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Importer des clients</h1>
    <p class="text-body-secondary mb-0">Fichier CSV (Excel : « Enregistrer sous › CSV UTF-8 »), séparateur point-virgule ou virgule.</p></div>
  <a href="<?= e(url('customers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<?php \App\Core\View::partial('import_report', ['report' => $report, 'back' => 'customers']); ?>
<div class="row g-4">
  <div class="col-lg-6"><form method="post" action="<?= e(url('customers/import')) ?>" enctype="multipart/form-data" class="card">
    <div class="card-body"><?= csrf_field() ?>
      <label class="form-label" for="file">Fichier CSV</label>
      <input class="form-control mb-3" type="file" id="file" name="file" accept=".csv,text/csv" required>
      <button class="btn btn-dark"><i class="bi bi-upload"></i> Lancer l'import</button>
    </div></form></div>
  <div class="col-lg-6"><div class="card"><div class="card-body small">
    <p class="fw-semibold mb-2">Règles d'import</p>
    <ul class="mb-3">
      <li>Si le <strong>Code</strong> correspond à un client existant, sa fiche est mise à jour ; sinon un client est créé (code automatique si vide).</li>
      <li>Colonnes reconnues : Code, Type, Nom, Entreprise, Téléphone, WhatsApp, Email, Adresse, Ville, ICE, IF, RC, CIN, Groupe, Limite de crédit, Notes, Actif.</li>
      <li>Type : Particulier, Entreprise, Association, Administration, Restaurant, Commerce, Agence, École.</li>
      <li>Les lignes invalides (e-mail, ICE de 15 chiffres…) sont ignorées et listées ; les autres sont importées.</li>
    </ul>
    <a class="btn btn-sm btn-outline-dark" href="<?= e(url('customers/import-template')) ?>"><i class="bi bi-filetype-csv"></i> Télécharger le modèle</a>
  </div></div></div>
</div>
