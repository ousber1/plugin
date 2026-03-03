    </main>

    <!-- Footer -->
    <?php
    $_footer_about = getParametre('footer_about', 'Votre partenaire de confiance pour tous vos besoins en impression au Maroc.');
    $_footer_copyright = getParametre('footer_copyright', '');
    $_footer_bg = getParametre('footer_bg_color', '#1a1a2e');
    $_footer_text = getParametre('footer_text_color', '#ffffff');
    $_wa_number = getParametre('whatsapp_number', '');
    $_wa_clean = str_replace(['+', ' ', '-', '(', ')'], '', $_wa_number ?: APP_PHONE);
    $_wa_msg = getParametre('whatsapp_float_message', 'Bonjour, je souhaite avoir des informations sur vos services d\'impression.');
    $_wa_active = getParametre('whatsapp_float_active', '1');
    $_fb_url = getParametre('footer_facebook', '');
    $_ig_url = getParametre('footer_instagram', '');
    $_tw_url = getParametre('footer_twitter', '');
    $_yt_url = getParametre('footer_youtube', '');
    $_tt_url = getParametre('footer_tiktok', '');
    $_li_url = getParametre('footer_linkedin', '');
    ?>
    <footer style="background-color:<?= $_footer_bg ?>;color:<?= $_footer_text ?>;" class="pt-5 pb-3 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">
                        <?php $_site_logo_f = getParametre('site_logo', ''); if ($_site_logo_f && file_exists($_site_logo_f)): ?>
                        <img src="<?= htmlspecialchars($_site_logo_f) ?>" alt="<?= APP_NAME ?>" style="max-height:40px;" class="me-2">
                        <?php else: ?>
                        <i class="bi bi-printer-fill text-primary me-2"></i>
                        <?php endif; ?>
                        <?= APP_NAME ?>
                    </h5>
                    <p style="opacity:0.75"><?= htmlspecialchars($_footer_about) ?></p>
                    <div class="d-flex gap-2 mt-3">
                        <?php if ($_fb_url): ?><a href="<?= htmlspecialchars($_fb_url) ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-facebook"></i></a><?php endif; ?>
                        <?php if ($_ig_url): ?><a href="<?= htmlspecialchars($_ig_url) ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-instagram"></i></a><?php endif; ?>
                        <?php if ($_tw_url): ?><a href="<?= htmlspecialchars($_tw_url) ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-twitter-x"></i></a><?php endif; ?>
                        <?php if ($_yt_url): ?><a href="<?= htmlspecialchars($_yt_url) ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-youtube"></i></a><?php endif; ?>
                        <?php if ($_tt_url): ?><a href="<?= htmlspecialchars($_tt_url) ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-tiktok"></i></a><?php endif; ?>
                        <?php if ($_li_url): ?><a href="<?= htmlspecialchars($_li_url) ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-linkedin"></i></a><?php endif; ?>
                        <?php if (!$_fb_url && !$_ig_url && !$_tw_url): ?>
                        <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-instagram"></i></a>
                        <a href="https://wa.me/<?= $_wa_clean ?>" class="btn btn-outline-light btn-sm" target="_blank"><i class="bi bi-whatsapp"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6 class="fw-bold mb-3">Navigation</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-decoration-none" style="color:<?= $_footer_text ?>;opacity:0.75;">Accueil</a></li>
                        <li class="mb-2"><a href="index.php?page=catalogue" class="text-decoration-none" style="color:<?= $_footer_text ?>;opacity:0.75;">Nos Services</a></li>
                        <li class="mb-2"><a href="index.php?page=devis" class="text-decoration-none" style="color:<?= $_footer_text ?>;opacity:0.75;">Demande de Devis</a></li>
                        <li class="mb-2"><a href="index.php?page=contact" class="text-decoration-none" style="color:<?= $_footer_text ?>;opacity:0.75;">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="fw-bold mb-3">Services Populaires</h6>
                    <ul class="list-unstyled">
                        <?php
                        try {
                            $db = getDB();
                            $footer_cats = $db->query("SELECT slug, nom FROM categories WHERE actif = 1 ORDER BY ordre LIMIT 4")->fetchAll();
                            foreach ($footer_cats as $fc): ?>
                        <li class="mb-2"><a href="index.php?page=catalogue&cat=<?= $fc['slug'] ?>" class="text-decoration-none" style="color:<?= $_footer_text ?>;opacity:0.75;"><?= htmlspecialchars($fc['nom']) ?></a></li>
                        <?php endforeach; } catch(Exception $e) {} ?>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="fw-bold mb-3">Contact</h6>
                    <ul class="list-unstyled" style="opacity:0.75">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i><?= APP_ADDRESS ?></li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i><?= APP_PHONE ?></li>
                        <?php if ($_wa_number && $_wa_number !== APP_PHONE): ?>
                        <li class="mb-2"><i class="bi bi-whatsapp me-2 text-success"></i><?= htmlspecialchars($_wa_number) ?></li>
                        <?php endif; ?>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i><?= APP_EMAIL ?></li>
                        <li class="mb-2"><i class="bi bi-clock me-2"></i>Lun-Sam: 9h00-19h00</li>
                    </ul>
                </div>
            </div>
            <hr style="opacity:0.25" class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <small style="opacity:0.75"><?= $_footer_copyright ?: '&copy; ' . date('Y') . ' ' . APP_NAME . '. Tous droits réservés.' ?></small>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <small style="opacity:0.75">
                        <i class="bi bi-truck me-1"></i> Paiement à la livraison
                        <span class="mx-2">|</span>
                        <i class="bi bi-shield-check me-1"></i> Qualité garantie
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Floating Button -->
    <?php if ($_wa_active): ?>
    <a href="https://wa.me/<?= $_wa_clean ?>?text=<?= urlencode($_wa_msg) ?>"
       class="whatsapp-float" target="_blank" title="Contactez-nous sur WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
