<?php
/**
 * BERRADI PRINT - Gestion des Pages
 */
$db = getDB();

// Auto-create pages table if not exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        contenu TEXT,
        meta_title VARCHAR(255) DEFAULT NULL,
        meta_description TEXT DEFAULT NULL,
        show_in_menu TINYINT(1) DEFAULT 0,
        actif TINYINT(1) DEFAULT 1,
        ordre INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

// Suppression
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && $id) {
    $db->prepare("DELETE FROM pages WHERE id = ?")->execute([$id]);
    setFlash('success', 'Page supprimée.');
    redirect('index.php?page=pages');
}

// Toggle actif
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && $id) {
    $db->prepare("UPDATE pages SET actif = NOT actif WHERE id = ?")->execute([$id]);
    setFlash('success', 'Statut mis à jour.');
    redirect('index.php?page=pages');
}

$pages_list = $db->query("SELECT * FROM pages ORDER BY ordre, titre")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-richtext me-2"></i>Pages (<?= count($pages_list) ?>)</h4>
    <a href="index.php?page=page_edit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nouvelle page</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Titre</th>
                        <th>Slug</th>
                        <th class="text-center">Menu</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pages_list)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucune page créée. <a href="index.php?page=page_edit">Créer une page</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($pages_list as $pg): ?>
                    <tr class="<?= !$pg['actif'] ? 'opacity-50' : '' ?>">
                        <td class="fw-bold"><?= htmlspecialchars($pg['titre']) ?></td>
                        <td><code class="small"><?= htmlspecialchars($pg['slug']) ?></code></td>
                        <td class="text-center">
                            <?php if ($pg['show_in_menu']): ?>
                            <span class="badge bg-success">Oui</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Non</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $pg['ordre'] ?></td>
                        <td>
                            <?php if ($pg['actif']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= date('d/m/Y', strtotime($pg['created_at'])) ?></small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=page_edit&id=<?= $pg['id'] ?>" class="btn btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
                                <a href="../index.php?page=<?= $pg['slug'] ?>" class="btn btn-outline-info" title="Voir" target="_blank"><i class="bi bi-eye"></i></a>
                                <a href="index.php?page=pages&action=toggle&id=<?= $pg['id'] ?>" class="btn btn-outline-warning" title="Toggle actif"><i class="bi bi-toggle-<?= $pg['actif'] ? 'on' : 'off' ?>"></i></a>
                                <a href="index.php?page=pages&action=supprimer&id=<?= $pg['id'] ?>" class="btn btn-outline-danger" title="Supprimer" onclick="return confirm('Supprimer cette page ?')"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
