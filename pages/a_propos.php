<?php
/**
 * BERRADI PRINT - À Propos
 */
?>

<section class="bg-primary py-4">
    <div class="container text-center">
        <h1 class="text-white fw-bold"><?= APP_NAME ?></h1>
        <p class="text-white-50"><?= APP_TAGLINE ?></p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-4">Votre Partenaire en Impression au Maroc</h2>
                <p class="text-muted">
                    <strong><?= APP_NAME ?></strong> est une entreprise spécialisée dans les services d'impression
                    et de communication visuelle au Maroc. Nous offrons une gamme complète de produits imprimés
                    de haute qualité à des prix compétitifs.
                </p>
                <p class="text-muted">
                    Que vous soyez un particulier, une PME ou une grande entreprise, nous avons les solutions
                    adaptées à vos besoins : cartes de visite, flyers, banderoles, signalétique, objets
                    publicitaires et bien plus encore.
                </p>

                <div class="row g-3 mt-4">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            <span>Qualité Premium</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            <span>Prix Compétitifs</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            <span>Livraison Rapide</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            <span>Paiement à la Livraison</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body bg-primary-soft text-center p-5" style="border-radius:12px;">
                        <i class="bi bi-printer display-1 text-primary"></i>
                        <h3 class="fw-bold mt-3 text-primary"><?= APP_NAME ?></h3>
                        <p class="text-muted"><?= APP_TAGLINE ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chiffres clés -->
        <div class="row g-4 mt-5 text-center">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm p-4">
                    <h2 class="fw-bold text-primary mb-0">12+</h2>
                    <p class="text-muted mb-0">Catégories de services</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm p-4">
                    <h2 class="fw-bold text-primary mb-0">20+</h2>
                    <p class="text-muted mb-0">Villes desservies</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm p-4">
                    <h2 class="fw-bold text-primary mb-0">24h</h2>
                    <p class="text-muted mb-0">Délai express</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm p-4">
                    <h2 class="fw-bold text-primary mb-0">100%</h2>
                    <p class="text-muted mb-0">Satisfaction client</p>
                </div>
            </div>
        </div>
    </div>
</section>
