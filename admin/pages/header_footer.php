<?php
/**
 * BERRADI PRINT - Paramètres Header / Footer
 */
$db = getDB();
$tab = $_GET['tab'] ?? 'header';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder'])) {
    verifyCsrf();
    $save_tab = $_POST['_tab'] ?? 'header';

    // Tab-aware saving
    $tab_keys = [
        'header' => [
            'site_logo', 'site_favicon',
            'header_bg_color', 'header_text_color',
            'header_announcement', 'header_announcement_active', 'header_announcement_bg', 'header_announcement_text_color',
            'header_topbar_active', 'header_topbar_phone', 'header_topbar_email',
            'header_topbar_hours', 'header_topbar_location',
            'header_show_cart', 'header_show_whatsapp_btn',
        ],
        'footer' => [
            'footer_about', 'footer_copyright',
            'footer_col1_title', 'footer_col2_title',
            'footer_bg_color', 'footer_text_color',
            'whatsapp_number', 'whatsapp_float_active', 'whatsapp_float_message',
        ],
        'social' => [
            'footer_facebook', 'footer_instagram', 'footer_twitter',
            'footer_youtube', 'footer_tiktok', 'footer_linkedin',
        ],
        'custom' => [
            'custom_css', 'custom_js_head', 'custom_js_body',
        ],
    ];

    $keys_to_save = $tab_keys[$save_tab] ?? [];
    foreach ($keys_to_save as $key) {
        setParametre($key, $_POST[$key] ?? '');
    }

    // Logo upload
    if ($save_tab === 'header') {
        if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $logo_name = 'site_logo_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_dir . $logo_name);
                setParametre('site_logo', 'uploads/logos/' . $logo_name);
            }
        }
        if (!empty($_FILES['favicon_file']['name']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['favicon_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['ico', 'png', 'svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $fav_name = 'favicon_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['favicon_file']['tmp_name'], $upload_dir . $fav_name);
                setParametre('site_favicon', 'uploads/logos/' . $fav_name);
            }
        }
    }

    setFlash('success', 'Paramètres sauvegardés avec succès.');
    redirect('index.php?page=header_footer&tab=' . urlencode($save_tab));
}

// Load current values
$all_keys = [
    'site_logo', 'site_favicon', 'header_bg_color', 'header_text_color',
    'header_announcement', 'header_announcement_active', 'header_announcement_bg', 'header_announcement_text_color',
    'header_topbar_active', 'header_topbar_phone', 'header_topbar_email',
    'header_topbar_hours', 'header_topbar_location',
    'header_show_cart', 'header_show_whatsapp_btn',
    'footer_about', 'footer_copyright',
    'footer_facebook', 'footer_instagram', 'footer_twitter', 'footer_youtube', 'footer_tiktok', 'footer_linkedin',
    'footer_col1_title', 'footer_col2_title',
    'footer_bg_color', 'footer_text_color',
    'whatsapp_number', 'whatsapp_float_active', 'whatsapp_float_message',
    'custom_css', 'custom_js_head', 'custom_js_body'
];
$p = [];
foreach ($all_keys as $k) {
    $p[$k] = getParametre($k, '');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-layout-text-window-reverse me-2"></i>Header / Footer</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $tab === 'header' ? 'active' : '' ?>" href="?page=header_footer&tab=header">Header</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'footer' ? 'active' : '' ?>" href="?page=header_footer&tab=footer">Footer</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'social' ? 'active' : '' ?>" href="?page=header_footer&tab=social">Réseaux sociaux</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'custom' ? 'active' : '' ?>" href="?page=header_footer&tab=custom">Code personnalisé</a></li>
</ul>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="_tab" value="<?= htmlspecialchars($tab) ?>">

    <?php if ($tab === 'header'): ?>
    <!-- HEADER SETTINGS -->
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Logo & Favicon -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-image me-2"></i>Logo & Favicon</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Logo du site</label>
                            <?php if ($p['site_logo']): ?>
                            <div class="mb-2">
                                <img src="../<?= htmlspecialchars($p['site_logo']) ?>" alt="Logo" style="max-height:60px;" class="rounded border p-1">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="logo_file" class="form-control" accept="image/*">
                            <small class="text-muted">JPG, PNG, SVG. Max 200x80px recommandé</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Favicon</label>
                            <?php if ($p['site_favicon']): ?>
                            <div class="mb-2">
                                <img src="../<?= htmlspecialchars($p['site_favicon']) ?>" alt="Favicon" style="max-height:32px;" class="rounded border p-1">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="favicon_file" class="form-control" accept=".ico,.png,.svg">
                            <small class="text-muted">ICO, PNG ou SVG. 32x32px</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Bar -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-layout-text-sidebar me-2"></i>Barre supérieure (Top Bar)</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="header_topbar_active" id="header_topbar_active" value="1" <?= ($p['header_topbar_active'] ?: '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="header_topbar_active">Afficher la barre supérieure</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="header_topbar_phone" class="form-control" value="<?= htmlspecialchars($p['header_topbar_phone'] ?: APP_PHONE) ?>" placeholder="+212 6XX-XXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="header_topbar_email" class="form-control" value="<?= htmlspecialchars($p['header_topbar_email'] ?: APP_EMAIL) ?>" placeholder="contact@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Horaires</label>
                            <input type="text" name="header_topbar_hours" class="form-control" value="<?= htmlspecialchars($p['header_topbar_hours'] ?: 'Lundi - Samedi: 9h00 - 19h00') ?>" placeholder="Lun-Sam: 9h-19h">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Localisation</label>
                            <input type="text" name="header_topbar_location" class="form-control" value="<?= htmlspecialchars($p['header_topbar_location'] ?: APP_ADDRESS) ?>" placeholder="Ville, Pays">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Announcement Banner -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-megaphone me-2"></i>Bannière d'annonce</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="header_announcement_active" id="header_announcement_active" value="1" <?= $p['header_announcement_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="header_announcement_active">Afficher la bannière d'annonce</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Texte de l'annonce</label>
                        <input type="text" name="header_announcement" class="form-control" value="<?= htmlspecialchars($p['header_announcement']) ?>" placeholder="Ex: Livraison gratuite à partir de 500 DH !">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Couleur de fond bannière</label>
                            <input type="color" name="header_announcement_bg" class="form-control form-control-color" value="<?= $p['header_announcement_bg'] ?: '#ffc107' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Couleur du texte bannière</label>
                            <input type="color" name="header_announcement_text_color" class="form-control form-control-color" value="<?= $p['header_announcement_text_color'] ?: '#212529' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Colors & Options -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-palette me-2"></i>Couleurs & Options du Header</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Couleur de fond navbar</label>
                            <input type="color" name="header_bg_color" class="form-control form-control-color" value="<?= $p['header_bg_color'] ?: '#ffffff' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Couleur du texte navbar</label>
                            <input type="color" name="header_text_color" class="form-control form-control-color" value="<?= $p['header_text_color'] ?: '#212529' ?>">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="header_show_cart" id="header_show_cart" value="1" <?= ($p['header_show_cart'] ?: '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="header_show_cart">Afficher le bouton Panier</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="header_show_whatsapp_btn" id="header_show_whatsapp_btn" value="1" <?= ($p['header_show_whatsapp_btn'] ?: '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="header_show_whatsapp_btn">Afficher le bouton WhatsApp</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Aide</h6>
                    <p class="small text-muted"><strong>Logo:</strong> Affiché dans la navbar à côté du nom du site.</p>
                    <p class="small text-muted"><strong>Barre supérieure:</strong> Affiche téléphone, email, horaires et localisation en haut du site.</p>
                    <p class="small text-muted"><strong>Bannière d'annonce:</strong> Bandeau coloré en haut de toutes les pages pour les promotions.</p>
                    <p class="small text-muted mb-0"><strong>Couleurs:</strong> Personnalisez l'apparence de la barre de navigation.</p>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'footer'): ?>
    <!-- FOOTER SETTINGS -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-layout-three-columns me-2"></i>Contenu du Footer</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Texte "À propos"</label>
                            <textarea name="footer_about" class="form-control" rows="3" placeholder="Description courte de votre entreprise..."><?= htmlspecialchars($p['footer_about']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Copyright</label>
                            <input type="text" name="footer_copyright" class="form-control" value="<?= htmlspecialchars($p['footer_copyright']) ?>" placeholder="&copy; 2025 BERRADI PRINT. Tous droits réservés.">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-palette me-2"></i>Couleurs du Footer</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Couleur de fond</label>
                            <input type="color" name="footer_bg_color" class="form-control form-control-color" value="<?= $p['footer_bg_color'] ?: '#1a1a2e' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Couleur du texte</label>
                            <input type="color" name="footer_text_color" class="form-control form-control-color" value="<?= $p['footer_text_color'] ?: '#ffffff' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Flottant</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="whatsapp_float_active" id="whatsapp_float_active" value="1" <?= $p['whatsapp_float_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="whatsapp_float_active">Afficher le bouton WhatsApp flottant</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Numéro WhatsApp</label>
                        <input type="text" name="whatsapp_number" class="form-control" value="<?= htmlspecialchars($p['whatsapp_number']) ?>" placeholder="+212 600-000000">
                        <small class="text-muted">Numéro avec indicatif pays. Utilisé pour le bouton flottant et les liens WhatsApp du site.</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Message pré-rempli</label>
                        <input type="text" name="whatsapp_float_message" class="form-control" value="<?= htmlspecialchars($p['whatsapp_float_message']) ?>" placeholder="Bonjour, je souhaite avoir des informations...">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Aide</h6>
                    <p class="small text-muted">Le footer s'affiche en bas de chaque page du site.</p>
                    <p class="small text-muted mb-0">Le bouton WhatsApp flottant apparaît en bas à droite pour un contact rapide.</p>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'social'): ?>
    <!-- SOCIAL LINKS -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-share me-2"></i>Liens Réseaux Sociaux</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-facebook text-primary me-2"></i>Facebook</label>
                            <input type="url" name="footer_facebook" class="form-control" value="<?= htmlspecialchars($p['footer_facebook']) ?>" placeholder="https://facebook.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-instagram text-danger me-2"></i>Instagram</label>
                            <input type="url" name="footer_instagram" class="form-control" value="<?= htmlspecialchars($p['footer_instagram']) ?>" placeholder="https://instagram.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-twitter-x me-2"></i>Twitter / X</label>
                            <input type="url" name="footer_twitter" class="form-control" value="<?= htmlspecialchars($p['footer_twitter']) ?>" placeholder="https://x.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-youtube text-danger me-2"></i>YouTube</label>
                            <input type="url" name="footer_youtube" class="form-control" value="<?= htmlspecialchars($p['footer_youtube']) ?>" placeholder="https://youtube.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-tiktok me-2"></i>TikTok</label>
                            <input type="url" name="footer_tiktok" class="form-control" value="<?= htmlspecialchars($p['footer_tiktok']) ?>" placeholder="https://tiktok.com/@...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-linkedin text-primary me-2"></i>LinkedIn</label>
                            <input type="url" name="footer_linkedin" class="form-control" value="<?= htmlspecialchars($p['footer_linkedin']) ?>" placeholder="https://linkedin.com/...">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Aide</h6>
                    <p class="small text-muted mb-0">Ajoutez les URLs complètes de vos profils sociaux. Laissez vide pour ne pas afficher un réseau.</p>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'custom'): ?>
    <!-- CUSTOM CODE -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">CSS personnalisé</div>
                <div class="card-body">
                    <textarea name="custom_css" class="form-control font-monospace" rows="8" placeholder="/* Vos styles CSS personnalisés */"><?= htmlspecialchars($p['custom_css']) ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">JavaScript (Head)</div>
                <div class="card-body">
                    <textarea name="custom_js_head" class="form-control font-monospace" rows="6" placeholder="<!-- Scripts dans le head -->"><?= htmlspecialchars($p['custom_js_head']) ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">JavaScript (Body)</div>
                <div class="card-body">
                    <textarea name="custom_js_body" class="form-control font-monospace" rows="6" placeholder="<!-- Scripts avant </body> -->"><?= htmlspecialchars($p['custom_js_body']) ?></textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Attention</h6>
                    <p class="small text-muted mb-0">Le code personnalisé est injecté sans modification. Assurez-vous qu'il est correct.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="mt-4">
        <button type="submit" name="sauvegarder" class="btn btn-primary btn-lg">
            <i class="bi bi-check-circle me-2"></i>Sauvegarder les paramètres
        </button>
    </div>
</form>
