<?php
/**
 * BERRADI PRINT - Détail Devis avec Impression
 */
$db = getDB();
$devis_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM devis WHERE id = ?");
$stmt->execute([$devis_id]);
$dv = $stmt->fetch();
if (!$dv) { echo '<div class="alert alert-danger">Devis non trouvé.</div>'; return; }

$lignes = json_decode($dv['lignes'], true) ?: [];
$statuts_devis = ['brouillon' => 'secondary', 'envoye' => 'info', 'accepte' => 'success', 'refuse' => 'danger', 'expire' => 'warning'];

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['changer_statut'])) {
        $ns = clean($_POST['nouveau_statut']);
        $db->prepare("UPDATE devis SET statut = ? WHERE id = ?")->execute([$ns, $devis_id]);
        setFlash('success', 'Statut du devis mis à jour.');
        redirect('index.php?page=devis_detail&id=' . $devis_id);
    }
}

// Reload after update
$stmt = $db->prepare("SELECT * FROM devis WHERE id = ?");
$stmt->execute([$devis_id]);
$dv = $stmt->fetch();

// Document settings
$_doc_color = getParametre('doc_couleur_primaire', '#2563eb');
$_doc_logo = getParametre('doc_logo', '');
$_doc_nom = getParametre('doc_entreprise_nom', APP_NAME);
$_doc_adresse = getParametre('doc_entreprise_adresse', '');
$_doc_ville = getParametre('doc_entreprise_ville', '');
$_doc_tel = getParametre('doc_entreprise_tel', APP_PHONE);
$_doc_email = getParametre('doc_entreprise_email', APP_EMAIL);
$_doc_ice = getParametre('doc_entreprise_ice', '');
$_doc_rc = getParametre('doc_entreprise_rc', '');
$_doc_show_tva = getParametre('doc_show_tva', '1');
$_doc_show_ice = getParametre('doc_show_ice', '1');
$_doc_mention = getParametre('doc_mention_devis', 'Ce devis est valable 30 jours à compter de sa date d\'émission.');
$_doc_footer = getParametre('doc_footer_text', 'Merci pour votre confiance !');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php?page=devis" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour aux devis</a>
        <h4 class="fw-bold mb-0">
            Devis #<?= htmlspecialchars($dv['numero_devis']) ?>
            <span class="badge bg-<?= $statuts_devis[$dv['statut']] ?? 'secondary' ?> ms-2"><?= ucfirst($dv['statut']) ?></span>
        </h4>
    </div>
    <div class="d-flex gap-2">
        <button onclick="imprimerDevis()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimer
        </button>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Client -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Client</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Nom:</strong> <?= htmlspecialchars($dv['client_nom']) ?></p>
                        <p class="mb-1"><strong>Téléphone:</strong> <a href="tel:<?= $dv['client_telephone'] ?>"><?= htmlspecialchars($dv['client_telephone']) ?></a></p>
                        <p class="mb-0"><strong>Email:</strong> <?= $dv['client_email'] ? htmlspecialchars($dv['client_email']) : '-' ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Date:</strong> <?= dateFormatFr($dv['created_at'], 'long') ?></p>
                        <p class="mb-1"><strong>Validité:</strong> <?= $dv['date_validite'] ? dateFormatFr($dv['date_validite'], 'court') : '-' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lignes -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-list-check me-2"></i>Lignes du devis</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="bg-light">
                        <tr><th>Désignation</th><th class="text-center" style="width:80px">Qté</th><th class="text-end" style="width:120px">Prix unit.</th><th class="text-end" style="width:120px">Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['designation'] ?? '') ?></td>
                            <td class="text-center"><?= $l['quantite'] ?? 0 ?></td>
                            <td class="text-end"><?= formatPrix($l['prix_unitaire'] ?? 0) ?></td>
                            <td class="text-end fw-bold"><?= formatPrix($l['total'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr><td colspan="3" class="text-end">Sous-total HT</td><td class="text-end fw-bold"><?= formatPrix($dv['sous_total']) ?></td></tr>
                        <?php if ($dv['tva_montant'] > 0): ?>
                        <tr><td colspan="3" class="text-end">TVA (20%)</td><td class="text-end"><?= formatPrix($dv['tva_montant']) ?></td></tr>
                        <?php endif; ?>
                        <tr><td colspan="3" class="text-end fw-bold fs-5">Total TTC</td><td class="text-end fw-bold fs-5 text-primary"><?= formatPrix($dv['total']) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if ($dv['notes']): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-sticky me-2"></i>Notes</div>
            <div class="card-body"><p class="mb-0"><?= nl2br(htmlspecialchars($dv['notes'])) ?></p></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Changer le statut</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <select name="nouveau_statut" class="form-select mb-2">
                        <option value="brouillon" <?= $dv['statut'] === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="envoye" <?= $dv['statut'] === 'envoye' ? 'selected' : '' ?>>Envoyé</option>
                        <option value="accepte" <?= $dv['statut'] === 'accepte' ? 'selected' : '' ?>>Accepté</option>
                        <option value="refuse" <?= $dv['statut'] === 'refuse' ? 'selected' : '' ?>>Refusé</option>
                        <option value="expire" <?= $dv['statut'] === 'expire' ? 'selected' : '' ?>>Expiré</option>
                    </select>
                    <button type="submit" name="changer_statut" class="btn btn-primary btn-sm w-100">Mettre à jour</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-info-circle me-2"></i>Informations</div>
            <div class="card-body small">
                <p class="mb-1"><strong>Numéro:</strong> <?= htmlspecialchars($dv['numero_devis']) ?></p>
                <p class="mb-1"><strong>Créé le:</strong> <?= dateFormatFr($dv['created_at'], 'complet') ?></p>
                <p class="mb-1"><strong>Modifié:</strong> <?= dateFormatFr($dv['updated_at'], 'complet') ?></p>
                <p class="mb-0"><strong>Montant:</strong> <span class="text-primary fw-bold"><?= formatPrix($dv['total']) ?></span></p>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button onclick="imprimerDevis()" class="btn btn-outline-primary">
                <i class="bi bi-printer me-1"></i>Imprimer le devis
            </button>
        </div>
    </div>
</div>

<!-- Print Template (hidden) -->
<div id="printDevis" style="display:none;">
<div style="max-width:800px;margin:auto;padding:30px;font-family:Poppins,Arial,sans-serif;font-size:13px;color:#333;">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:25px;border-bottom:3px solid <?= $_doc_color ?>;padding-bottom:15px;">
        <div>
            <?php if ($_doc_logo): ?>
            <img src="../<?= htmlspecialchars($_doc_logo) ?>" alt="Logo" style="max-height:55px;margin-bottom:8px;"><br>
            <?php endif; ?>
            <strong style="font-size:16px;color:<?= $_doc_color ?>;"><?= htmlspecialchars($_doc_nom) ?></strong><br>
            <?php if ($_doc_adresse): ?><small><?= htmlspecialchars($_doc_adresse) ?></small><br><?php endif; ?>
            <?php if ($_doc_ville): ?><small><?= htmlspecialchars($_doc_ville) ?></small><br><?php endif; ?>
            <small>Tél: <?= htmlspecialchars($_doc_tel) ?></small>
            <?php if ($_doc_email): ?><br><small><?= htmlspecialchars($_doc_email) ?></small><?php endif; ?>
        </div>
        <div style="text-align:right;">
            <h2 style="color:<?= $_doc_color ?>;margin:0;font-size:24px;">DEVIS</h2>
            <p style="margin:5px 0;font-size:12px;">
                N° <strong><?= htmlspecialchars($dv['numero_devis']) ?></strong><br>
                Date: <?= dateFormatFr($dv['created_at'], 'court') ?><br>
                <?php if ($dv['date_validite']): ?>Validité: <?= dateFormatFr($dv['date_validite'], 'court') ?><?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Client -->
    <div style="background:#f8f9fa;padding:12px;border-radius:5px;margin-bottom:20px;">
        <strong style="color:<?= $_doc_color ?>;font-size:11px;text-transform:uppercase;">Client</strong><br>
        <strong><?= htmlspecialchars($dv['client_nom']) ?></strong><br>
        <small>Tél: <?= htmlspecialchars($dv['client_telephone']) ?></small>
        <?php if ($dv['client_email']): ?><br><small><?= htmlspecialchars($dv['client_email']) ?></small><?php endif; ?>
    </div>

    <!-- Table -->
    <table style="width:100%;border-collapse:collapse;margin-bottom:15px;">
        <thead>
            <tr style="background:<?= $_doc_color ?>;color:#fff;">
                <th style="padding:8px;text-align:left;font-size:12px;">Désignation</th>
                <th style="padding:8px;text-align:center;width:70px;font-size:12px;">Qté</th>
                <th style="padding:8px;text-align:right;width:110px;font-size:12px;">P.U. HT</th>
                <th style="padding:8px;text-align:right;width:110px;font-size:12px;">Total HT</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lignes as $l): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:8px;"><?= htmlspecialchars($l['designation'] ?? '') ?></td>
                <td style="padding:8px;text-align:center;"><?= $l['quantite'] ?? 0 ?></td>
                <td style="padding:8px;text-align:right;"><?= formatPrix($l['prix_unitaire'] ?? 0) ?></td>
                <td style="padding:8px;text-align:right;font-weight:bold;"><?= formatPrix($l['total'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div style="text-align:right;margin-bottom:20px;">
        <table style="margin-left:auto;font-size:13px;">
            <tr><td style="padding:4px 15px;">Sous-total HT</td><td style="padding:4px;font-weight:bold;"><?= formatPrix($dv['sous_total']) ?></td></tr>
            <?php if ($_doc_show_tva && $dv['tva_montant'] > 0): ?>
            <tr><td style="padding:4px 15px;">TVA (20%)</td><td style="padding:4px;"><?= formatPrix($dv['tva_montant']) ?></td></tr>
            <?php endif; ?>
            <tr style="background:<?= $_doc_color ?>;color:#fff;">
                <td style="padding:8px 15px;font-weight:bold;">Total TTC</td>
                <td style="padding:8px;font-weight:bold;font-size:16px;"><?= formatPrix($dv['total']) ?></td>
            </tr>
        </table>
    </div>

    <!-- Mentions -->
    <div style="border-top:1px solid #ddd;padding-top:12px;font-size:11px;color:#666;">
        <p style="margin:0 0 5px;"><?= nl2br(htmlspecialchars($_doc_mention)) ?></p>
        <?php if ($_doc_show_ice && $_doc_ice): ?>
        <p style="margin:0 0 5px;">ICE: <?= htmlspecialchars($_doc_ice) ?> <?php if ($_doc_rc): ?>| RC: <?= htmlspecialchars($_doc_rc) ?><?php endif; ?></p>
        <?php endif; ?>
        <p style="text-align:center;margin-top:10px;color:<?= $_doc_color ?>;font-weight:600;"><?= htmlspecialchars($_doc_footer) ?></p>
    </div>

    <!-- Signature -->
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

<script>
function imprimerDevis() {
    var printContent = document.getElementById('printDevis').innerHTML;
    var w = window.open('', '_blank', 'width=900,height=700');
    w.document.write('<html><head><title>Devis #<?= $dv['numero_devis'] ?></title>');
    w.document.write('<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">');
    w.document.write('<style>body{margin:0;padding:20px;} @media print{body{padding:0;} @page{margin:15mm;}}</style>');
    w.document.write('</head><body>');
    w.document.write(printContent);
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(function(){ w.print(); }, 500);
}
</script>
