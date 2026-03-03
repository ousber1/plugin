<?php
/**
 * BERRADI PRINT - Édition Page
 */
$db = getDB();

if (!isset($_GET['id'])) {
    redirect('index.php?page=pages');
}

$page_id = (int)$_GET['id'];
$stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$page_id]);
$page = $stmt->fetch();

// For TinyMCE (will be included in admin header)
$use_wysiwyg = true;

if (!$page) {
    setFlash('error', 'Page non trouvée.');
    redirect('index.php?page=pages');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['modifier_page'])) {
        $nom = clean($_POST['nom']);
        $slug = trim($_POST['slug']);
        if (!$slug) $slug = preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($nom)));
        $template = clean($_POST['template'] ?? 'default');
        $body = $_POST['body'] ?? '';
        $meta_title = clean($_POST['meta_title'] ?? '');
        $meta_description = clean($_POST['meta_description'] ?? '');
        $visible = isset($_POST['visible']) ? 1 : 0;
        $show_header_footer = isset($_POST['show_header_footer']) ? 1 : 0;

        // Check slug uniqueness (excluding current page)
        $stmt = $db->prepare("SELECT COUNT(*) FROM pages WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $page_id]);
        if ($stmt->fetchColumn() > 0) {
            setFlash('error', 'Le slug est déjà utilisé.');
            redirect('index.php?page=page_edit&id=' . $page_id);
        }

        $meta_image = $page['meta_image'];

        // Delete old meta_image if requested
        if (isset($_POST['delete_meta_image'])) {
            if ($meta_image && file_exists(__DIR__ . '/../../uploads/pages/' . $meta_image)) {
                unlink(__DIR__ . '/../../uploads/pages/' . $meta_image);
            }
            $meta_image = '';
        }

        // Upload new meta image
        if (!empty($_FILES['meta_image']['name']) && $_FILES['meta_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['meta_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/pages/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                // Delete old image if exists
                if ($meta_image && file_exists($upload_dir . $meta_image)) {
                    unlink($upload_dir . $meta_image);
                }
                
                $meta_image = uniqid('page_img_') . '.' . $ext;
                move_uploaded_file($_FILES['meta_image']['tmp_name'], $upload_dir . $meta_image);
            }
        }

        $stmt = $db->prepare("UPDATE pages SET nom=?, slug=?, template=?, body=?, meta_title=?, meta_description=?, meta_image=?, visible=?, show_header_footer=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$nom, $slug, $template, $body, $meta_title, $meta_description, $meta_image, $visible, $show_header_footer, $page_id]);
        setFlash('success', 'Page mise à jour avec succès.');
        redirect('index.php?page=page_edit&id=' . $page_id);
    }
}

$meta_image_preview = '';
if ($page['meta_image']) {
    $meta_img_path = __DIR__ . '/../../uploads/pages/' . $page['meta_image'];
    if (file_exists($meta_img_path)) {
        $meta_image_preview = '../uploads/pages/' . $page['meta_image'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil me-2"></i>Éditer : <?= htmlspecialchars($page['nom']) ?></h4>
    <a class="btn btn-outline-secondary" href="index.php?page=pages"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Contenu -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Contenu</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($page['nom']) ?>" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenu (WYSIWYG Editor)</label>
                        <textarea id="page_body_editor" name="body" class="form-control" rows="12" style="font-family:monospace;"><?= htmlspecialchars($page['body']) ?></textarea>
                        <small class="text-muted">Utilisez l'éditeur visuel pour formater votre contenu.</small>
                    </div>
                </div>
            </div>

            <!-- SEO Metadata -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-search me-2"></i>SEO & Meta</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control" value="<?= htmlspecialchars($page['meta_title'] ?? '') ?>" placeholder="Titre pour les moteurs de recherche" />
                        <small class="text-muted">Idéalement 50-60 caractères</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2" placeholder="Description pour les moteurs de recherche"><?= htmlspecialchars($page['meta_description'] ?? '') ?></textarea>
                        <small class="text-muted">Idéalement 150-160 caractères</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Image (partage réseaux)</label>
                        <?php if ($meta_image_preview): ?>
                        <div class="mb-2">
                            <img src="<?= $meta_image_preview ?>" alt="Meta image" class="rounded" style="max-height:120px;object-fit:contain;">
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="delete_meta_image" id="delete_meta" value="1" />
                            <label class="form-check-label text-danger" for="delete_meta">Supprimer cette image</label>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="meta_image" class="form-control" accept="image/*" />
                        <small class="text-muted">JPG, PNG, WebP, GIF, SVG. Taille recommandée: 1200x630px</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Settings -->
        <div class="col-lg-4">
            <!-- Page Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-sliders me-2"></i>Paramètres</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Slug (URL)</label>
                        <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($page['slug']) ?>" placeholder="Laissez vide pour générer automatiquement" />
                        <small class="text-muted">Utilisé dans l'URL: site.com/pages.php?slug=<strong><?= htmlspecialchars($page['slug']) ?></strong></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select name="template" class="form-select">
                            <option value="default" <?= $page['template'] === 'default' ? 'selected' : '' ?>>default</option>
                            <option value="landing" <?= $page['template'] === 'landing' ? 'selected' : '' ?>>landing (sans header/footer)</option>
                        </select>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="visible" id="page_visible" value="1" <?= $page['visible'] ? 'checked' : '' ?> />
                        <label class="form-check-label" for="page_visible">Page visible</label>
                        <small class="d-block text-muted mt-1">Activer pour que la page soit accessible en ligne</small>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="show_header_footer" id="page_hf" value="1" <?= $page['show_header_footer'] ? 'checked' : '' ?> />
                        <label class="form-check-label" for="page_hf">Afficher header & footer</label>
                        <small class="d-block text-muted mt-1">Désactiver pour une page landing ou personalisée</small>
                    </div>

                    <hr />

                    <div class="mb-3">
                        <strong class="d-block mb-2">Créée:</strong>
                        <small class="text-muted"><?= date('Y-m-d H:i', strtotime($page['created_at'])) ?></small>
                    </div>
                    <div class="mb-3">
                        <strong class="d-block mb-2">Mise à jour:</strong>
                        <small class="text-muted"><?= date('Y-m-d H:i', strtotime($page['updated_at'])) ?></small>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" name="modifier_page" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle me-1"></i>Sauvegarder
                    </button>
                    <a href="../pages.php?slug=<?= urlencode($page['slug']) ?>" target="_blank" class="btn btn-outline-secondary w-100" title="Voir la page en ligne">
                        <i class="bi bi-eye me-1"></i>Aperçu
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#page_body_editor',
    height: 500,
    plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount checklist',
    toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | link image media table | align numlist bullist | checklist emoticons charmap | codesample | removeformat',
    menubar: 'file edit view insert format tools table',
    image_caption: true,
    image_advtab: true,
    link_context_toolbar: true,
    content_style: 'body { font-family: Poppins, sans-serif; font-size: 14px; } h1 { font-size: 2rem; font-weight: 600; } h2 { font-size: 1.5rem; font-weight: 600; } h3 { font-size: 1.25rem; font-weight: 600; } table { border-collapse: collapse; } table th, table td { border: 1px solid #ddd; padding: 8px; }',
    promotion: false,
    relative_urls: false,
    convert_urls: true,
    paste_as_text: false,
    branding: false
});
</script>
