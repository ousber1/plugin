<?php
/**
 * BERRADI PRINT - Gestion Pages (création simple)
 */
$db = getDB();

// Ensure pages table exists
$db->exec("CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    template VARCHAR(100) DEFAULT 'default',
    body LONGTEXT,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description TEXT DEFAULT NULL,
    meta_image VARCHAR(255) DEFAULT NULL,
    visible TINYINT(1) DEFAULT 1,
    show_header_footer TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['ajouter_page'])) {
        $nom = clean($_POST['nom']);
        $slug = trim($_POST['slug']) ?: preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($nom)));
        if (!$slug) $slug = 'page-' . time();
        $template = clean($_POST['template'] ?? 'default');
        $body = $_POST['body'] ?? '';
        $meta_title = clean($_POST['meta_title'] ?? '');
        $meta_description = clean($_POST['meta_description'] ?? '');
        $visible = isset($_POST['visible']) ? 1 : 0;
        $show_header_footer = isset($_POST['show_header_footer']) ? 1 : 0;

        // Ensure unique slug
        $base = $slug; $i = 1;
        while (true) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM pages WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn() == 0) break;
            $slug = $base . '-' . $i; $i++;
        }

        // Meta image upload
        $meta_image = '';
        if (!empty($_FILES['meta_image']['name']) && $_FILES['meta_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['meta_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/pages/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $meta_image = uniqid('page_img_') . '.' . $ext;
                move_uploaded_file($_FILES['meta_image']['tmp_name'], $upload_dir . $meta_image);
            }
        }

        $stmt = $db->prepare("INSERT INTO pages (nom, slug, template, body, meta_title, meta_description, meta_image, visible, show_header_footer) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$nom, $slug, $template, $body, $meta_title, $meta_description, $meta_image, $visible, $show_header_footer]);
        setFlash('success', 'Page créée avec succès.');
        redirect('index.php?page=pages');
    }

    if (isset($_POST['delete_page'])) {
        $delete_id = (int)$_POST['delete_id'];
        
        // Get page to find meta_image
        $stmt = $db->prepare("SELECT meta_image FROM pages WHERE id = ?");
        $stmt->execute([$delete_id]);
        $del_page = $stmt->fetch();
        
        if ($del_page) {
            // Delete meta image file if exists
            if ($del_page['meta_image']) {
                $img_path = __DIR__ . '/../../uploads/pages/' . $del_page['meta_image'];
                if (file_exists($img_path)) {
                    unlink($img_path);
                }
            }
            
            // Delete page from database
            $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$delete_id]);
            setFlash('success', 'Page supprimée.');
        }
        
        redirect('index.php?page=pages');
    }
}

$pages = $db->query("SELECT * FROM pages ORDER BY created_at DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Pages (<?= count($pages) ?>)</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterPage">
        <i class="bi bi-plus-circle me-1"></i>Ajouter une page
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light"><tr><th>Nom</th><th>Slug</th><th>Template</th><th>Visibilité</th><th>Créée</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($p['nom']) ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($p['slug']) ?></small></td>
                    <td><?= htmlspecialchars($p['template']) ?></td>
                    <td><?= $p['visible'] ? '<span class="badge bg-success">Visible</span>' : '<span class="badge bg-secondary">Cachée</span>' ?></td>
                    <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="../pages.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" title="Voir"><i class="bi bi-eye"></i></a>
                        <a class="btn btn-sm btn-outline-secondary" href="index.php?page=page_edit&id=<?= $p['id'] ?>" title="Éditer"><i class="bi bi-pencil"></i></a>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Êtes-vous sûr ? Cette action est irréversible.');">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" name="delete_page" title="Supprimer"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajouter Page -->
<div class="modal fade" id="modalAjouterPage" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="modal-header"><h5 class="modal-title">Nouvelle page</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Slug (optionnel)</label><input type="text" name="slug" class="form-control" placeholder="laisser vide pour générer automatiquement"></div>
                        <div class="col-md-6"><label class="form-label">Template</label><select name="template" class="form-select"><option value="default">default</option></select></div>
                        <div class="col-12"><label class="form-label">Contenu (HTML)</label><textarea name="body" class="form-control" rows="6"></textarea></div>
                        <div class="col-md-6"><label class="form-label">Meta title</label><input type="text" name="meta_title" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Meta description</label><input type="text" name="meta_description" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Meta image</label><input type="file" name="meta_image" class="form-control" accept="image/*"></div>
                        <div class="col-6 form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="visible" id="visible" checked><label class="form-check-label" for="visible">Afficher la page</label></div>
                        <div class="col-6 form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="show_header_footer" id="show_header_footer" checked><label class="form-check-label" for="show_header_footer">Afficher header & footer</label></div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary" name="ajouter_page">Créer</button></div>
            </form>
        </div>
    </div>
</div>
