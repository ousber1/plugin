<?php
/**
 * BERRADI PRINT - Gestion des Templates de pages
 */
$db = getDB();

// ensure table exists (should already be created earlier)
$db->exec("CREATE TABLE IF NOT EXISTS page_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    contenu LONGTEXT,
    ordre INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['ajouter_template'])) {
        $nom = clean($_POST['nom']);
        $description = clean($_POST['description']);
        $ordre = (int)$_POST['ordre'];
        $contenu = $_POST['contenu'] ?? '';
        $stmt = $db->prepare("INSERT INTO page_templates (nom, description, ordre, contenu) VALUES (?,?,?,?)");
        try {
            $stmt->execute([$nom, $description, $ordre, $contenu]);
            setFlash('success', 'Template ajouté.');
        } catch (PDOException $e) {
            setFlash('error', 'Impossible d\'ajouter le template : ' . $e->getMessage());
        }
        redirect('index.php?page=page_templates');
    }

    if (isset($_POST['modifier_template'])) {
        $id = (int)$_POST['id'];
        $nom = clean($_POST['nom']);
        $description = clean($_POST['description']);
        $ordre = (int)$_POST['ordre'];
        $contenu = $_POST['contenu'] ?? '';
        $stmt = $db->prepare("UPDATE page_templates SET nom=?, description=?, ordre=?, contenu=? WHERE id=?");
        $stmt->execute([$nom, $description, $ordre, $contenu, $id]);
        setFlash('success', 'Template mis à jour.');
        redirect('index.php?page=page_templates');
    }

    if (isset($_POST['delete_template'])) {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM page_templates WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Template supprimé.');
        redirect('index.php?page=page_templates');
    }
}

$templates = $db->query("SELECT * FROM page_templates ORDER BY ordre")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-layout-text-window me-2"></i>Gestion des Templates</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterTemplate">
        <i class="bi bi-plus-circle me-1"></i>Nouveau template
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light"><tr><th>Nom</th><th>Description</th><th>Ordre</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['nom']) ?></td>
                    <td><?= htmlspecialchars($t['description']) ?></td>
                    <td><?= $t['ordre'] ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" onclick='editTemplate(<?= json_encode($t) ?>)'><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Supprimer ce template ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button name="delete_template" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajouter/Modifier -->
<div class="modal fade" id="modalAjouterTemplate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="id" id="temp_id">
                <div class="modal-header"><h5 class="modal-title" id="temp_modal_title">Nouveau template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" id="temp_nom" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><input type="text" name="description" id="temp_desc" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Ordre</label><input type="number" name="ordre" id="temp_ordre" class="form-control" value="0"></div>
                    <div class="mb-3"><label class="form-label">Contenu HTML</label><textarea name="contenu" id="temp_contenu" class="form-control" rows="8"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="ajouter_template" id="temp_submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTemplate(t) {
    document.getElementById('temp_modal_title').textContent = 'Modifier le template';
    document.getElementById('temp_submit').name = 'modifier_template';
    document.getElementById('temp_id').value = t.id;
    document.getElementById('temp_nom').value = t.nom;
    document.getElementById('temp_desc').value = t.description;
    document.getElementById('temp_ordre').value = t.ordre;
    document.getElementById('temp_contenu').value = t.contenu;
    var modal = new bootstrap.Modal(document.getElementById('modalAjouterTemplate'));
    modal.show();
}
</script>