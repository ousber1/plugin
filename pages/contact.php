<?php
/**
 * BERRADI PRINT - Page Contact
 */
?>

<section class="bg-primary py-4">
    <div class="container text-center">
        <h1 class="text-white fw-bold"><i class="bi bi-chat-dots me-2"></i>Contactez-nous</h1>
        <p class="text-white-50">Nous sommes à votre disposition pour toute question</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle-lg bg-primary-soft mx-auto mb-3">
                            <i class="bi bi-telephone fs-2 text-primary"></i>
                        </div>
                        <h5 class="fw-bold">Téléphone</h5>
                        <p class="text-muted"><?= APP_PHONE ?></p>
                        <a href="tel:<?= str_replace(' ', '', APP_PHONE) ?>" class="btn btn-outline-primary">Appeler</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle-lg bg-success-soft mx-auto mb-3">
                            <i class="bi bi-whatsapp fs-2 text-success"></i>
                        </div>
                        <h5 class="fw-bold">WhatsApp</h5>
                        <p class="text-muted">Réponse rapide par WhatsApp</p>
                        <a href="https://wa.me/<?= str_replace(['+', ' ', '-'], '', APP_PHONE) ?>" class="btn btn-success" target="_blank">
                            <i class="bi bi-whatsapp me-2"></i>Écrire
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle-lg bg-warning-soft mx-auto mb-3">
                            <i class="bi bi-envelope fs-2 text-warning"></i>
                        </div>
                        <h5 class="fw-bold">Email</h5>
                        <p class="text-muted"><?= APP_EMAIL ?></p>
                        <a href="mailto:<?= APP_EMAIL ?>" class="btn btn-outline-warning">Envoyer un email</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-clock me-2"></i>Horaires d'ouverture
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td>Lundi - Vendredi</td><td class="text-end fw-bold">9h00 - 19h00</td></tr>
                            <tr><td>Samedi</td><td class="text-end fw-bold">9h00 - 17h00</td></tr>
                            <tr><td>Dimanche</td><td class="text-end text-danger fw-bold">Fermé</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-geo-alt me-2"></i>Notre adresse
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><i class="bi bi-building me-2"></i><strong><?= APP_NAME ?></strong></p>
                        <p class="mb-2"><i class="bi bi-geo-alt me-2"></i><?= APP_ADDRESS ?></p>
                        <p class="mb-0"><i class="bi bi-truck me-2"></i>Livraison disponible dans tout le Maroc</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
