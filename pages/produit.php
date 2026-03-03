<?php
/**
 * BERRADI PRINT - Page Détail Produit (Professional)
 */
$db = getDB();
$produit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT p.*, c.nom as categorie_nom, c.slug as categorie_slug FROM produits p JOIN categories c ON p.categorie_id = c.id WHERE p.id = ? AND p.actif = 1");
$stmt->execute([$produit_id]);
$produit = $stmt->fetch();

if (!$produit) {
    echo '<div class="container py-5 text-center"><h3>Produit non trouvé</h3><a href="index.php?page=catalogue" class="btn btn-primary mt-3">Retour au catalogue</a></div>';
    return;
}

$stmt = $db->prepare("SELECT * FROM produit_options WHERE produit_id = ? ORDER BY ordre");
$stmt->execute([$produit_id]);
$options = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM produits WHERE categorie_id = ? AND id != ? AND actif = 1 ORDER BY populaire DESC, ordre LIMIT 4");
$stmt->execute([$produit['categorie_id'], $produit_id]);
$similaires = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_panier'])) {
    verifyCsrf();
    $quantite = max(1, (int)($_POST['quantite'] ?? 1));
    $opts = [];
    if (!empty($_POST['options']) && is_array($_POST['options'])) {
        foreach ($_POST['options'] as $nom => $val) {
            $opts[] = ['nom' => $nom, 'valeur' => $val, 'prix_supplement' => 0];
        }
    }
    ajouterAuPanier($produit_id, $quantite, $opts);
    setFlash('success', '<i class="bi bi-check-circle me-2"></i>Produit ajouté au panier !');
    redirect('index.php?page=produit&id=' . $produit_id);
}

$prod_image = '';
if (!empty($produit['image']) && file_exists(__DIR__ . '/../uploads/produits/' . $produit['image'])) {
    $prod_image = 'uploads/produits/' . $produit['image'];
}
$_wa_number = getParametre('whatsapp_number', '');
$_wa_clean = str_replace(['+', ' ', '-', '(', ')'], '', $_wa_number ?: APP_PHONE);
?>

<section class="bg-light py-3">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?page=catalogue">Catalogue</a></li>
                <li class="breadcrumb-item"><a href="index.php?page=catalogue&cat=<?= $produit['categorie_slug'] ?>"><?= htmlspecialchars($produit['categorie_nom']) ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($produit['nom']) ?></li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm overflow-hidden">
                    <?php if ($prod_image): ?>
                    <img src="<?= $prod_image ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" class="card-img-top" style="max-height:450px;object-fit:cover;">
                    <?php else: ?>
                    <div class="card-body bg-primary-soft d-flex align-items-center justify-content-center" style="min-height:400px;">
                        <i class="bi bi-printer display-1 text-primary"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="d-flex gap-2 mb-2">
                    <span class="badge bg-primary"><?= htmlspecialchars($produit['categorie_nom']) ?></span>
                    <?php if ($produit['populaire']): ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Populaire</span>
                    <?php endif; ?>
                </div>
                <h1 class="fw-bold mb-2"><?= htmlspecialchars($produit['nom']) ?></h1>
                <?php if ($produit['description_courte']): ?>
                <p class="text-muted lead mb-3"><?= htmlspecialchars($produit['description_courte']) ?></p>
                <?php endif; ?>

                <div class="d-flex align-items-baseline gap-3 mb-4">
                    <span class="display-6 fw-bold text-primary"><?= formatPrix($produit['prix_base']) ?></span>
                    <span class="text-muted fs-5">/ <?= htmlspecialchars($produit['unite']) ?></span>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-auto"><div class="d-flex align-items-center gap-2 px-3 py-2 bg-light rounded-pill"><i class="bi bi-clock text-primary"></i><span class="small fw-semibold"><?= htmlspecialchars($produit['delai_production']) ?></span></div></div>
                    <div class="col-auto"><div class="d-flex align-items-center gap-2 px-3 py-2 bg-light rounded-pill"><i class="bi bi-box text-primary"></i><span class="small fw-semibold">Min: <?= $produit['quantite_min'] ?></span></div></div>
                    <div class="col-auto"><div class="d-flex align-items-center gap-2 px-3 py-2 bg-light rounded-pill"><i class="bi bi-truck text-primary"></i><span class="small fw-semibold">Livraison Maroc</span></div></div>
                    <div class="col-auto"><div class="d-flex align-items-center gap-2 px-3 py-2 bg-light rounded-pill"><i class="bi bi-shield-check text-success"></i><span class="small fw-semibold">Qualité garantie</span></div></div>
                </div>

                <form method="POST" action="index.php?page=produit&id=<?= $produit_id ?>">
                    <?= csrfField() ?>
                    <?php foreach ($options as $opt):
                        $valeurs = json_decode($opt['valeurs'], true) ?: [];
                    ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= htmlspecialchars($opt['nom_option']) ?> <?= $opt['obligatoire'] ? '<span class="text-danger">*</span>' : '' ?></label>
                        <?php if ($opt['type_option'] === 'select'): ?>
                        <select name="options[<?= htmlspecialchars($opt['nom_option']) ?>]" class="form-select" <?= $opt['obligatoire'] ? 'required' : '' ?>>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($valeurs as $v): ?>
                            <option value="<?= htmlspecialchars(is_array($v) ? ($v['valeur'] ?? '') : $v) ?>">
                                <?= htmlspecialchars(is_array($v) ? ($v['valeur'] ?? '') : $v) ?>
                                <?php if (is_array($v) && !empty($v['prix_supplement'])): ?> (+<?= formatPrix($v['prix_supplement']) ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php elseif ($opt['type_option'] === 'text'): ?>
                        <input type="text" name="options[<?= htmlspecialchars($opt['nom_option']) ?>]" class="form-control" <?= $opt['obligatoire'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Quantité</label>
                        <div class="input-group" style="max-width:200px;">
                            <button type="button" class="btn btn-outline-primary" onclick="changeQte(-1)">-</button>
                            <input type="number" name="quantite" id="quantite" class="form-control text-center" value="<?= $produit['quantite_min'] ?>" min="<?= $produit['quantite_min'] ?>">
                            <button type="button" class="btn btn-outline-primary" onclick="changeQte(1)">+</button>
                        </div>
                    </div>

                    <div class="d-flex gap-3 flex-wrap">
                        <button type="submit" name="ajouter_panier" value="1" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-cart-plus me-2"></i>Ajouter au panier
                        </button>
                        <a href="https://wa.me/<?= $_wa_clean ?>?text=<?= urlencode('Bonjour, je suis intéressé par: ' . $produit['nom']) ?>"
                           class="btn btn-success btn-lg px-4" target="_blank">
                            <i class="bi bi-whatsapp me-2"></i>WhatsApp
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Description (rendered as HTML from TinyMCE) -->
        <?php if ($produit['description']): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Description détaillée</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="product-description"><?= $produit['description'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Similar Products -->
        <?php if (!empty($similaires)): ?>
        <div class="mt-5">
            <h3 class="fw-bold mb-4">Produits similaires</h3>
            <div class="row g-4">
                <?php foreach ($similaires as $sim):
                    $sim_img = (!empty($sim['image']) && file_exists(__DIR__ . '/../uploads/produits/' . $sim['image'])) ? 'uploads/produits/' . $sim['image'] : '';
                ?>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm h-100 product-card">
                        <?php if ($sim_img): ?>
                        <img src="<?= $sim_img ?>" alt="<?= htmlspecialchars($sim['nom']) ?>" class="card-img-top" style="height:150px;object-fit:cover;">
                        <?php else: ?>
                        <div class="card-img-top bg-primary-soft d-flex align-items-center justify-content-center" style="height:150px;">
                            <i class="bi bi-printer fs-2 text-primary"></i>
                        </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h6 class="fw-bold small"><?= htmlspecialchars($sim['nom']) ?></h6>
                            <span class="fw-bold text-primary"><?= formatPrix($sim['prix_base']) ?></span>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0">
                            <a href="index.php?page=produit&id=<?= $sim['id'] ?>" class="btn btn-outline-primary btn-sm w-100">Voir</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
function changeQte(delta) {
    const input = document.getElementById('quantite');
    const min = parseInt(input.min) || 1;
    let val = parseInt(input.value) + delta;
    if (val < min) val = min;
    input.value = val;
}
</script>
