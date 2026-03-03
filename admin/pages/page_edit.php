<?php
/**
 * BERRADI PRINT - Ajout/Modification Page avec TinyMCE
 */
$db = getDB();
$page_id = (int)($_GET['id'] ?? 0);
$pg = null;

if ($page_id) {
    $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $pg = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder'])) {
    verifyCsrf();

    $titre = clean($_POST['titre']);
    $slug = $_POST['slug'] ? preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower(clean($_POST['slug'])))) : preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($titre)));
    $contenu = $_POST['contenu'] ?? '';
    $meta_title = clean($_POST['meta_title'] ?? '');
    $meta_description = clean($_POST['meta_description'] ?? '');
    $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
    $actif = isset($_POST['actif']) ? 1 : 0;
    $ordre = (int)($_POST['ordre'] ?? 0);

    if ($page_id && $pg) {
        $sql = "UPDATE pages SET titre=?, slug=?, contenu=?, meta_title=?, meta_description=?, show_in_menu=?, actif=?, ordre=? WHERE id=?";
        $db->prepare($sql)->execute([$titre, $slug, $contenu, $meta_title, $meta_description, $show_in_menu, $actif, $ordre, $page_id]);
        setFlash('success', 'Page mise à jour avec succès.');
    } else {
        $sql = "INSERT INTO pages (titre, slug, contenu, meta_title, meta_description, show_in_menu, actif, ordre) VALUES (?,?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute([$titre, $slug, $contenu, $meta_title, $meta_description, $show_in_menu, $actif, $ordre]);
        $page_id = $db->lastInsertId();
        setFlash('success', 'Page créée avec succès.');
    }
    redirect('index.php?page=page_edit&id=' . $page_id);
}
?>

<div class="mb-4">
    <a href="index.php?page=pages" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour aux pages</a>
    <h4 class="fw-bold"><?= $pg ? 'Modifier' : 'Nouvelle' ?> Page</h4>
</div>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-file-text me-2"></i>Contenu</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Titre de la page *</label>
                        <input type="text" name="titre" class="form-control" required value="<?= htmlspecialchars($pg['titre'] ?? '') ?>" id="titreInput">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug (URL)</label>
                        <div class="input-group">
                            <span class="input-group-text">index.php?page=</span>
                            <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($pg['slug'] ?? '') ?>" id="slugInput" placeholder="auto-généré depuis le titre">
                        </div>
                        <small class="text-muted">Laissez vide pour générer automatiquement depuis le titre.</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Contenu de la page</label>
                        <textarea name="contenu" id="contenu" class="form-control" rows="15"><?= htmlspecialchars($pg['contenu'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-search me-2"></i>SEO</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control" maxlength="70" value="<?= htmlspecialchars($pg['meta_title'] ?? '') ?>" placeholder="Titre pour les moteurs de recherche">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2" maxlength="160" placeholder="Description pour les moteurs de recherche"><?= htmlspecialchars($pg['meta_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold">Options</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= (!$pg || $pg['actif']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="actif">Page active</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="show_in_menu" id="show_in_menu" <?= ($pg && $pg['show_in_menu']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_in_menu">Afficher dans le menu</label>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Ordre d'affichage</label>
                        <input type="number" name="ordre" class="form-control" value="<?= $pg['ordre'] ?? 0 ?>">
                    </div>
                </div>
            </div>
            <button type="submit" name="sauvegarder" class="btn btn-primary w-100 btn-lg mb-3">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder
            </button>
            <?php if ($pg): ?>
            <a href="../index.php?page=<?= $pg['slug'] ?>" target="_blank" class="btn btn-outline-primary w-100">
                <i class="bi bi-eye me-2"></i>Voir sur le site
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#contenu',
    language: 'fr_FR',
    height: 500,
    menubar: true,
    plugins: 'lists link image table code wordcount fullscreen media hr',
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link image media table | hr | fullscreen code',
    content_style: 'body { font-family: Poppins, sans-serif; font-size: 14px; }',
    branding: false,
    promotion: false
});

// Auto-generate slug from title
document.getElementById('titreInput').addEventListener('input', function() {
    const slug = document.getElementById('slugInput');
    if (!slug.value || slug.dataset.auto === '1') {
        slug.value = this.value.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        slug.dataset.auto = '1';
    }
});
document.getElementById('slugInput').addEventListener('input', function() {
    this.dataset.auto = '0';
});
</script>
