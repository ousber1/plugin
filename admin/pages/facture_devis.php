<?php
/**
 * BERRADI PRINT - Customizer Facture & Devis avec Logo
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save_settings'])) {
        $keys = [
            'doc_entreprise_nom', 'doc_entreprise_adresse', 'doc_entreprise_ville',
            'doc_entreprise_tel', 'doc_entreprise_email', 'doc_entreprise_ice',
            'doc_entreprise_rc', 'doc_entreprise_if', 'doc_entreprise_patente',
            'doc_couleur_primaire', 'doc_couleur_secondaire',
            'doc_mention_devis', 'doc_mention_facture', 'doc_conditions_paiement',
            'doc_show_tva', 'doc_show_ice', 'doc_footer_text'
        ];
        foreach ($keys as $k) {
            setParametre($k, $_POST[$k] ?? '');
        }

        // Logo upload
        if (!empty($_FILES['doc_logo']['name']) && $_FILES['doc_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['doc_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $upload_dir = __DIR__ . '/../../uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $old_logo = getParametre('doc_logo');
                if ($old_logo && file_exists(__DIR__ . '/../../' . $old_logo)) {
                    unlink(__DIR__ . '/../../' . $old_logo);
                }
                $logo_name = 'doc_logo_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['doc_logo']['tmp_name'], $upload_dir . $logo_name);
                setParametre('doc_logo', 'uploads/logos/' . $logo_name);
            }
        }

        setFlash('success', 'Paramètres de facturation sauvegardés.');
        redirect('index.php?page=facture_devis');
    }
}

$tab = $_GET['tab'] ?? 'entreprise';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-ruled me-2"></i>Factures & Devis</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $tab === 'entreprise' ? 'active' : '' ?>" href="?page=facture_devis&tab=entreprise">Informations entreprise</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'apparence' ? 'active' : '' ?>" href="?page=facture_devis&tab=apparence">Apparence</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'mentions' ? 'active' : '' ?>" href="?page=facture_devis&tab=mentions">Mentions légales</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'preview' ? 'active' : '' ?>" href="?page=facture_devis&tab=preview">Aperçu</a></li>
</ul>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

<?php if ($tab === 'entreprise'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Logo de l'entreprise</div>
                <div class="card-body">
                    <?php $logo = getParametre('doc_logo'); ?>
                    <?php if ($logo): ?>
                    <div class="mb-3">
                        <img src="../<?= htmlspecialchars($logo) ?>" alt="Logo" class="rounded border p-2" style="max-height:80px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="doc_logo" class="form-control" accept="image/*">
                    <small class="text-muted">Ce logo sera affiché sur les devis et factures. Format recommandé: PNG transparent, 300x100px</small>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Coordonnées de l'entreprise</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom / Raison sociale *</label>
                            <input type="text" name="doc_entreprise_nom" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_nom', APP_NAME)) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="doc_entreprise_tel" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_tel', APP_PHONE)) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Adresse</label>
                            <input type="text" name="doc_entreprise_adresse" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_adresse')) ?>" placeholder="Rue, numéro...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ville</label>
                            <input type="text" name="doc_entreprise_ville" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_ville')) ?>" placeholder="Casablanca, Maroc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="doc_entreprise_email" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_email', APP_EMAIL)) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Identifiants fiscaux</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ICE (Identifiant Commun de l'Entreprise)</label>
                            <input type="text" name="doc_entreprise_ice" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_ice')) ?>" placeholder="000000000000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RC (Registre de Commerce)</label>
                            <input type="text" name="doc_entreprise_rc" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_rc')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IF (Identifiant Fiscal)</label>
                            <input type="text" name="doc_entreprise_if" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_if')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Patente</label>
                            <input type="text" name="doc_entreprise_patente" class="form-control" value="<?= htmlspecialchars(getParametre('doc_entreprise_patente')) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Aide</h6>
                    <p class="small text-muted">Ces informations apparaîtront sur vos devis et factures générés.</p>
                    <p class="small text-muted mb-0">L'ICE est obligatoire pour les factures au Maroc.</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'apparence'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Couleurs du document</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Couleur primaire (en-tête)</label>
                            <div class="input-group">
                                <input type="color" name="doc_couleur_primaire" class="form-control form-control-color" value="<?= getParametre('doc_couleur_primaire', '#2563eb') ?>">
                                <input type="text" class="form-control" value="<?= getParametre('doc_couleur_primaire', '#2563eb') ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Couleur secondaire (accents)</label>
                            <div class="input-group">
                                <input type="color" name="doc_couleur_secondaire" class="form-control form-control-color" value="<?= getParametre('doc_couleur_secondaire', '#1e40af') ?>">
                                <input type="text" class="form-control" value="<?= getParametre('doc_couleur_secondaire', '#1e40af') ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Options d'affichage</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="doc_show_tva" id="doc_show_tva" value="1" <?= getParametre('doc_show_tva', '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="doc_show_tva">Afficher la TVA sur les documents</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="doc_show_ice" id="doc_show_ice" value="1" <?= getParametre('doc_show_ice', '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="doc_show_ice">Afficher l'ICE sur les documents</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'mentions'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Mentions sur les devis</div>
                <div class="card-body">
                    <textarea name="doc_mention_devis" class="form-control" rows="4" placeholder="Ce devis est valable 30 jours à compter de sa date d'émission..."><?= htmlspecialchars(getParametre('doc_mention_devis', 'Ce devis est valable 30 jours à compter de sa date d\'émission. Tout devis signé et retourné vaut bon de commande.')) ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Mentions sur les factures</div>
                <div class="card-body">
                    <textarea name="doc_mention_facture" class="form-control" rows="4" placeholder="Paiement à réception de la facture..."><?= htmlspecialchars(getParametre('doc_mention_facture', 'Paiement à réception de la facture. Tout retard de paiement entraînera des pénalités conformément à la loi en vigueur.')) ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Conditions de paiement</div>
                <div class="card-body">
                    <textarea name="doc_conditions_paiement" class="form-control" rows="3" placeholder="Modes de paiement acceptés: espèces, virement bancaire, chèque..."><?= htmlspecialchars(getParametre('doc_conditions_paiement', 'Modes de paiement acceptés: espèces, virement bancaire, chèque.')) ?></textarea>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Pied de page du document</div>
                <div class="card-body">
                    <textarea name="doc_footer_text" class="form-control" rows="2" placeholder="Merci pour votre confiance !"><?= htmlspecialchars(getParametre('doc_footer_text', 'Merci pour votre confiance !')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'preview'): ?>
    <!-- PREVIEW -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div style="max-width:800px;margin:auto;border:1px solid #ddd;padding:40px;background:#fff;">
                <?php
                $color = getParametre('doc_couleur_primaire', '#2563eb');
                $logo = getParametre('doc_logo');
                $nom = getParametre('doc_entreprise_nom', APP_NAME);
                ?>
                <!-- Header -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px;border-bottom:3px solid <?= $color ?>;padding-bottom:20px;">
                    <div>
                        <?php if ($logo): ?>
                        <img src="../<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-height:60px;margin-bottom:10px;"><br>
                        <?php endif; ?>
                        <strong style="font-size:18px;color:<?= $color ?>;"><?= htmlspecialchars($nom) ?></strong><br>
                        <small><?= htmlspecialchars(getParametre('doc_entreprise_adresse')) ?></small><br>
                        <small><?= htmlspecialchars(getParametre('doc_entreprise_ville')) ?></small><br>
                        <small>Tél: <?= htmlspecialchars(getParametre('doc_entreprise_tel', APP_PHONE)) ?></small>
                    </div>
                    <div style="text-align:right;">
                        <h2 style="color:<?= $color ?>;margin:0;">DEVIS</h2>
                        <p style="margin:5px 0;">N° DV-202603-0001<br>
                        Date: <?= date('d/m/Y') ?><br>
                        Validité: <?= date('d/m/Y', strtotime('+30 days')) ?></p>
                    </div>
                </div>

                <!-- Client -->
                <div style="background:#f8f9fa;padding:15px;border-radius:5px;margin-bottom:20px;">
                    <strong>Client:</strong><br>
                    Mohammed Exemple<br>
                    <small>Tél: +212 600-000000</small>
                </div>

                <!-- Table -->
                <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                    <thead>
                        <tr style="background:<?= $color ?>;color:#fff;">
                            <th style="padding:10px;text-align:left;">Désignation</th>
                            <th style="padding:10px;text-align:center;width:80px;">Qté</th>
                            <th style="padding:10px;text-align:right;width:120px;">P.U. HT</th>
                            <th style="padding:10px;text-align:right;width:120px;">Total HT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;">Cartes de visite 300g recto/verso</td>
                            <td style="padding:10px;text-align:center;">500</td>
                            <td style="padding:10px;text-align:right;">0,80 DH</td>
                            <td style="padding:10px;text-align:right;">400,00 DH</td>
                        </tr>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;">Flyers A5 135g</td>
                            <td style="padding:10px;text-align:center;">1000</td>
                            <td style="padding:10px;text-align:right;">0,50 DH</td>
                            <td style="padding:10px;text-align:right;">500,00 DH</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Totals -->
                <div style="text-align:right;margin-bottom:20px;">
                    <table style="margin-left:auto;">
                        <tr><td style="padding:5px 20px;">Sous-total HT</td><td style="padding:5px;font-weight:bold;">900,00 DH</td></tr>
                        <?php if (getParametre('doc_show_tva', '1')): ?>
                        <tr><td style="padding:5px 20px;">TVA (20%)</td><td style="padding:5px;">180,00 DH</td></tr>
                        <?php endif; ?>
                        <tr style="background:<?= $color ?>;color:#fff;"><td style="padding:10px 20px;font-weight:bold;">Total TTC</td><td style="padding:10px;font-weight:bold;font-size:18px;">1 080,00 DH</td></tr>
                    </table>
                </div>

                <!-- Mentions -->
                <div style="border-top:1px solid #ddd;padding-top:15px;font-size:12px;color:#666;">
                    <p><?= nl2br(htmlspecialchars(getParametre('doc_mention_devis', 'Ce devis est valable 30 jours.'))) ?></p>
                    <?php if (getParametre('doc_show_ice', '1') && getParametre('doc_entreprise_ice')): ?>
                    <p>ICE: <?= htmlspecialchars(getParametre('doc_entreprise_ice')) ?></p>
                    <?php endif; ?>
                    <p style="text-align:center;margin-top:15px;color:<?= $color ?>;"><?= htmlspecialchars(getParametre('doc_footer_text', 'Merci pour votre confiance !')) ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <?php if ($tab !== 'preview'): ?>
    <div class="mt-4">
        <button type="submit" name="save_settings" class="btn btn-primary btn-lg">
            <i class="bi bi-check-circle me-2"></i>Sauvegarder
        </button>
    </div>
    <?php endif; ?>
</form>
