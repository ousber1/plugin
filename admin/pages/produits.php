<?php
/**
 * BERRADI PRINT - Gestion Produits/Services
 */
$db = getDB();

// Suppression
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && $id) {
    $db->prepare("UPDATE produits SET actif = 0 WHERE id = ?")->execute([$id]);
    setFlash('success', 'Produit désactivé.');
    redirect('index.php?page=produits');
}

if (isset($_GET['action']) && $_GET['action'] === 'activer' && $id) {
    $db->prepare("UPDATE produits SET actif = 1 WHERE id = ?")->execute([$id]);
    setFlash('success', 'Produit activé.');
    redirect('index.php?page=produits');
}

// Toggle populaire
if (isset($_GET['action']) && $_GET['action'] === 'populaire' && $id) {
    $db->prepare("UPDATE produits SET populaire = NOT populaire WHERE id = ?")->execute([$id]);
    setFlash('success', 'Statut mis à jour.');
    redirect('index.php?page=produits');
}

$cat_filtre = (int)($_GET['cat'] ?? 0);
$sql = "SELECT p.*, c.nom as categorie_nom FROM produits p JOIN categories c ON p.categorie_id = c.id WHERE 1=1";
$params = [];
if ($cat_filtre) { $sql .= " AND p.categorie_id = ?"; $params[] = $cat_filtre; }
$sql .= " ORDER BY c.ordre, p.ordre";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY ordre")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-box me-2"></i>Produits / Services (<?= count($produits) ?>)</h4>
    <a href="index.php?page=produit_edit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nouveau produit</a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php?page=produits" class="btn btn-sm <?= !$cat_filtre ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
            <?php foreach ($categories as $cat): ?>
            <a href="index.php?page=produits&cat=<?= $cat['id'] ?>" class="btn btn-sm <?= $cat_filtre == $cat['id'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $cat['nom'] ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr><th>Produit</th><th>Catégorie</th><th class="text-end">Prix</th><th>Unité</th><th>Délai</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                    <tr class="<?= !$p['actif'] ? 'opacity-50' : '' ?>">
                        <td>
                            <div class="fw-bold"><?= $p['nom'] ?></div>
                            <small class="text-muted"><?= $p['description_courte'] ?></small>
                        </td>
                        <td><span class="badge bg-primary-soft text-primary"><?= $p['categorie_nom'] ?></span></td>
                        <td class="text-end fw-bold"><?= formatPrix($p['prix_base']) ?></td>
                        <td><small><?= $p['unite'] ?></small></td>
                        <td><small><?= $p['delai_production'] ?></small></td>
                        <td>
                            <?php if ($p['actif']): ?><span class="badge bg-success">Actif</span><?php else: ?><span class="badge bg-secondary">Inactif</span><?php endif; ?>
                            <?php if ($p['populaire']): ?><span class="badge bg-warning text-dark ms-1">Pop.</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=produit_edit&id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
                                <a href="index.php?page=produits&action=populaire&id=<?= $p['id'] ?>" class="btn btn-outline-warning" title="Populaire"><i class="bi bi-star<?= $p['populaire'] ? '-fill' : '' ?>"></i></a>
                                <?php if ($p['actif']): ?>
                                <a href="index.php?page=produits&action=supprimer&id=<?= $p['id'] ?>" class="btn btn-outline-danger" title="Désactiver" onclick="return confirm('Désactiver ce produit ?')"><i class="bi bi-x-circle"></i></a>
                                <?php else: ?>
                                <a href="index.php?page=produits&action=activer&id=<?= $p['id'] ?>" class="btn btn-outline-success" title="Activer"><i class="bi bi-check-circle"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
