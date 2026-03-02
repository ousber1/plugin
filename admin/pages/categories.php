<?php
/**
 * BERRADI PRINT - Gestion Catégories
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
        $db->prepare("INSERT INTO categories (nom, slug, description, icone, ordre) VALUES (?,?,?,?,?)")->execute([$nom, $slug, $desc, $icone, $ordre]);
        setFlash('success', 'Catégorie ajoutée.');
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
        $db->prepare("UPDATE categories SET nom=?, slug=?, description=?, icone=?, ordre=?, actif=? WHERE id=?")->execute([$nom, $slug, $desc, $icone, $ordre, $actif, $cat_id]);
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
                <?php foreach ($categories as $cat): ?>
                <tr class="<?= !$cat['actif'] ? 'opacity-50' : '' ?>">
                    <td><i class="<?= $cat['icone'] ?> fs-4 text-primary"></i></td>
                    <td class="fw-bold"><?= $cat['nom'] ?></td>
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
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-header"><h5 class="modal-title">Nouvelle catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Icône Bootstrap</label><input type="text" name="icone" class="form-control" placeholder="bi-printer" value="bi-printer"></div>
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
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="cat_id" id="mod_id">
                <div class="modal-header"><h5 class="modal-title">Modifier la catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" id="mod_nom" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="mod_desc" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Icône Bootstrap</label><input type="text" name="icone" id="mod_icone" class="form-control"></div>
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
    new bootstrap.Modal(document.getElementById('modalModifier')).show();
}
</script>
