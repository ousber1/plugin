<?php
/**
 * BERRADI PRINT - Customizer Facture & Devis
 */
$db = getDB();
$tab = $_GET['tab'] ?? 'entreprise';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save_settings'])) {
        $save_tab = $_POST['_tab'] ?? 'entreprise';

        // Tab-aware key groups
        $tab_keys = [
            'entreprise' => [
                'doc_entreprise_nom', 'doc_entreprise_adresse', 'doc_entreprise_ville',
                'doc_entreprise_tel', 'doc_entreprise_email', 'doc_entreprise_ice',
                'doc_entreprise_rc', 'doc_entreprise_if', 'doc_entreprise_patente',
            ],
            'apparence' => [
                'doc_couleur_primaire', 'doc_couleur_secondaire',
                'doc_show_tva', 'doc_show_ice',
            ],
            'mentions' => [
                'doc_mention_devis', 'doc_mention_facture',
                'doc_conditions_paiement', 'doc_footer_text',
            ],
        ];

        $keys = $tab_keys[$save_tab] ?? [];
        foreach ($keys as $k) {
            setParametre($k, $_POST[$k] ?? '');
        }

        // Logo upload (only on entreprise tab)
        if ($save_tab === 'entreprise' && !empty($_FILES['doc_logo']['name']) && $_FILES['doc_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['doc_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $old_logo = getParametre('doc_logo');
                if ($old_logo && file_exists(__DIR__ . '/../../' . $old_logo)) {
                    @unlink(__DIR__ . '/../../' . $old_logo);
                }
                $logo_name = 'doc_logo_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['doc_logo']['tmp_name'], $upload_dir . $logo_name);
                setParametre('doc_logo', 'uploads/logos/' . $logo_name);
            }
        }

        // Delete logo
        if ($save_tab === 'entreprise' && isset($_POST['supprimer_logo'])) {
            $old_logo = getParametre('doc_logo');
            if ($old_logo && file_exists(__DIR__ . '/../../' . $old_logo)) {
                @unlink(__DIR__ . '/../../' . $old_logo);
            }
            setParametre('doc_logo', '');
        }

        setFlash('success', 'Paramètres sauvegardés avec succès.');
        redirect('index.php?page=facture_devis&tab=' . urlencode($save_tab));
    }
}

// Load all values
$d = [];
$all_keys = [
    'doc_logo', 'doc_entreprise_nom', 'doc_entreprise_adresse', 'doc_entreprise_ville',
    'doc_entreprise_tel', 'doc_entreprise_email', 'doc_entreprise_ice',
    'doc_entreprise_rc', 'doc_entreprise_if', 'doc_entreprise_patente',
    'doc_couleur_primaire', 'doc_couleur_secondaire',
    'doc_show_tva', 'doc_show_ice',
    'doc_mention_devis', 'doc_mention_facture',
    'doc_conditions_paiement', 'doc_footer_text',
];
foreach ($all_keys as $k) {
    $d[$k] = getParametre($k, '');
}
// Defaults
if (!$d['doc_entreprise_nom']) $d['doc_entreprise_nom'] = APP_NAME;
if (!$d['doc_entreprise_tel']) $d['doc_entreprise_tel'] = APP_PHONE;
if (!$d['doc_entreprise_email']) $d['doc_entreprise_email'] = APP_EMAIL;
if (!$d['doc_couleur_primaire']) $d['doc_couleur_primaire'] = '#2563eb';
if (!$d['doc_couleur_secondaire']) $d['doc_couleur_secondaire'] = '#1e40af';
$_color = $d['doc_couleur_primaire'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-ruled me-2"></i>Factures & Devis</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $tab === 'entreprise' ? 'active' : '' ?>" href="?page=facture_devis&tab=entreprise"><i class="bi bi-building me-1"></i>Entreprise</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'apparence' ? 'active' : '' ?>" href="?page=facture_devis&tab=apparence"><i class="bi bi-palette me-1"></i>Apparence</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'mentions' ? 'active' : '' ?>" href="?page=facture_devis&tab=mentions"><i class="bi bi-card-text me-1"></i>Mentions légales</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'preview' ? 'active' : '' ?>" href="?page=facture_devis&tab=preview"><i class="bi bi-eye me-1"></i>Aperçu</a></li>
</ul>

<?php if ($tab !== 'preview'): ?>
<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="_tab" value="<?= htmlspecialchars($tab) ?>">
<?php endif; ?>

<?php if ($tab === 'entreprise'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-image me-2"></i>Logo de l'entreprise</div>
                <div class="card-body">
                    <?php if ($d['doc_logo']): ?>
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <img src="../<?= htmlspecialchars($d['doc_logo']) ?>" alt="Logo" class="rounded border p-2" style="max-height:80px;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="supprimer_logo" id="supprimer_logo" value="1">
                            <label class="form-check-label text-danger small" for="supprimer_logo">Supprimer le logo</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="doc_logo" class="form-control" accept="image/*">
                    <small class="text-muted">Ce logo sera affiché sur les devis et factures. Format: PNG transparent, 300x100px</small>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-geo-alt me-2"></i>Coordonnées de l'entreprise</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nom / Raison sociale *</label>
                            <input type="text" name="doc_entreprise_nom" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_nom']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="doc_entreprise_tel" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_tel']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Adresse</label>
                            <input type="text" name="doc_entreprise_adresse" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_adresse']) ?>" placeholder="Rue, numéro...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ville, Pays</label>
                            <input type="text" name="doc_entreprise_ville" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_ville']) ?>" placeholder="Casablanca, Maroc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="doc_entreprise_email" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_email']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-bank me-2"></i>Identifiants fiscaux (Maroc)</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ICE <small class="text-muted">(Identifiant Commun)</small></label>
                            <input type="text" name="doc_entreprise_ice" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_ice']) ?>" placeholder="000000000000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RC <small class="text-muted">(Registre Commerce)</small></label>
                            <input type="text" name="doc_entreprise_rc" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_rc']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IF <small class="text-muted">(Identifiant Fiscal)</small></label>
                            <input type="text" name="doc_entreprise_if" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_if']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Patente</label>
                            <input type="text" name="doc_entreprise_patente" class="form-control" value="<?= htmlspecialchars($d['doc_entreprise_patente']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-lightbulb text-warning me-2"></i>Aide</h6>
                    <p class="small text-muted">Ces informations apparaîtront sur vos devis et factures.</p>
                    <p class="small text-muted">L'<strong>ICE</strong> est obligatoire pour les factures au Maroc.</p>
                    <p class="small text-muted mb-0">Allez dans l'onglet <strong>Aperçu</strong> pour voir le rendu final de vos documents.</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'apparence'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-palette me-2"></i>Couleurs du document</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Couleur primaire</label>
                            <input type="color" name="doc_couleur_primaire" id="docColor1" class="form-control form-control-color w-100" value="<?= $d['doc_couleur_primaire'] ?>" style="height:50px;">
                            <small class="text-muted">En-tête, titres, bordures</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Couleur secondaire</label>
                            <input type="color" name="doc_couleur_secondaire" id="docColor2" class="form-control form-control-color w-100" value="<?= $d['doc_couleur_secondaire'] ?>" style="height:50px;">
                            <small class="text-muted">Accents, survol</small>
                        </div>
                    </div>
                    <!-- Live color preview -->
                    <div class="mt-3 p-3 rounded" id="colorPreview" style="background:<?= $d['doc_couleur_primaire'] ?>;color:#fff;">
                        <strong><?= htmlspecialchars($d['doc_entreprise_nom']) ?></strong> - Aperçu des couleurs
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-toggles me-2"></i>Options d'affichage</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="doc_show_tva" id="doc_show_tva" value="1" <?= ($d['doc_show_tva'] !== '' ? $d['doc_show_tva'] : '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="doc_show_tva">
                            <strong>Afficher la TVA (20%)</strong>
                            <br><small class="text-muted">Affiche la ligne TVA sur les devis et factures</small>
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="doc_show_ice" id="doc_show_ice" value="1" <?= ($d['doc_show_ice'] !== '' ? $d['doc_show_ice'] : '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="doc_show_ice">
                            <strong>Afficher l'ICE</strong>
                            <br><small class="text-muted">Affiche l'ICE en pied de document</small>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-lightbulb text-warning me-2"></i>Conseil</h6>
                    <p class="small text-muted mb-0">Choisissez une couleur primaire qui correspond à votre identité visuelle. Elle sera utilisée pour les en-têtes et les totaux des documents.</p>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('docColor1').addEventListener('input', function() {
        document.getElementById('colorPreview').style.background = this.value;
    });
    </script>

<?php elseif ($tab === 'mentions'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-file-text me-2"></i>Mentions sur les devis</div>
                <div class="card-body">
                    <textarea name="doc_mention_devis" class="form-control" rows="4" placeholder="Ce devis est valable 30 jours..."><?= htmlspecialchars($d['doc_mention_devis'] ?: 'Ce devis est valable 30 jours à compter de sa date d\'émission. Tout devis signé et retourné vaut bon de commande.') ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-receipt me-2"></i>Mentions sur les factures</div>
                <div class="card-body">
                    <textarea name="doc_mention_facture" class="form-control" rows="4" placeholder="Paiement à réception..."><?= htmlspecialchars($d['doc_mention_facture'] ?: 'Paiement à réception de la facture. Tout retard de paiement entraînera des pénalités conformément à la loi en vigueur.') ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-cash-stack me-2"></i>Conditions de paiement</div>
                <div class="card-body">
                    <textarea name="doc_conditions_paiement" class="form-control" rows="3" placeholder="Modes de paiement..."><?= htmlspecialchars($d['doc_conditions_paiement'] ?: 'Modes de paiement acceptés: espèces, virement bancaire, chèque.') ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-chat-square-quote me-2"></i>Pied de page</div>
                <div class="card-body">
                    <textarea name="doc_footer_text" class="form-control" rows="2" placeholder="Merci pour votre confiance !"><?= htmlspecialchars($d['doc_footer_text'] ?: 'Merci pour votre confiance !') ?></textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-lightbulb text-warning me-2"></i>Aide</h6>
                    <p class="small text-muted">Les mentions légales sont automatiquement ajoutées en bas de vos documents.</p>
                    <p class="small text-muted mb-0">Le pied de page s'affiche centré en couleur primaire.</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'preview'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-eye me-2"></i>Aperçu du document</span>
                    <button onclick="imprimerApercu()" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer me-1"></i>Tester l'impression</button>
                </div>
                <div class="card-body p-4" style="background:#f0f0f0;">
                    <div id="docPreview" style="max-width:800px;margin:auto;border:1px solid #ccc;padding:40px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.1);font-family:Poppins,Arial,sans-serif;font-size:13px;color:#333;">
                        <!-- Header -->
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:25px;border-bottom:3px solid <?= $_color ?>;padding-bottom:15px;">
                            <div>
                                <?php if ($d['doc_logo']): ?>
                                <img src="../<?= htmlspecialchars($d['doc_logo']) ?>" alt="Logo" style="max-height:55px;margin-bottom:8px;"><br>
                                <?php endif; ?>
                                <strong style="font-size:16px;color:<?= $_color ?>;"><?= htmlspecialchars($d['doc_entreprise_nom']) ?></strong><br>
                                <?php if ($d['doc_entreprise_adresse']): ?><small><?= htmlspecialchars($d['doc_entreprise_adresse']) ?></small><br><?php endif; ?>
                                <?php if ($d['doc_entreprise_ville']): ?><small><?= htmlspecialchars($d['doc_entreprise_ville']) ?></small><br><?php endif; ?>
                                <small>Tél: <?= htmlspecialchars($d['doc_entreprise_tel']) ?></small>
                                <?php if ($d['doc_entreprise_email']): ?><br><small><?= htmlspecialchars($d['doc_entreprise_email']) ?></small><?php endif; ?>
                            </div>
                            <div style="text-align:right;">
                                <h2 style="color:<?= $_color ?>;margin:0;font-size:24px;">DEVIS</h2>
                                <p style="margin:5px 0;font-size:12px;">
                                    N° <strong>DV-<?= date('Ym') ?>-0001</strong><br>
                                    Date: <?= date('d/m/Y') ?><br>
                                    Validité: <?= date('d/m/Y', strtotime('+30 days')) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Client -->
                        <div style="display:flex;gap:20px;margin-bottom:20px;">
                            <div style="flex:1;background:#f8f9fa;padding:12px;border-radius:5px;">
                                <strong style="color:<?= $_color ?>;font-size:11px;text-transform:uppercase;">Client</strong><br>
                                <strong>Mohammed Exemple</strong><br>
                                <small>Tél: +212 600-000000</small><br>
                                <small>contact@client.ma</small>
                            </div>
                        </div>

                        <!-- Table -->
                        <table style="width:100%;border-collapse:collapse;margin-bottom:15px;">
                            <thead>
                                <tr style="background:<?= $_color ?>;color:#fff;">
                                    <th style="padding:8px;text-align:left;font-size:12px;">Désignation</th>
                                    <th style="padding:8px;text-align:center;width:70px;font-size:12px;">Qté</th>
                                    <th style="padding:8px;text-align:right;width:110px;font-size:12px;">P.U. HT</th>
                                    <th style="padding:8px;text-align:right;width:110px;font-size:12px;">Total HT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:8px;">Cartes de visite 300g recto/verso</td>
                                    <td style="padding:8px;text-align:center;">500</td>
                                    <td style="padding:8px;text-align:right;">0,80 DH</td>
                                    <td style="padding:8px;text-align:right;font-weight:bold;">400,00 DH</td>
                                </tr>
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:8px;">Flyers A5 135g</td>
                                    <td style="padding:8px;text-align:center;">1 000</td>
                                    <td style="padding:8px;text-align:right;">0,50 DH</td>
                                    <td style="padding:8px;text-align:right;font-weight:bold;">500,00 DH</td>
                                </tr>
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:8px;">Roll-up 85x200cm bâche 400g</td>
                                    <td style="padding:8px;text-align:center;">2</td>
                                    <td style="padding:8px;text-align:right;">350,00 DH</td>
                                    <td style="padding:8px;text-align:right;font-weight:bold;">700,00 DH</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Totals -->
                        <div style="text-align:right;margin-bottom:20px;">
                            <table style="margin-left:auto;font-size:13px;">
                                <tr><td style="padding:4px 15px;">Sous-total HT</td><td style="padding:4px;font-weight:bold;">1 600,00 DH</td></tr>
                                <?php if ($d['doc_show_tva'] !== '0'): ?>
                                <tr><td style="padding:4px 15px;">TVA (20%)</td><td style="padding:4px;">320,00 DH</td></tr>
                                <?php endif; ?>
                                <tr style="background:<?= $_color ?>;color:#fff;">
                                    <td style="padding:8px 15px;font-weight:bold;">Total TTC</td>
                                    <td style="padding:8px;font-weight:bold;font-size:16px;"><?= $d['doc_show_tva'] !== '0' ? '1 920,00' : '1 600,00' ?> DH</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Footer -->
                        <div style="border-top:1px solid #ddd;padding-top:12px;font-size:11px;color:#666;">
                            <p style="margin:0 0 5px;"><?= nl2br(htmlspecialchars($d['doc_mention_devis'] ?: 'Ce devis est valable 30 jours à compter de sa date d\'émission.')) ?></p>
                            <?php if ($d['doc_conditions_paiement']): ?>
                            <p style="margin:0 0 5px;"><?= nl2br(htmlspecialchars($d['doc_conditions_paiement'])) ?></p>
                            <?php endif; ?>
                            <?php if ($d['doc_show_ice'] !== '0' && $d['doc_entreprise_ice']): ?>
                            <p style="margin:0 0 5px;">ICE: <?= htmlspecialchars($d['doc_entreprise_ice']) ?>
                            <?php if ($d['doc_entreprise_rc']): ?> | RC: <?= htmlspecialchars($d['doc_entreprise_rc']) ?><?php endif; ?>
                            <?php if ($d['doc_entreprise_if']): ?> | IF: <?= htmlspecialchars($d['doc_entreprise_if']) ?><?php endif; ?>
                            </p>
                            <?php endif; ?>
                            <p style="text-align:center;margin-top:10px;color:<?= $_color ?>;font-weight:600;"><?= htmlspecialchars($d['doc_footer_text'] ?: 'Merci pour votre confiance !') ?></p>
                        </div>

                        <!-- Signatures -->
                        <div style="display:flex;justify-content:space-between;margin-top:30px;font-size:12px;">
                            <div style="width:45%;border-top:1px solid #ccc;padding-top:8px;text-align:center;">
                                <strong>Signature du fournisseur</strong>
                            </div>
                            <div style="width:45%;border-top:1px solid #ccc;padding-top:8px;text-align:center;">
                                <strong>Signature du client</strong><br>
                                <small>(Bon pour accord)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-check-circle text-success me-2"></i>Résumé</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <i class="bi bi-<?= $d['doc_logo'] ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                            Logo <?= $d['doc_logo'] ? 'configuré' : 'non configuré' ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-<?= $d['doc_entreprise_adresse'] ? 'check-circle text-success' : 'x-circle text-danger' ?> me-2"></i>
                            Adresse <?= $d['doc_entreprise_adresse'] ? 'renseignée' : 'non renseignée' ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-<?= $d['doc_entreprise_ice'] ? 'check-circle text-success' : 'exclamation-circle text-warning' ?> me-2"></i>
                            ICE <?= $d['doc_entreprise_ice'] ? 'configuré' : 'non configuré' ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-circle-fill me-2" style="color:<?= $_color ?>;font-size:10px;"></i>
                            Couleur: <?= $_color ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-<?= $d['doc_show_tva'] !== '0' ? 'check-circle text-success' : 'x-circle text-secondary' ?> me-2"></i>
                            TVA <?= $d['doc_show_tva'] !== '0' ? 'affichée' : 'masquée' ?>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="small text-muted mb-2">Ce document est utilisé pour :</p>
                    <ul class="small text-muted mb-0">
                        <li>Impression des commandes</li>
                        <li>Impression des devis</li>
                        <li>Génération de factures</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script>
    function imprimerApercu() {
        var content = document.getElementById('docPreview').innerHTML;
        var w = window.open('', '_blank', 'width=900,height=700');
        w.document.write('<html><head><title>Aperçu Document</title>');
        w.document.write('<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">');
        w.document.write('<style>body{margin:0;padding:20px;font-family:Poppins,sans-serif;} @media print{body{padding:0;} @page{margin:15mm;}}</style>');
        w.document.write('</head><body>');
        w.document.write(content);
        w.document.write('</body></html>');
        w.document.close();
        setTimeout(function(){ w.print(); }, 500);
    }
    </script>
<?php endif; ?>

<?php if ($tab !== 'preview'): ?>
    <div class="mt-4">
        <button type="submit" name="save_settings" class="btn btn-primary btn-lg">
            <i class="bi bi-check-circle me-2"></i>Sauvegarder
        </button>
        <a href="?page=facture_devis&tab=preview" class="btn btn-outline-secondary btn-lg ms-2">
            <i class="bi bi-eye me-1"></i>Voir l'aperçu
        </a>
    </div>
</form>
<?php endif; ?>
