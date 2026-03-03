<?php
/**
 * BERRADI PRINT - Paramètres Header / Footer
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder'])) {
    verifyCsrf();

    $params = [
        // header
        'site_logo', 'site_favicon', 'header_bg_color', 'header_text_color', 'header_announcement', 'header_announcement_active',
        // footer
        'footer_about', 'footer_copyright', 'footer_col1_title', 'footer_col2_title', 'footer_bg_color', 'footer_text_color',
        // social / whatsapp
        'footer_facebook', 'footer_instagram', 'footer_twitter', 'footer_youtube', 'footer_tiktok', 'footer_linkedin',
        'whatsapp_float_active', 'whatsapp_float_number', 'whatsapp_float_message',
        // custom code
        'custom_css', 'custom_js_head', 'custom_js_body',
        // invoices
        'invoice_header', 'invoice_payment_terms', 'invoice_company_number', 'invoice_footer_message', 'invoice_legal_notice',
        // orders / printing
        'order_print_show_client_notes', 'order_print_show_payement_proof', 'order_print_show_qr_code', 'order_print_custom_css'
    ];

    // Save simple params
    foreach ($params as $key) {
        if (isset($_POST[$key])) {
            setParametre($key, $_POST[$key]);
        }
    }

    // Handle checkboxes explicitly (unchecked checkboxes don't send POST data)
    $checkbox_params = ['header_announcement_active', 'whatsapp_float_active', 'order_print_show_client_notes', 'order_print_show_payement_proof', 'order_print_show_qr_code'];
    foreach ($checkbox_params as $key) {
        if (!isset($_POST[$key])) {
            setParametre($key, 0);
        }
    }

    // Logo upload
    if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $upload_dir = __DIR__ . '/../../uploads/logos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $logo_name = 'site_logo.' . $ext;
            move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_dir . $logo_name);
            setParametre('site_logo', 'uploads/logos/' . $logo_name);
        }
    }

    // Favicon upload
    if (!empty($_FILES['favicon_file']['name']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['favicon_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['ico', 'png', 'svg'])) {
            $upload_dir = __DIR__ . '/../../uploads/logos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $fav_name = 'favicon.' . $ext;
            move_uploaded_file($_FILES['favicon_file']['tmp_name'], $upload_dir . $fav_name);
            setParametre('site_favicon', 'uploads/logos/' . $fav_name);
        }
    }

    setFlash('success', 'Paramètres sauvegardés avec succès.');
    $_SESSION['last_customizer_tab'] = $_GET['tab'] ?? 'header';
    redirect('index.php?page=header_footer');
}

// Load current values
$keys = [
    'site_logo', 'site_favicon', 'header_bg_color', 'header_text_color', 'header_announcement', 'header_announcement_active',
    'footer_about', 'footer_copyright', 'footer_col1_title', 'footer_col2_title', 'footer_bg_color', 'footer_text_color',
    'footer_facebook', 'footer_instagram', 'footer_twitter', 'footer_youtube', 'footer_tiktok', 'footer_linkedin',
    'whatsapp_float_active', 'whatsapp_float_number', 'whatsapp_float_message',
    'custom_css', 'custom_js_head', 'custom_js_body',
    'invoice_header', 'invoice_payment_terms', 'invoice_company_number', 'invoice_footer_message', 'invoice_legal_notice',
    'order_print_show_client_notes', 'order_print_show_payement_proof', 'order_print_show_qr_code', 'order_print_custom_css'
];

$p = [];
foreach ($keys as $k) {
    $p[$k] = getParametre($k, '');
}

$tab = $_GET['tab'] ?? 'header';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-layout-text-window-reverse me-2"></i>Header / Footer</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $tab === 'header' ? 'active' : '' ?>" href="?page=header_footer&tab=header">Header</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'footer' ? 'active' : '' ?>" href="?page=header_footer&tab=footer">Footer</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'social' ? 'active' : '' ?>" href="?page=header_footer&tab=social">Réseaux sociaux</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'custom' ? 'active' : '' ?>" href="?page=header_footer&tab=custom">Code personnalisé</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'invoices' ? 'active' : '' ?>" href="?page=header_footer&tab=invoices">Factures & Devis</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'orders' ? 'active' : '' ?>" href="?page=header_footer&tab=orders">Commandes Imprimées</a></li>
</ul>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <?php if ($tab === 'header'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Logo & Favicon</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Logo du site</label>
                            <?php if ($p['site_logo']): ?>
                            <div class="mb-2">
                                <img src="../<?= htmlspecialchars($p['site_logo']) ?>" alt="Logo" style="max-height:60px;" class="rounded border p-1">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="logo_file" class="form-control" accept="image/*">
                            <small class="text-muted">JPG, PNG, SVG recommandé.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Favicon</label>
                            <?php if ($p['site_favicon']): ?>
                            <div class="mb-2">
                                <img src="../<?= htmlspecialchars($p['site_favicon']) ?>" alt="Favicon" style="max-height:32px;" class="rounded border p-1">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="favicon_file" class="form-control" accept=".ico,.png,.svg">
                            <small class="text-muted">ICO, PNG ou SVG. 32x32px recommandé</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Bannière d'annonce</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="header_announcement_active" id="header_announcement_active" value="1" <?= $p['header_announcement_active'] ? 'checked' : '' ?> />
                        <label class="form-check-label" for="header_announcement_active">Afficher la bannière d'annonce</label>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Texte de l'annonce</label>
                        <input type="text" name="header_announcement" class="form-control" value="<?= htmlspecialchars($p['header_announcement']) ?>" placeholder="Ex: Livraison gratuite à partir de 500 DH !">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Couleurs du Header</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Couleur de fond</label>
                            <div class="input-group">
                                <input type="color" id="header_bg_color" name="header_bg_color" class="form-control form-control-color" value="<?= $p['header_bg_color'] ?: '#ffffff' ?>">
                                <input type="text" class="form-control" value="<?= $p['header_bg_color'] ?: '#ffffff' ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Couleur du texte</label>
                            <div class="input-group">
                                <input type="color" id="header_text_color" name="header_text_color" class="form-control form-control-color" value="<?= $p['header_text_color'] ?: '#212529' ?>">
                                <input type="text" class="form-control" value="<?= $p['header_text_color'] ?: '#212529' ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Aide</h6>
                    <p class="small text-muted">Le logo est affiché dans le header du site à côté du nom. Le favicon apparaît dans l'onglet du navigateur.</p>
                    <p class="small text-muted mb-0">La bannière d'annonce s'affiche en haut de toutes les pages.</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Aperçu live</div>
                <div class="card-body position-relative">
                    <div class="customizer-preview">
                        <div class="preview-header" id="preview_header" style="background: <?= htmlspecialchars($p['header_bg_color'] ?: '#ffffff') ?>; color: <?= htmlspecialchars($p['header_text_color'] ?: '#212529') ?>;">
                            <img id="preview_logo" src="<?= $p['site_logo'] ? '../'.htmlspecialchars($p['site_logo']) : '' ?>" alt="Logo" onerror="this.style.display='none'">
                            <div class="flex-grow-1"><strong><?= APP_NAME ?></strong></div>
                        </div>
                        <?php if ($p['header_announcement_active'] && $p['header_announcement']): ?>
                            <div class="annonce text-center" id="preview_annonce" style="background: rgba(0,0,0,0.06); margin:10px;"><?= htmlspecialchars($p['header_announcement']) ?></div>
                        <?php else: ?>
                            <div class="annonce text-center d-none" id="preview_annonce"></div>
                        <?php endif; ?>
                        <div class="preview-body">Contenu de la page — Aperçu</div>
                        <div class="preview-footer" id="preview_footer" style="background: <?= htmlspecialchars($p['footer_bg_color'] ?: '#1a1a2e') ?>; color: <?= htmlspecialchars($p['footer_text_color'] ?: '#ffffff') ?>;">
                            <small><?= htmlspecialchars($p['footer_about'] ?: 'Texte de pied de page exemple') ?></small>
                        </div>
                        <a href="#" class="whatsapp-btn d-none" id="preview_whatsapp"><i class="bi bi-whatsapp"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'footer'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Contenu du Footer</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Texte "À propos"</label>
                            <textarea name="footer_about" class="form-control" rows="3"><?= htmlspecialchars($p['footer_about']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Copyright</label>
                            <input type="text" name="footer_copyright" class="form-control" value="<?= htmlspecialchars($p['footer_copyright']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Titre colonne 1</label>
                            <input type="text" name="footer_col1_title" class="form-control" value="<?= htmlspecialchars($p['footer_col1_title'] ?: 'Liens rapides') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Titre colonne 2</label>
                            <input type="text" name="footer_col2_title" class="form-control" value="<?= htmlspecialchars($p['footer_col2_title'] ?: 'Contact') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Couleurs du Footer</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Couleur de fond</label>
                            <div class="input-group">
                                <input type="color" id="footer_bg_color" name="footer_bg_color" class="form-control form-control-color" value="<?= $p['footer_bg_color'] ?: '#1a1a2e' ?>">
                                <input type="text" class="form-control" value="<?= $p['footer_bg_color'] ?: '#1a1a2e' ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Couleur du texte</label>
                            <div class="input-group">
                                <input type="color" id="footer_text_color" name="footer_text_color" class="form-control form-control-color" value="<?= $p['footer_text_color'] ?: '#ffffff' ?>">
                                <input type="text" class="form-control" value="<?= $p['footer_text_color'] ?: '#ffffff' ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-phone me-2"></i>Réseaux & Contact</h6>
                    <p class="small text-muted mb-0">Saisissez les liens de vos réseaux sociaux pour les afficher dans le footer.</p>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'social'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Réseaux sociaux</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Facebook</label><input type="text" name="footer_facebook" class="form-control" value="<?= htmlspecialchars($p['footer_facebook']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Instagram</label><input type="text" name="footer_instagram" class="form-control" value="<?= htmlspecialchars($p['footer_instagram']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Twitter</label><input type="text" name="footer_twitter" class="form-control" value="<?= htmlspecialchars($p['footer_twitter']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">YouTube</label><input type="text" name="footer_youtube" class="form-control" value="<?= htmlspecialchars($p['footer_youtube']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">TikTok</label><input type="text" name="footer_tiktok" class="form-control" value="<?= htmlspecialchars($p['footer_tiktok']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">LinkedIn</label><input type="text" name="footer_linkedin" class="form-control" value="<?= htmlspecialchars($p['footer_linkedin']) ?>"></div>
                    </div>
                    <hr class="my-3">
                    <h6 class="fw-bold">WhatsApp flottant</h6>
                    <div class="row g-2">
                        <div class="col-12 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="whatsapp_float_active" id="whatsapp_float_active" value="1" <?= $p['whatsapp_float_active'] ? 'checked' : '' ?> />
                            <label class="form-check-label" for="whatsapp_float_active">Activer le bouton WhatsApp flottant</label>
                        </div>
                        <div class="col-md-6"><label class="form-label">Numéro WhatsApp</label><input type="text" name="whatsapp_float_number" class="form-control" value="<?= htmlspecialchars($p['whatsapp_float_number']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Message pré-défini</label><input type="text" name="whatsapp_float_message" class="form-control" value="<?= htmlspecialchars($p['whatsapp_float_message']) ?>"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'custom'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Code personnalisé</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">CSS personnalisé</label><textarea name="custom_css" class="form-control" rows="6"><?= htmlspecialchars($p['custom_css']) ?></textarea></div>
                    <div class="mb-3"><label class="form-label">JS dans &lt;head&gt;</label><textarea name="custom_js_head" class="form-control" rows="3"><?= htmlspecialchars($p['custom_js_head']) ?></textarea></div>
                    <div class="mb-3"><label class="form-label">JS avant &lt;/body&gt;</label><textarea name="custom_js_body" class="form-control" rows="3"><?= htmlspecialchars($p['custom_js_body']) ?></textarea></div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'invoices'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Factures & Devis</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">En-tête facture (HTML autorisé)</label><textarea name="invoice_header" class="form-control" rows="3"><?= htmlspecialchars($p['invoice_header']) ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Conditions de paiement</label><textarea name="invoice_payment_terms" class="form-control" rows="3"><?= htmlspecialchars($p['invoice_payment_terms']) ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Numéro d'entreprise / ID</label><input type="text" name="invoice_company_number" class="form-control" value="<?= htmlspecialchars($p['invoice_company_number']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Texte bas de facture</label><textarea name="invoice_footer_message" class="form-control" rows="2"><?= htmlspecialchars($p['invoice_footer_message']) ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Mentions légales</label><textarea name="invoice_legal_notice" class="form-control" rows="2"><?= htmlspecialchars($p['invoice_legal_notice']) ?></textarea></div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'orders'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Paramètres d'impression des commandes</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="order_print_show_client_notes" id="order_print_show_client_notes" value="1" <?= $p['order_print_show_client_notes'] ? 'checked' : '' ?> />
                        <label class="form-check-label" for="order_print_show_client_notes">Afficher les notes client sur l'impression</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="order_print_show_payement_proof" id="order_print_show_payement_proof" value="1" <?= $p['order_print_show_payement_proof'] ? 'checked' : '' ?> />
                        <label class="form-check-label" for="order_print_show_payement_proof">Afficher la preuve de paiement</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="order_print_show_qr_code" id="order_print_show_qr_code" value="1" <?= $p['order_print_show_qr_code'] ? 'checked' : '' ?> />
                        <label class="form-check-label" for="order_print_show_qr_code">Afficher le QR Code de paiement/commande</label>
                    </div>
                    <div class="mb-3"><label class="form-label">CSS d'impression personnalisé</label><textarea name="order_print_custom_css" class="form-control" rows="4"><?= htmlspecialchars($p['order_print_custom_css']) ?></textarea></div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <div class="d-flex justify-content-end mt-4">
        <button name="sauvegarder" class="btn btn-primary">Sauvegarder</button>
    </div>
</form>

<script>
// Simple live preview helpers for color inputs
['header_bg_color','header_text_color','footer_bg_color','footer_text_color'].forEach(id=>{
    const el=document.getElementById(id);
    if(!el) return;
    el.addEventListener('input', ()=>{
        const inpText = el.nextElementSibling;
        if(inpText) inpText.value = el.value;
    });
});
</script>
<script>
// Rich live preview: colors, announcement, logo file preview, whatsapp
function qs(id){return document.getElementById(id);}

function updateColors(){
    const hb = qs('header_bg_color')?.value;
    const ht = qs('header_text_color')?.value;
    const fb = qs('footer_bg_color')?.value;
    const ft = qs('footer_text_color')?.value;
    if(qs('preview_header')){ if(hb) qs('preview_header').style.background = hb; if(ht) qs('preview_header').style.color = ht; }
    if(qs('preview_footer')){ if(fb) qs('preview_footer').style.background = fb; if(ft) qs('preview_footer').style.color = ft; }
}

function updateAnnonce(){
    const active = qs('header_announcement_active')?.checked;
    const txt = document.querySelector('input[name="header_announcement"]')?.value || '';
    const el = qs('preview_annonce');
    if(!el) return;
    if(active && txt.trim()){ el.classList.remove('d-none'); el.textContent = txt; } else { el.classList.add('d-none'); }
}

function updateWhatsApp(){
    const active = qs('whatsapp_float_active')?.checked;
    const num = document.querySelector('input[name="whatsapp_float_number"]')?.value || '';
    const el = qs('preview_whatsapp');
    if(!el) return;
    if(active && num.trim()){ el.classList.remove('d-none'); el.href = `https://wa.me/${encodeURIComponent(num)}?text=${encodeURIComponent(document.querySelector('input[name="whatsapp_float_message"]')?.value||'')}`; }
    else el.classList.add('d-none');
}

// Logo file preview
const logoInput = document.querySelector('input[name="logo_file"]');
if(logoInput){
    logoInput.addEventListener('change', (e)=>{
        const f = e.target.files && e.target.files[0];
        if(!f) return;
        const reader = new FileReader();
        reader.onload = function(ev){ const img = qs('preview_logo'); if(img){ img.src = ev.target.result; img.style.display = 'inline-block'; } };
        reader.readAsDataURL(f);
    });
}

['header_bg_color','header_text_color','footer_bg_color','footer_text_color'].forEach(id=>{ const el=qs(id); if(el) el.addEventListener('input', updateColors); });
['header_announcement_active','whatsapp_float_active'].forEach(id=>{ const el=qs(id); if(el) el.addEventListener('change', ()=>{ updateAnnonce(); updateWhatsApp(); }); });
document.querySelectorAll('input[name="header_announcement"], input[name="whatsapp_float_number"], input[name="whatsapp_float_message"]').forEach(i=>i&&i.addEventListener('input', ()=>{ updateAnnonce(); updateWhatsApp(); }));

// Initial sync
updateColors(); updateAnnonce(); updateWhatsApp();
</script>
