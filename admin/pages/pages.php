<?php
/**
 * BERRADI PRINT - Gestion Pages (création simple)
 */
$db = getDB();

// Ensure tables exist
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

$db->exec("CREATE TABLE IF NOT EXISTS page_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    contenu LONGTEXT,
    ordre INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

// Insert default templates if not exists
$templates_count = $db->query("SELECT COUNT(*) FROM page_templates")->fetchColumn();
if ($templates_count == 0) {
    $default_templates = [
        ['landing', 'Page d\'accueil / Landing page', 1, '<section class="text-center py-5"><h1 class="display-4 fw-bold mb-4">Bienvenue</h1><p class="lead mb-4">Ceci est une page landing. Personnalisez-la selon vos besoins.</p><a href="#" class="btn btn-primary btn-lg">En savoir plus</a></section>'],
        ['contact', 'Formulaire de contact', 2, '<section class="py-5"><h2 class="mb-4">Nous contacter</h2><p class="mb-4">Remplissez le formulaire ci-dessous pour nous envoyer un message.</p><form><div class="mb-3"><input type="text" class="form-control" placeholder="Votre nom" required></div><div class="mb-3"><input type="email" class="form-control" placeholder="Votre email" required></div><div class="mb-3"><textarea class="form-control" rows="5" placeholder="Votre message" required></textarea></div><button class="btn btn-primary" type="submit">Envoyer</button></form></section>'],
        ['about', 'À propos de nous', 3, '<section class="py-5"><h2 class="mb-4">À propos de nous</h2><p>Qui sommes-nous ? Racontez votre histoire en quelques paragraphes.</p><h3 class="mt-4 mb-3">Notre mission</h3><p>Décrivez votre mission ici.</p><h3 class="mt-4 mb-3">Notre vision</h3><p>Décrivez votre vision ici.</p><h3 class="mt-4 mb-3">Nos valeurs</h3><ul><li>Valeur 1</li><li>Valeur 2</li><li>Valeur 3</li></ul></section>'],
        ['services', 'Nos services', 4, '<section class="py-5"><h2 class="mb-4">Nos services</h2><div class="row g-4"><div class="col-md-6"><h3>Service 1</h3><p>Description du service 1</p></div><div class="col-md-6"><h3>Service 2</h3><p>Description du service 2</p></div><div class="col-md-6"><h3>Service 3</h3><p>Description du service 3</p></div><div class="col-md-6"><h3>Service 4</h3><p>Description du service 4</p></div></div></section>'],
        ['faq', 'Questions Fréquemment Posées', 5, '<section class="py-5"><h2 class="mb-4">Questions Fréquemment Posées</h2><div class="accordion"><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">Question 1 ?</button></h2><div id="faq1" class="accordion-collapse collapse show" data-bs-parent=".accordion"><div class="accordion-body">Réponse à la question 1...</div></div></div><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">Question 2 ?</button></h2><div id="faq2" class="accordion-collapse collapse" data-bs-parent=".accordion"><div class="accordion-body">Réponse à la question 2...</div></div></div></div></section>']
    ];
    $stmt = $db->prepare("INSERT INTO page_templates (nom, description, ordre, contenu) VALUES (?,?,?,?)");
    foreach ($default_templates as $t) {
        $stmt->execute($t);
    }
}

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
$templates = $db->query("SELECT * FROM page_templates ORDER BY ordre")->fetchAll();
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
                        <div class="col-md-6"><label class="form-label">Template de départ</label><select id="template_select" name="template" class="form-select"><option value="">-- Vierge --</option><?php foreach ($templates as $t): ?><option value="<?= htmlspecialchars($t['nom']) ?>" data-content="<?= htmlspecialchars($t['contenu']) ?>"><?= htmlspecialchars($t['description'] ?: $t['nom']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-12" id="template_preview_wrap" style="display:none;"><div class="alert alert-info"><small><strong>Aperçu du template :</strong></small><div id="preview_content" style="font-size:0.85rem;max-height:120px;overflow-y:auto;"></div><button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="applyTemplate()">Appliquer ce template</button></div></div>
                        <div class="col-12"><label class="form-label">Contenu (WYSIWYG)</label><textarea id="modal_body_editor" name="body" class="form-control" rows="6"></textarea></div>
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

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
<script>
// Initialize TinyMCE for create modal
var initTinyMCE = function(selector) {
    tinymce.init({
        selector: selector,
        height: 300,
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount checklist',
        toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | link image media table | align numlist bullist | checklist emoticons charmap | codesample | removeformat',
        menubar: false,
        image_caption: true,
        link_context_toolbar: true,
        content_style: 'body { font-family: Poppins, sans-serif; font-size: 14px; }',
        promotion: false,
        relative_urls: false,
        convert_urls: true,
        branding: false
    });
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initTinyMCE('#modal_body_editor');
});

// Reinitialize when modal opens (in case it wasn't rendered initially)
document.getElementById('modalAjouterPage').addEventListener('show.bs.modal', function() {
    if (!tinymce.get('modal_body_editor')) {
        initTinyMCE('#modal_body_editor');
    }
});

// Template management
var templateData = {};
<?php foreach ($templates as $t): ?>
templateData['<?= $t['nom'] ?>'] = <?= json_encode($t['contenu']) ?>;
<?php endforeach; ?>

document.getElementById('template_select').addEventListener('change', function() {
    var selected = this.value;
    var preview = document.getElementById('template_preview_wrap');
    var content = document.getElementById('preview_content');
    
    if (selected && templateData[selected]) {
        content.innerHTML = templateData[selected];
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

function applyTemplate() {
    var selected = document.getElementById('template_select').value;
    if (selected && templateData[selected]) {
        tinymce.get('modal_body_editor').setContent(templateData[selected]);
        // Scroll to editor
        document.getElementById('modal_body_editor').scrollIntoView({ behavior: 'smooth' });
    }
}
</script>