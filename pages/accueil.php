<?php
/**
 * BERRADI PRINT - Page d'Accueil
 */
try {
    $db = getDB();
    $categories = $db->query("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();
    $produits_populaires = $db->query("SELECT p.*, c.nom as categorie_nom FROM produits p JOIN categories c ON p.categorie_id = c.id WHERE p.actif = 1 AND p.populaire = 1 ORDER BY p.ordre LIMIT 8")->fetchAll();
} catch(Exception $e) {
    $categories = [];
    $produits_populaires = [];
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-50">
            <div class="col-lg-7">
                <h1 class="display-4 fw-bold text-white mb-3">
                    Vos Impressions<br>
                    <span class="text-warning">Professionnelles</span> au Maroc
                </h1>
                <p class="lead text-white opacity-90 mb-4">
                    De la carte de visite aux grands formats, <?= APP_NAME ?> vous accompagne
                    dans tous vos projets d'impression avec qualité et rapidité.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="index.php?page=catalogue" class="btn btn-warning btn-lg fw-semibold">
                        <i class="bi bi-grid me-2"></i>Voir nos services
                    </a>
                    <a href="index.php?page=devis" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-file-text me-2"></i>Demander un devis
                    </a>
                </div>
                <div class="mt-4 d-flex gap-4 text-white">
                    <div><i class="bi bi-truck me-2"></i><small>Livraison partout au Maroc</small></div>
                    <div><i class="bi bi-cash me-2"></i><small>Paiement à la livraison</small></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Avantages -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="icon-circle bg-primary-soft mx-auto mb-3">
                        <i class="bi bi-award fs-3 text-primary"></i>
                    </div>
                    <h6 class="fw-bold">Qualité Premium</h6>
                    <small class="text-muted">Impression haute résolution</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="icon-circle bg-success-soft mx-auto mb-3">
                        <i class="bi bi-lightning fs-3 text-success"></i>
                    </div>
                    <h6 class="fw-bold">Rapide</h6>
                    <small class="text-muted">Délai 24-48h</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="icon-circle bg-warning-soft mx-auto mb-3">
                        <i class="bi bi-cash-coin fs-3 text-warning"></i>
                    </div>
                    <h6 class="fw-bold">Paiement à la livraison</h6>
                    <small class="text-muted">Cash on delivery</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3">
                    <div class="icon-circle bg-info-soft mx-auto mb-3">
                        <i class="bi bi-truck fs-3 text-info"></i>
                    </div>
                    <h6 class="fw-bold">Livraison Maroc</h6>
                    <small class="text-muted">Toutes les villes</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Catégories de Services -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Nos Services d'Impression</h2>
            <p class="text-muted">Des solutions complètes pour tous vos besoins en impression</p>
        </div>
        <div class="row g-4">
            <?php foreach ($categories as $cat): ?>
            <div class="col-lg-3 col-md-4 col-6">
                <a href="index.php?page=catalogue&cat=<?= $cat['slug'] ?>" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 category-card">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle-lg bg-primary-soft mx-auto mb-3">
                                <i class="<?= $cat['icone'] ?> fs-2 text-primary"></i>
                            </div>
                            <h6 class="fw-bold text-dark"><?= $cat['nom'] ?></h6>
                            <small class="text-muted"><?= mb_strimwidth($cat['description'], 0, 60, '...') ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Produits Populaires -->
<?php if (!empty($produits_populaires)): ?>
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Produits Populaires</h2>
            <p class="text-muted">Les services les plus demandés par nos clients</p>
        </div>
        <div class="row g-4">
            <?php foreach ($produits_populaires as $prod): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100 product-card">
                    <div class="card-img-top bg-primary-soft d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="bi bi-printer fs-1 text-primary"></i>
                    </div>
                    <div class="card-body">
                        <span class="badge bg-primary-soft text-primary mb-2"><?= $prod['categorie_nom'] ?></span>
                        <h6 class="fw-bold"><?= $prod['nom'] ?></h6>
                        <p class="text-muted small mb-2"><?= $prod['description_courte'] ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-primary fs-5"><?= formatPrix($prod['prix_base']) ?></span>
                            <small class="text-muted">/<?= $prod['unite'] ?></small>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <div class="d-grid gap-2">
                            <a href="index.php?page=produit&id=<?= $prod['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>Voir détails
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="index.php?page=catalogue" class="btn btn-outline-primary btn-lg">
                Voir tous nos services <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section CTA -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Besoin d'un devis personnalisé ?</h2>
        <p class="lead opacity-90 mb-4">
            Contactez-nous pour un devis gratuit et sans engagement. Nous répondons en moins de 2 heures !
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="index.php?page=devis" class="btn btn-warning btn-lg fw-semibold">
                <i class="bi bi-file-text me-2"></i>Demander un devis
            </a>
            <a href="https://wa.me/<?= str_replace(['+', ' ', '-'], '', APP_PHONE) ?>" class="btn btn-light btn-lg" target="_blank">
                <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- Témoignages -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Ce que disent nos clients</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex text-warning mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="text-muted">"Excellent travail ! Les cartes de visite sont magnifiques et la livraison était rapide. Je recommande vivement."</p>
                        <div class="fw-bold">Ahmed M.</div>
                        <small class="text-muted">Entrepreneur - Casablanca</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex text-warning mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="text-muted">"La qualité des banderoles est exceptionnelle. Prix compétitifs et service client très professionnel."</p>
                        <div class="fw-bold">Fatima Z.</div>
                        <small class="text-muted">Gérante - Rabat</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex text-warning mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                        </div>
                        <p class="text-muted">"Très satisfait de la papeterie complète pour mon entreprise. En-têtes, enveloppes, tout est parfait !"</p>
                        <div class="fw-bold">Youssef B.</div>
                        <small class="text-muted">Directeur - Marrakech</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
