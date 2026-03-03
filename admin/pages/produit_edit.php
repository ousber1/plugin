<?php
/**
 * BERRADI PRINT - Ajout/Modification Produit avec Image Upload
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
        'description' => $_POST['description'] ?? '',
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

    // Image upload
    $image_name = $produit['image'] ?? '';
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $upload_dir = __DIR__ . '/../../uploads/produits/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            // Delete old image
            if ($image_name && file_exists($upload_dir . $image_name)) {
                unlink($upload_dir . $image_name);
            }
            $image_name = uniqid('prod_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
        }
    }
    // Delete image if requested
    if (isset($_POST['supprimer_image']) && $image_name) {
        $upload_dir = __DIR__ . '/../../uploads/produits/';
        if (file_exists($upload_dir . $image_name)) {
            unlink($upload_dir . $image_name);
        }
        $image_name = '';
    }
    $data['image'] = $image_name;

    // Stock fields
    $_has_stock_cols = false;
    try { $db->query("SELECT stock_actif FROM produits LIMIT 1"); $_has_stock_cols = true; } catch (Exception $e) {}

    if ($produit_id && $produit) {
        $sql = "UPDATE produits SET categorie_id=?, nom=?, slug=?, description=?, description_courte=?, prix_base=?, prix_unitaire=?, unite=?, quantite_min=?, delai_production=?, populaire=?, actif=?, ordre=?, image=? WHERE id=?";
        $db->prepare($sql)->execute([...array_values($data), $produit_id]);
        if ($_has_stock_cols) {
            $db->prepare("UPDATE produits SET stock_actif=?, stock_quantite=?, stock_min=? WHERE id=?")->execute([
                isset($_POST['stock_actif']) ? 1 : 0,
                (int)($_POST['stock_quantite'] ?? 0),
                (int)($_POST['stock_min'] ?? 0),
                $produit_id
            ]);
        }
        // SEO meta fields
        try {
            $db->prepare("UPDATE produits SET meta_title=?, meta_description=? WHERE id=?")->execute([
                clean($_POST['meta_title'] ?? '') ?: null,
                clean($_POST['meta_description'] ?? '') ?: null,
                $produit_id
            ]);
        } catch (Exception $e) {}
        setFlash('success', 'Produit mis à jour avec succès.');
    } else {
        $sql = "INSERT INTO produits (categorie_id, nom, slug, description, description_courte, prix_base, prix_unitaire, unite, quantite_min, delai_production, populaire, actif, ordre, image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute(array_values($data));
        $produit_id = $db->lastInsertId();
        if ($_has_stock_cols) {
            $db->prepare("UPDATE produits SET stock_actif=?, stock_quantite=?, stock_min=? WHERE id=?")->execute([
                isset($_POST['stock_actif']) ? 1 : 0,
                (int)($_POST['stock_quantite'] ?? 0),
                (int)($_POST['stock_min'] ?? 0),
                $produit_id
            ]);
        }
        // SEO meta fields
        try {
            $db->prepare("UPDATE produits SET meta_title=?, meta_description=? WHERE id=?")->execute([
                clean($_POST['meta_title'] ?? '') ?: null,
                clean($_POST['meta_description'] ?? '') ?: null,
                $produit_id
            ]);
        } catch (Exception $e) {}
        setFlash('success', 'Produit créé avec succès.');
    }
    redirect('index.php?page=produit_edit&id=' . $produit_id);
}

// Current image
$current_image = '';
if ($produit && !empty($produit['image'])) {
    $img_path = __DIR__ . '/../../uploads/produits/' . $produit['image'];
    if (file_exists($img_path)) {
        $current_image = '../uploads/produits/' . $produit['image'];
    }
}
?>

<div class="mb-4">
    <a href="index.php?page=produits" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour aux produits</a>
    <h4 class="fw-bold"><?= $produit ? 'Modifier' : 'Nouveau' ?> Produit</h4>
</div>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Informations -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-info-circle me-2"></i>Informations</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nom du produit *</label>
                            <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($produit['nom'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Catégorie *</label>
                            <select name="categorie_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($produit && $produit['categorie_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description courte</label>
                            <input type="text" name="description_courte" class="form-control" maxlength="500" value="<?= htmlspecialchars($produit['description_courte'] ?? '') ?>">
                            <small class="text-muted">Résumé affiché dans les listes de produits</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description complète</label>
                            <textarea name="description" id="description" class="form-control" rows="8"><?= htmlspecialchars($produit['description'] ?? '') ?></textarea>
                            <small class="text-muted">Éditeur riche : formatez votre description avec les outils ci-dessus.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image du produit -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-image me-2"></i>Image du produit</div>
                <div class="card-body">
                    <?php if ($current_image): ?>
                    <div class="mb-3">
                        <div class="position-relative d-inline-block">
                            <img src="<?= $current_image ?>" alt="Image actuelle" class="rounded border" style="max-width:300px;max-height:200px;object-fit:cover;">
                            <div class="mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="supprimer_image" id="supprimer_image" value="1">
                                    <label class="form-check-label text-danger" for="supprimer_image">
                                        <i class="bi bi-trash me-1"></i>Supprimer cette image
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <label class="form-label"><?= $current_image ? 'Changer l\'image' : 'Ajouter une image' ?></label>
                        <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <small class="text-muted">Formats acceptés: JPG, PNG, GIF, WebP, SVG. Max 5 MB.</small>
                    </div>
                    <div id="imagePreview" class="mt-3" style="display:none;">
                        <img id="previewImg" class="rounded border" style="max-width:300px;max-height:200px;object-fit:cover;">
                    </div>
                </div>
            </div>

            <!-- Tarification -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-currency-dollar me-2"></i>Tarification</div>
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
                            <input type="text" name="delai_production" class="form-control" value="<?= htmlspecialchars($produit['delai_production'] ?? '24-48h') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ordre d'affichage</label>
                            <input type="number" name="ordre" class="form-control" value="<?= $produit['ordre'] ?? 0 ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO -->
            <?php
            $_has_meta = false;
            try { $db->query("SELECT meta_title FROM produits LIMIT 1"); $_has_meta = true; } catch (Exception $e) {}
            if ($_has_meta):
            ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-search me-2"></i>SEO</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Meta Title <small class="text-muted">(max 70 car.)</small></label>
                        <input type="text" name="meta_title" class="form-control" maxlength="70" value="<?= htmlspecialchars($produit['meta_title'] ?? '') ?>" placeholder="<?= htmlspecialchars(($produit['nom'] ?? 'Produit') . ' - ' . APP_NAME) ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Meta Description <small class="text-muted">(max 160 car.)</small></label>
                        <textarea name="meta_description" class="form-control" rows="2" maxlength="160" placeholder="Description pour Google..."><?= htmlspecialchars($produit['meta_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold">Options</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= (!$produit || $produit['actif']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="actif">Actif (visible sur le site)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="populaire" id="populaire" <?= ($produit && $produit['populaire']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="populaire">Produit populaire</label>
                    </div>
                </div>
            </div>

            <!-- Stock Management -->
            <?php
            // Check if stock columns exist
            $_has_stock = false;
            try {
                $db->query("SELECT stock_actif FROM produits LIMIT 1");
                $_has_stock = true;
            } catch (Exception $e) {}
            if ($_has_stock):
                $_stock_actif = $produit['stock_actif'] ?? 0;
                $_stock_qte = $produit['stock_quantite'] ?? 0;
                $_stock_min = $produit['stock_min'] ?? 0;
            ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold"><i class="bi bi-box-seam me-2"></i>Stock</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="stock_actif" id="stock_actif" <?= $_stock_actif ? 'checked' : '' ?> onchange="document.getElementById('stockFields').style.display = this.checked ? '' : 'none'">
                        <label class="form-check-label" for="stock_actif">Activer le suivi de stock</label>
                    </div>
                    <div id="stockFields" style="<?= $_stock_actif ? '' : 'display:none' ?>">
                        <div class="mb-3">
                            <label class="form-label">Quantité en stock</label>
                            <input type="number" name="stock_quantite" class="form-control" min="0" value="<?= $_stock_qte ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Stock minimum (alerte)</label>
                            <input type="number" name="stock_min" class="form-control" min="0" value="<?= $_stock_min ?>">
                            <small class="text-muted">Alerte quand le stock descend en dessous</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <button type="submit" name="sauvegarder" class="btn btn-primary w-100 btn-lg mb-3">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder
            </button>
            <?php if ($produit): ?>
            <a href="../index.php?page=produit&id=<?= $produit_id ?>" target="_blank" class="btn btn-outline-primary w-100">
                <i class="bi bi-eye me-2"></i>Voir sur le site
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#description',
    language: 'fr_FR',
    height: 400,
    menubar: false,
    plugins: 'lists link image table code wordcount',
    toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | code',
    content_style: 'body { font-family: Poppins, sans-serif; font-size: 14px; }',
    branding: false,
    promotion: false
});
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
