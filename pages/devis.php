<?php
/**
 * BERRADI PRINT - Demande de Devis
 */
$db = getDB();
$categories = $db->query("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();
$produits = $db->query("SELECT id, nom, categorie_id FROM produits WHERE actif = 1 ORDER BY nom")->fetchAll();
$succes = false;

// Pre-fill from logged-in customer
$_client_dv = null;
if (clientEstConnecte()) {
    $_client_dv = clientConnecte();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_devis'])) {
    verifyCsrf();

    $nom = clean($_POST['nom'] ?? '');
    $prenom = clean($_POST['prenom'] ?? '');
    $telephone = clean($_POST['telephone'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $entreprise = clean($_POST['entreprise'] ?? '');
    $adresse = clean($_POST['adresse'] ?? '');
    $ville = clean($_POST['ville'] ?? '');
    $service = clean($_POST['service'] ?? '');
    $produit_choisi = clean($_POST['produit_choisi'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $quantite = clean($_POST['quantite'] ?? '');
    $format = clean($_POST['format'] ?? '');
    $finition = clean($_POST['finition'] ?? '');
    $delai = clean($_POST['delai'] ?? '');
    $budget = clean($_POST['budget'] ?? '');

    if ($nom && $telephone && $description) {
        $numero_devis = genererNumeroDevis();
        $lignes = json_encode([
            ['service' => $service, 'produit' => $produit_choisi, 'description' => $description, 'quantite' => $quantite, 'format' => $format, 'finition' => $finition, 'budget' => $budget]
        ]);

        $notes = '';
        if ($entreprise) $notes .= "Entreprise: $entreprise\n";
        if ($adresse) $notes .= "Adresse: $adresse, $ville\n";
        if ($delai) $notes .= "Délai souhaité: $delai\n";
        if ($budget) $notes .= "Budget: $budget\n";

        // Link to client_id if logged in
        $client_id = null;
        if ($_client_dv) {
            $client_id = $_client_dv['id'];
        } else {
            // Try to find existing client by phone
            $stmt = $db->prepare("SELECT id FROM clients WHERE telephone = ?");
            $stmt->execute([$telephone]);
            $found = $stmt->fetch();
            if ($found) $client_id = $found['id'];
        }

        $stmt = $db->prepare("INSERT INTO devis (numero_devis, client_id, client_nom, client_telephone, client_email, lignes, sous_total, total, notes) VALUES (?,?,?,?,?,?,0,0,?)");
        $stmt->execute([$numero_devis, $client_id, "$prenom $nom", $telephone, $email, $lignes, trim($notes)]);

        creerNotification('devis', 'Nouvelle demande de devis #' . $numero_devis, "De $prenom $nom - $service");

        $succes = true;
        $_succes_numero = $numero_devis;
    }
}
?>

<section class="bg-primary py-4">
    <div class="container text-center">
        <h1 class="text-white fw-bold"><i class="bi bi-file-text me-2"></i>Demande de Devis</h1>
        <p class="text-white-50">Gratuit et sans engagement - Réponse en moins de 2 heures</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if ($succes): ?>
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card border-0 shadow-sm p-5">
                    <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-4" style="width:80px;height:80px;">
                        <i class="bi bi-check-lg display-4"></i>
                    </div>
                    <h3 class="fw-bold text-success">Demande envoyée !</h3>
                    <p class="text-muted mb-1">Votre demande de devis <strong>#<?= $_succes_numero ?? '' ?></strong> a été enregistrée.</p>
                    <p class="text-muted">Nous vous contacterons très rapidement avec votre devis personnalisé.</p>
                    <?php if ($_client_dv): ?>
                    <a href="index.php?page=compte&tab=devis" class="btn btn-outline-primary me-2 mb-2"><i class="bi bi-file-text me-1"></i>Voir mes devis</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-primary mb-2">Retour à l'accueil</a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfField() ?>

                            <!-- Informations client -->
                            <h5 class="fw-bold mb-3"><i class="bi bi-person me-2"></i>Vos informations</h5>
                            <?php if ($_client_dv): ?>
                            <div class="alert alert-info py-2 mb-3 small">
                                <i class="bi bi-person-check me-1"></i>Connecté en tant que <strong><?= htmlspecialchars($_client_dv['prenom'] . ' ' . $_client_dv['nom']) ?></strong> - Vos informations sont pré-remplies.
                            </div>
                            <?php else: ?>
                            <div class="alert alert-light py-2 mb-3 small border">
                                <i class="bi bi-person me-1"></i>Vous avez un compte ? <a href="index.php?page=compte-login" class="fw-semibold">Connectez-vous</a> pour remplir automatiquement.
                            </div>
                            <?php endif; ?>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" name="prenom" class="form-control" required value="<?= htmlspecialchars($_POST['prenom'] ?? ($_client_dv['prenom'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($_POST['nom'] ?? ($_client_dv['nom'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                                    <input type="tel" name="telephone" class="form-control" required placeholder="+212 6XX-XXXXXX" value="<?= htmlspecialchars($_POST['telephone'] ?? ($_client_dv['telephone'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? ($_client_dv['email'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Entreprise</label>
                                    <input type="text" name="entreprise" class="form-control" value="<?= htmlspecialchars($_POST['entreprise'] ?? ($_client_dv['nom_entreprise'] ?? '')) ?>" placeholder="Nom de votre entreprise (optionnel)">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ville</label>
                                    <input type="text" name="ville" class="form-control" value="<?= htmlspecialchars($_POST['ville'] ?? ($_client_dv['ville'] ?? '')) ?>" placeholder="Votre ville">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Adresse</label>
                                    <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($_POST['adresse'] ?? ($_client_dv['adresse'] ?? '')) ?>" placeholder="Votre adresse complète">
                                </div>
                            </div>

                            <!-- Projet -->
                            <h5 class="fw-bold mb-3"><i class="bi bi-printer me-2"></i>Votre projet</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Catégorie de service</label>
                                    <select name="service" id="serviceSelect" class="form-select" onchange="filterProduits()">
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['nom']) ?>" data-id="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                        <?php endforeach; ?>
                                        <option value="Autre">Autre (précisez ci-dessous)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Produit spécifique</label>
                                    <select name="produit_choisi" id="produitSelect" class="form-select">
                                        <option value="">-- Tous les produits --</option>
                                        <?php foreach ($produits as $p): ?>
                                        <option value="<?= htmlspecialchars($p['nom']) ?>" data-cat="<?= $p['categorie_id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description détaillée du projet <span class="text-danger">*</span></label>
                                    <textarea name="description" class="form-control" rows="4" required placeholder="Décrivez votre besoin en détail : format, couleurs, type de papier, finition souhaitée..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Quantité estimée</label>
                                    <input type="text" name="quantite" class="form-control" placeholder="Ex: 500 pièces" value="<?= htmlspecialchars($_POST['quantite'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Format / Dimensions</label>
                                    <input type="text" name="format" class="form-control" placeholder="Ex: A4, 85x55mm, 2m x 1m" value="<?= htmlspecialchars($_POST['format'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Finition souhaitée</label>
                                    <select name="finition" class="form-select">
                                        <option value="">-- Sélectionner --</option>
                                        <option value="Standard">Standard</option>
                                        <option value="Pelliculage mat">Pelliculage mat</option>
                                        <option value="Pelliculage brillant">Pelliculage brillant</option>
                                        <option value="Vernis sélectif">Vernis sélectif</option>
                                        <option value="Dorure à chaud">Dorure à chaud</option>
                                        <option value="Découpe forme">Découpe à la forme</option>
                                        <option value="Autre">Autre (précisez dans la description)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Délai souhaité</label>
                                    <select name="delai" class="form-select">
                                        <option value="">-- Sélectionner --</option>
                                        <option value="Urgent (24h)">Urgent (24h)</option>
                                        <option value="Express (48h)">Express (48h)</option>
                                        <option value="Normal (3-5 jours)">Normal (3-5 jours)</option>
                                        <option value="1 semaine">1 semaine</option>
                                        <option value="2 semaines">2 semaines</option>
                                        <option value="Pas pressé">Pas pressé</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Budget approximatif</label>
                                    <select name="budget" class="form-select">
                                        <option value="">-- Sélectionner --</option>
                                        <option value="< 500 DH">Moins de 500 DH</option>
                                        <option value="500 - 1000 DH">500 - 1 000 DH</option>
                                        <option value="1000 - 5000 DH">1 000 - 5 000 DH</option>
                                        <option value="5000 - 10000 DH">5 000 - 10 000 DH</option>
                                        <option value="> 10000 DH">Plus de 10 000 DH</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="envoyer_devis" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send me-2"></i>Envoyer la demande de devis
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                            <i class="bi bi-lightning-charge text-primary fs-3"></i>
                        </div>
                        <h6 class="fw-bold">Réponse rapide</h6>
                        <p class="text-muted small mb-0">Recevez votre devis personnalisé en moins de 2 heures pendant les heures ouvrables.</p>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                            <i class="bi bi-shield-check text-success fs-3"></i>
                        </div>
                        <h6 class="fw-bold">Sans engagement</h6>
                        <p class="text-muted small mb-0">La demande de devis est gratuite et sans aucune obligation de votre part.</p>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                            <i class="bi bi-whatsapp text-success fs-3"></i>
                        </div>
                        <h6 class="fw-bold">Besoin d'aide ?</h6>
                        <p class="text-muted small mb-2">Contactez-nous directement sur WhatsApp pour un devis instantané.</p>
                        <?php $_wa = str_replace(['+', ' ', '-', '(', ')'], '', getParametre('whatsapp_number', '') ?: APP_PHONE); ?>
                        <a href="https://wa.me/<?= $_wa ?>?text=<?= urlencode('Bonjour, je souhaite un devis pour...') ?>" class="btn btn-success btn-sm" target="_blank">
                            <i class="bi bi-whatsapp me-1"></i>WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
function filterProduits() {
    const sel = document.getElementById('serviceSelect');
    const opt = sel.options[sel.selectedIndex];
    const catId = opt ? opt.dataset.id : '';
    const prodSel = document.getElementById('produitSelect');
    for (let i = 1; i < prodSel.options.length; i++) {
        const o = prodSel.options[i];
        o.style.display = (!catId || o.dataset.cat === catId) ? '' : 'none';
    }
    prodSel.value = '';
}
</script>
