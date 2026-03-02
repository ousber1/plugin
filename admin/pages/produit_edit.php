<?php
/**
 * BERRADI PRINT - Ajout/Modification Produit
 */
$db = getDB();
$produit_id = (int)($_GET['id'] ?? 0);
$produit = null;

if ($produit_id) {
    $stmt = $db->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();
}

$categories = $db->query("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder'])) {
    verifyCsrf();

    $data = [
        'categorie_id' => (int)$_POST['categorie_id'],
        'nom' => clean($_POST['nom']),
        'slug' => preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower(clean($_POST['nom'])))),
        'description' => clean($_POST['description']),
        'description_courte' => clean($_POST['description_courte']),
        'prix_base' => floatval($_POST['prix_base']),
        'prix_unitaire' => floatval($_POST['prix_unitaire']) ?: null,
        'unite' => clean($_POST['unite']),
        'quantite_min' => (int)$_POST['quantite_min'],
        'delai_production' => clean($_POST['delai_production']),
        'populaire' => isset($_POST['populaire']) ? 1 : 0,
        'actif' => isset($_POST['actif']) ? 1 : 0,
        'ordre' => (int)$_POST['ordre'],
    ];

    if ($produit_id && $produit) {
        $sql = "UPDATE produits SET categorie_id=?, nom=?, slug=?, description=?, description_courte=?, prix_base=?, prix_unitaire=?, unite=?, quantite_min=?, delai_production=?, populaire=?, actif=?, ordre=? WHERE id=?";
        $db->prepare($sql)->execute([...array_values($data), $produit_id]);
        setFlash('success', 'Produit mis à jour.');
    } else {
        $sql = "INSERT INTO produits (categorie_id, nom, slug, description, description_courte, prix_base, prix_unitaire, unite, quantite_min, delai_production, populaire, actif, ordre) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute(array_values($data));
        $produit_id = $db->lastInsertId();
        setFlash('success', 'Produit créé.');
    }
    redirect('index.php?page=produit_edit&id=' . $produit_id);
}
?>

<div class="mb-4">
    <a href="index.php?page=produits" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <h4 class="fw-bold"><?= $produit ? 'Modifier' : 'Nouveau' ?> Produit</h4>
</div>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Informations</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nom du produit *</label>
                            <input type="text" name="nom" class="form-control" required value="<?= $produit['nom'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Catégorie *</label>
                            <select name="categorie_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($produit && $produit['categorie_id'] == $cat['id']) ? 'selected' : '' ?>><?= $cat['nom'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description courte</label>
                            <input type="text" name="description_courte" class="form-control" maxlength="500" value="<?= $produit['description_courte'] ?? '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description complète</label>
                            <textarea name="description" class="form-control" rows="4"><?= $produit['description'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Tarification</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Prix de base (DH) *</label>
                            <input type="number" name="prix_base" class="form-control" step="0.01" required value="<?= $produit['prix_base'] ?? '0' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Prix unitaire (DH)</label>
                            <input type="number" name="prix_unitaire" class="form-control" step="0.01" value="<?= $produit['prix_unitaire'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unité</label>
                            <select name="unite" class="form-select">
                                <?php foreach (['pièce', 'lot de 100', 'lot de 500', 'lot de 1000', 'm²', 'mètre linéaire', 'page', 'carnet'] as $u): ?>
                                <option value="<?= $u ?>" <?= ($produit && $produit['unite'] == $u) ? 'selected' : '' ?>><?= $u ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantité minimum</label>
                            <input type="number" name="quantite_min" class="form-control" min="1" value="<?= $produit['quantite_min'] ?? 1 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Délai de production</label>
                            <input type="text" name="delai_production" class="form-control" value="<?= $produit['delai_production'] ?? '24-48h' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ordre d'affichage</label>
                            <input type="number" name="ordre" class="form-control" value="<?= $produit['ordre'] ?? 0 ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold">Options</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= (!$produit || $produit['actif']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="actif">Actif (visible)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="populaire" id="populaire" <?= ($produit && $produit['populaire']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="populaire">Produit populaire</label>
                    </div>
                </div>
            </div>
            <button type="submit" name="sauvegarder" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder
            </button>
        </div>
    </div>
</form>
