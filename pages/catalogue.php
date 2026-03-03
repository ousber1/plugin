<?php
/**
 * BERRADI PRINT - Catalogue / Services
 */
$db = getDB();
$cat_slug = isset($_GET['cat']) ? clean($_GET['cat']) : '';
$search = isset($_GET['q']) ? clean($_GET['q']) : '';

$categorie_active = null;
if ($cat_slug) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND actif = 1");
    $stmt->execute([$cat_slug]);
    $categorie_active = $stmt->fetch();
}

$categories = $db->query("SELECT c.*, (SELECT COUNT(*) FROM produits WHERE categorie_id = c.id AND actif = 1) as nb_produits FROM categories c WHERE c.actif = 1 ORDER BY c.ordre")->fetchAll();

// Construire la requête produits
$sql = "SELECT p.*, c.nom as categorie_nom, c.slug as categorie_slug FROM produits p JOIN categories c ON p.categorie_id = c.id WHERE p.actif = 1";
$params = [];

if ($categorie_active) {
    $sql .= " AND p.categorie_id = ?";
    $params[] = $categorie_active['id'];
}

if ($search) {
    $sql .= " AND (p.nom LIKE ? OR p.description LIKE ? OR p.description_courte LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.ordre, p.nom";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();
?>

<!-- Bannière -->
<section class="bg-primary py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="text-white fw-bold mb-1">
                    <?= $categorie_active ? $categorie_active['nom'] : 'Nos Services d\'Impression' ?>
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php" class="text-white-50">Accueil</a></li>
                        <?php if ($categorie_active): ?>
                            <li class="breadcrumb-item"><a href="index.php?page=catalogue" class="text-white-50">Catalogue</a></li>
                            <li class="breadcrumb-item text-white"><?= $categorie_active['nom'] ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item text-white">Catalogue</li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
            <div class="col-md-4">
                <form method="GET" class="mt-3 mt-md-0">
                    <input type="hidden" name="page" value="catalogue">
                    <?php if ($cat_slug): ?><input type="hidden" name="cat" value="<?= $cat_slug ?>"><?php endif; ?>
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Rechercher un service..." value="<?= $search ?>">
                        <button class="btn btn-warning" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Sidebar Catégories -->
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="bi bi-list me-2"></i>Catégories
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="index.php?page=catalogue"
                           class="list-group-item list-group-item-action <?= !$categorie_active ? 'active' : '' ?>">
                            <i class="bi bi-grid me-2"></i>Tous les services
                        </a>
                        <?php foreach ($categories as $cat):
                            $cat_icon = '';
                            if (!empty($cat['image']) && file_exists(__DIR__ . '/../uploads/categories/' . $cat['image'])) {
                                $cat_icon = 'uploads/categories/' . $cat['image'];
                            }
                        ?>
                        <a href="index.php?page=catalogue&cat=<?= $cat['slug'] ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($categorie_active && $categorie_active['id'] == $cat['id']) ? 'active' : '' ?>">
                            <span>
                                <?php if ($cat_icon): ?>
                                <img src="<?= $cat_icon ?>" alt="" style="width:20px;height:20px;object-fit:contain;" class="me-2">
                                <?php else: ?>
                                <i class="<?= $cat['icone'] ?> me-2"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($cat['nom']) ?>
                            </span>
                            <span class="badge bg-primary rounded-pill"><?= $cat['nb_produits'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Grille Produits -->
            <div class="col-lg-9">
                <?php if ($categorie_active && $categorie_active['description']): ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i><?= $categorie_active['description'] ?>
                </div>
                <?php endif; ?>

                <?php if (empty($produits)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-search fs-1 text-muted"></i>
                    <h4 class="mt-3">Aucun produit trouvé</h4>
                    <p class="text-muted">Essayez de modifier votre recherche ou explorez nos catégories</p>
                    <a href="index.php?page=catalogue" class="btn btn-primary">Voir tout le catalogue</a>
                </div>
                <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($produits as $prod):
                        $prod_img = '';
                        if (!empty($prod['image']) && file_exists(__DIR__ . '/../uploads/produits/' . $prod['image'])) {
                            $prod_img = 'uploads/produits/' . $prod['image'];
                        }
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100 product-card">
                            <div class="position-relative">
                                <?php if ($prod_img): ?>
                                <img src="<?= $prod_img ?>" alt="<?= htmlspecialchars($prod['nom']) ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                                <?php else: ?>
                                <div class="card-img-top bg-primary-soft d-flex align-items-center justify-content-center" style="height: 180px;">
                                    <i class="bi bi-printer fs-1 text-primary"></i>
                                </div>
                                <?php endif; ?>
                                <?php if ($prod['populaire']): ?>
                                <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
                                    <i class="bi bi-star-fill me-1"></i>Populaire
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <span class="badge bg-primary-soft text-primary small mb-2"><?= $prod['categorie_nom'] ?></span>
                                <h6 class="fw-bold mb-1"><?= $prod['nom'] ?></h6>
                                <p class="text-muted small mb-2"><?= $prod['description_courte'] ?></p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-primary"><?= formatPrix($prod['prix_base']) ?></span>
                                    <small class="text-muted">/ <?= $prod['unite'] ?></small>
                                </div>
                                <div class="d-flex gap-2 text-muted small">
                                    <span><i class="bi bi-clock me-1"></i><?= $prod['delai_production'] ?></span>
                                    <span><i class="bi bi-box me-1"></i>Min: <?= $prod['quantite_min'] ?></span>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 pt-0">
                                <div class="d-grid">
                                    <a href="index.php?page=produit&id=<?= $prod['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-eye me-1"></i>Voir & Commander
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
