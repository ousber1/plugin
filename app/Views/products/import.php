<?php /** Import CSV du catalogue. Variables : $report */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Importer le catalogue</h1><p class="text-body-secondary mb-0">Produits et services depuis un fichier CSV (Excel : « CSV UTF-8 »).</p></div>
  <a href="<?= e(url('products')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<?php \App\Core\View::partial('import_report', ['report' => $report, 'back' => 'products']); ?>
<div class="row g-4">
  <div class="col-lg-6"><form method="post" action="<?= e(url('products/import')) ?>" enctype="multipart/form-data" class="card"><div class="card-body"><?= csrf_field() ?>
    <label class="form-label" for="file">Fichier CSV</label><input class="form-control mb-3" type="file" id="file" name="file" accept=".csv,text/csv" required>
    <button class="btn btn-dark"><i class="bi bi-upload"></i> Lancer l'import</button></div></form></div>
  <div class="col-lg-6"><div class="card"><div class="card-body small">
    <p class="fw-semibold mb-2">Règles d'import</p>
    <ul class="mb-3">
      <li>Un <strong>Code</strong> existant met à jour l'article ; sinon il est créé (code automatique si vide).</li>
      <li>Colonnes : Code, Nom, Nom FR, Type, Catégorie, Unité, Prix achat, Coût estimé, Prix vente, Prix minimum, Prix professionnel, Prix gros, Prix contrat, TVA, Code-barres, Suivi stock, Stock, Stock minimum, Description, Actif.</li>
      <li>Unité : code, nom ou symbole (piece, m2, ml, Mètre carré, m²…). Catégorie : nom exact d'une catégorie existante. TVA : taux (20, 14, 10, 7, 0).</li>
      <li><strong>Stock</strong> : utilisé uniquement comme stock initial d'un nouvel article (mouvement tracé). Le stock d'un article existant n'est jamais écrasé : utilisez un inventaire.</li>
      <li>Nombres acceptés : « 1 250,50 » ou « 1250.50 ».</li>
    </ul>
    <a class="btn btn-sm btn-outline-dark" href="<?= e(url('products/import-template')) ?>"><i class="bi bi-filetype-csv"></i> Télécharger le modèle</a>
  </div></div></div>
</div>
