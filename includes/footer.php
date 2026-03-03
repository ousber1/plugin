    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-3 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-printer-fill text-primary me-2"></i><?= APP_NAME ?>
                    </h5>
                    <p class="text-light opacity-75">
                        Votre partenaire de confiance pour tous vos besoins en impression au Maroc.
                        Qualité professionnelle, prix compétitifs et livraison rapide.
                    </p>
                    <div class="d-flex gap-2 mt-3">
                        <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-whatsapp"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6 class="fw-bold mb-3">Navigation</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-light text-decoration-none opacity-75">Accueil</a></li>
                        <li class="mb-2"><a href="index.php?page=catalogue" class="text-light text-decoration-none opacity-75">Nos Services</a></li>
                        <li class="mb-2"><a href="index.php?page=devis" class="text-light text-decoration-none opacity-75">Demande de Devis</a></li>
                        <li class="mb-2"><a href="index.php?page=contact" class="text-light text-decoration-none opacity-75">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="fw-bold mb-3">Services Populaires</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php?page=catalogue&cat=cartes-de-visite" class="text-light text-decoration-none opacity-75">Cartes de Visite</a></li>
                        <li class="mb-2"><a href="index.php?page=catalogue&cat=flyers-depliants" class="text-light text-decoration-none opacity-75">Flyers & Dépliants</a></li>
                        <li class="mb-2"><a href="index.php?page=catalogue&cat=banderoles-baches" class="text-light text-decoration-none opacity-75">Banderoles & Bâches</a></li>
                        <li class="mb-2"><a href="index.php?page=catalogue&cat=tampons-cachets" class="text-light text-decoration-none opacity-75">Tampons & Cachets</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="fw-bold mb-3">Contact</h6>
                    <ul class="list-unstyled text-light opacity-75">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i><?= APP_ADDRESS ?></li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i><?= APP_PHONE ?></li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i><?= APP_EMAIL ?></li>
                        <li class="mb-2"><i class="bi bi-clock me-2"></i>Lun-Sam: 9h00-19h00</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 opacity-25">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <small class="opacity-75">&copy; <?= date('Y') ?> <?= APP_NAME ?>. Tous droits réservés.</small>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <small class="opacity-75">
                        <i class="bi bi-truck me-1"></i> Paiement à la livraison
                        <span class="mx-2">|</span>
                        <i class="bi bi-shield-check me-1"></i> Qualité garantie
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/<?= str_replace(['+', ' ', '-'], '', APP_PHONE) ?>?text=Bonjour, je souhaite avoir des informations sur vos services d'impression."
       class="whatsapp-float" target="_blank" title="Contactez-nous sur WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
