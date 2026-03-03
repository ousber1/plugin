<?php
/**
 * BERRADI PRINT - Gestion Catégories avec Image Upload
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['ajouter_categorie'])) {
        $nom = clean($_POST['nom']);
        $slug = preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($nom)));
        $desc = clean($_POST['description']);
        $icone = clean($_POST['icone']) ?: 'bi-printer';
        $ordre = (int)$_POST['ordre'];

        // Image upload
        $image_name = '';
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/categories/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $image_name = uniqid('cat_') . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
            }
        }

        $db->prepare("INSERT INTO categories (nom, slug, description, icone, ordre, image) VALUES (?,?,?,?,?,?)")->execute([$nom, $slug, $desc, $icone, $ordre, $image_name]);
        setFlash('success', 'Catégorie ajoutée avec succès.');
        redirect('index.php?page=categories');
    }

    if (isset($_POST['modifier_categorie'])) {
        $cat_id = (int)$_POST['cat_id'];
        $nom = clean($_POST['nom']);
        $slug = preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($nom)));
        $desc = clean($_POST['description']);
        $icone = clean($_POST['icone']) ?: 'bi-printer';
        $ordre = (int)$_POST['ordre'];
        $actif = isset($_POST['actif']) ? 1 : 0;

        // Get existing image
        $stmt = $db->prepare("SELECT image FROM categories WHERE id = ?");
        $stmt->execute([$cat_id]);
        $existing = $stmt->fetch();
        $image_name = $existing['image'] ?? '';

        // New image upload
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/categories/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                // Delete old image
                if ($image_name && file_exists($upload_dir . $image_name)) {
                    unlink($upload_dir . $image_name);
                }
                $image_name = uniqid('cat_') . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
            }
        }

        // Delete image if requested
        if (isset($_POST['supprimer_image'])) {
            $upload_dir = __DIR__ . '/../../uploads/categories/';
            if ($image_name && file_exists($upload_dir . $image_name)) {
                unlink($upload_dir . $image_name);
            }
            $image_name = '';
        }

        $db->prepare("UPDATE categories SET nom=?, slug=?, description=?, icone=?, ordre=?, actif=?, image=? WHERE id=?")->execute([$nom, $slug, $desc, $icone, $ordre, $actif, $image_name, $cat_id]);
        setFlash('success', 'Catégorie mise à jour.');
        redirect('index.php?page=categories');
    }
}

$categories = $db->query("SELECT c.*, (SELECT COUNT(*) FROM produits WHERE categorie_id = c.id) as nb_produits FROM categories c ORDER BY c.ordre")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-tags me-2"></i>Catégories (<?= count($categories) ?>)</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouter">
        <i class="bi bi-plus-circle me-1"></i>Nouvelle catégorie
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr><th>Icône</th><th>Nom</th><th>Description</th><th class="text-center">Produits</th><th>Ordre</th><th>Statut</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat):
                    $cat_img = '';
                    if (!empty($cat['image'])) {
                        $img_path = __DIR__ . '/../../uploads/categories/' . $cat['image'];
                        if (file_exists($img_path)) {
                            $cat_img = '../uploads/categories/' . $cat['image'];
                        }
                    }
                ?>
                <tr class="<?= !$cat['actif'] ? 'opacity-50' : '' ?>">
                    <td>
                        <?php if ($cat_img): ?>
                        <img src="<?= $cat_img ?>" alt="" class="rounded" style="width:40px;height:40px;object-fit:contain;">
                        <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="<?= htmlspecialchars($cat['icone']) ?> fs-4 text-primary"></i>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="fw-bold"><?= htmlspecialchars($cat['nom']) ?></td>
                    <td><small class="text-muted"><?= mb_strimwidth($cat['description'], 0, 60, '...') ?></small></td>
                    <td class="text-center"><span class="badge bg-primary"><?= $cat['nb_produits'] ?></span></td>
                    <td><?= $cat['ordre'] ?></td>
                    <td><?= $cat['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="modifierCat(<?= htmlspecialchars(json_encode($cat)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajouter -->
<div class="modal fade" id="modalAjouter" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="modal-header"><h5 class="modal-title">Nouvelle catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Icône / Image (PNG, SVG)</label>
                        <input type="file" name="image" class="form-control" accept="image/png,image/svg+xml,image/jpeg,image/gif,image/webp">
                        <small class="text-muted">Téléchargez une icône PNG ou SVG pour cette catégorie. Format recommandé: 64x64px</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icône de secours <small class="text-muted">(si pas d'image)</small></label>
                        <input type="text" name="icone" class="form-control" placeholder="bi-printer" value="bi-printer">
                        <small class="text-muted">Classe Bootstrap Icons ex: bi-printer, bi-image</small>
                    </div>
                    <div class="mb-3"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="0"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="ajouter_categorie" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal fade" id="modalModifier" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="cat_id" id="mod_id">
                <div class="modal-header"><h5 class="modal-title">Modifier la catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" id="mod_nom" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="mod_desc" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Icône / Image actuelle</label>
                        <div id="mod_image_preview" class="mb-2"></div>
                        <label class="form-label">Changer l'icône / image</label>
                        <input type="file" name="image" class="form-control" accept="image/png,image/svg+xml,image/jpeg,image/gif,image/webp">
                        <small class="text-muted">PNG ou SVG recommandé. Format: 64x64px</small>
                        <div id="mod_delete_image_wrap" class="form-check mt-2" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="supprimer_image" id="mod_supprimer_image" value="1">
                            <label class="form-check-label text-danger" for="mod_supprimer_image">Supprimer l'image</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icône de secours <small class="text-muted">(si pas d'image)</small></label>
                        <input type="text" name="icone" id="mod_icone" class="form-control">
                        <small class="text-muted">Classe Bootstrap Icons ex: bi-printer</small>
                    </div>
                    <div class="mb-3"><label class="form-label">Ordre</label><input type="number" name="ordre" id="mod_ordre" class="form-control"></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="actif" id="mod_actif"><label class="form-check-label" for="mod_actif">Actif</label></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="modifier_categorie" class="btn btn-primary">Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function modifierCat(cat) {
    document.getElementById('mod_id').value = cat.id;
    document.getElementById('mod_nom').value = cat.nom;
    document.getElementById('mod_desc').value = cat.description || '';
    document.getElementById('mod_icone').value = cat.icone;
    document.getElementById('mod_ordre').value = cat.ordre;
    document.getElementById('mod_actif').checked = cat.actif == 1;
    document.getElementById('mod_supprimer_image').checked = false;

    // Show image preview
    const preview = document.getElementById('mod_image_preview');
    const delWrap = document.getElementById('mod_delete_image_wrap');
    if (cat.image) {
        preview.innerHTML = '<img src="../uploads/categories/' + cat.image + '" class="rounded border" style="max-width:150px;max-height:100px;object-fit:cover;">';
        delWrap.style.display = 'block';
    } else {
        preview.innerHTML = '<span class="text-muted small">Aucune image</span>';
        delWrap.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('modalModifier')).show();
}
</script>
