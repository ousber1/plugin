<?php
/**
 * BERRADI PRINT - Page Commander (Checkout)
 */
$db = getDB();
$panier = getPanier();
$total_panier = totalPanier();

if (empty($panier)) {
    redirect('index.php?page=panier');
}

$villes = getVillesLivraison();

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_commande'])) {
    verifyCsrf();

    $nom = clean($_POST['nom'] ?? '');
    $prenom = clean($_POST['prenom'] ?? '');
    $telephone = clean($_POST['telephone'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $adresse = clean($_POST['adresse'] ?? '');
    $ville_id = (int)($_POST['ville_id'] ?? 0);
    $code_postal = clean($_POST['code_postal'] ?? '');
    $type_livraison = clean($_POST['type_livraison'] ?? 'livraison');
    $notes = clean($_POST['notes'] ?? '');

    // Validation
    $erreurs = [];
    if (empty($nom)) $erreurs[] = 'Le nom est obligatoire';
    if (empty($prenom)) $erreurs[] = 'Le prénom est obligatoire';
    if (empty($telephone)) $erreurs[] = 'Le téléphone est obligatoire';
    if ($type_livraison === 'livraison' && empty($adresse)) $erreurs[] = 'L\'adresse est obligatoire pour la livraison';
    if ($type_livraison === 'livraison' && !$ville_id) $erreurs[] = 'Veuillez sélectionner une ville';

    if (empty($erreurs)) {
        // Chercher ou créer le client
        $stmt = $db->prepare("SELECT id FROM clients WHERE telephone = ?");
        $stmt->execute([$telephone]);
        $client = $stmt->fetch();

        if ($client) {
            $client_id = $client['id'];
            $db->prepare("UPDATE clients SET nom=?, prenom=?, email=?, adresse=?, ville=?, code_postal=? WHERE id=?")->execute([$nom, $prenom, $email, $adresse, '', $code_postal, $client_id]);
        } else {
            $stmt = $db->prepare("INSERT INTO clients (nom, prenom, email, telephone, adresse, code_postal) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $code_postal]);
            $client_id = $db->lastInsertId();
        }

        // Calcul frais livraison
        $frais_livraison = 0;
        $ville_nom = 'Retrait en magasin';
        if ($type_livraison === 'livraison') {
            $frais_livraison = getFraisLivraison($ville_id);
            if ($total_panier >= FREE_DELIVERY_MIN) $frais_livraison = 0;
            $stmt = $db->prepare("SELECT nom FROM villes_livraison WHERE id = ?");
            $stmt->execute([$ville_id]);
            $v = $stmt->fetch();
            $ville_nom = $v ? $v['nom'] : '';
        }

        $tva = $total_panier * TAX_RATE;
        $total_final = $total_panier + $frais_livraison;

        // Créer la commande
        $numero = genererNumeroCommande();
        $stmt = $db->prepare("INSERT INTO commandes (numero_commande, client_id, client_nom, client_telephone, client_email, client_adresse, client_ville, client_code_postal, sous_total, frais_livraison, tva_montant, total, type_livraison, notes_client, source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $numero, $client_id, "$prenom $nom", $telephone, $email, $adresse, $ville_nom, $code_postal,
            $total_panier, $frais_livraison, $tva, $total_final, $type_livraison, $notes, 'site'
        ]);
        $commande_id = $db->lastInsertId();

        // Ajouter les lignes
        foreach ($panier as $item) {
            $stmt = $db->prepare("INSERT INTO commande_lignes (commande_id, produit_id, designation, options_selectionnees, quantite, prix_unitaire, prix_total) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $commande_id,
                $item['produit_id'],
                $item['nom'],
                json_encode($item['options']),
                $item['quantite'],
                $item['prix_unitaire'],
                $item['prix_total']
            ]);
        }

        // Historique
        $db->prepare("INSERT INTO commande_historique (commande_id, statut_nouveau, commentaire) VALUES (?,?,?)")->execute([$commande_id, 'nouvelle', 'Commande créée depuis le site web']);

        // Notification
        creerNotification('commande', 'Nouvelle commande #' . $numero, "Commande de $prenom $nom - " . formatPrix($total_final), 'admin/index.php?page=commande_detail&id=' . $commande_id);

        // Mettre à jour le client
        $db->prepare("UPDATE clients SET total_commandes = total_commandes + 1, total_depense = total_depense + ? WHERE id = ?")->execute([$total_final, $client_id]);

        // Vider le panier
        viderPanier();
        $_SESSION['derniere_commande'] = $numero;

        redirect('index.php?page=confirmation');
    }
}
?>

<section class="bg-light py-3">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?page=panier">Panier</a></li>
                <li class="breadcrumb-item active">Commander</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h2 class="fw-bold mb-4"><i class="bi bi-credit-card me-2"></i>Finaliser la commande</h2>

        <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($erreurs as $err): ?>
                <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="row g-4">
                <!-- Formulaire Client -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">
                            <i class="bi bi-person me-2"></i>Informations personnelles
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" name="prenom" class="form-control" required value="<?= $_POST['prenom'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" name="nom" class="form-control" required value="<?= $_POST['nom'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                                    <input type="tel" name="telephone" class="form-control" required placeholder="06XXXXXXXX" value="<?= $_POST['telephone'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= $_POST['email'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">
                            <i class="bi bi-truck me-2"></i>Mode de réception
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="type_livraison" value="livraison" id="livraison" checked onchange="toggleLivraison()">
                                    <label class="form-check-label" for="livraison">
                                        <i class="bi bi-truck me-1"></i>Livraison à domicile (Paiement à la livraison)
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="type_livraison" value="retrait" id="retrait" onchange="toggleLivraison()">
                                    <label class="form-check-label" for="retrait">
                                        <i class="bi bi-shop me-1"></i>Retrait en magasin
                                    </label>
                                </div>
                            </div>

                            <div id="champs-livraison">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Adresse complète <span class="text-danger">*</span></label>
                                        <textarea name="adresse" class="form-control" rows="2"><?= $_POST['adresse'] ?? '' ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ville <span class="text-danger">*</span></label>
                                        <select name="ville_id" class="form-select" id="ville_select" onchange="updateFrais()">
                                            <option value="">-- Sélectionner une ville --</option>
                                            <?php foreach ($villes as $v): ?>
                                            <option value="<?= $v['id'] ?>" data-frais="<?= $v['frais_livraison'] ?>" <?= (isset($_POST['ville_id']) && $_POST['ville_id'] == $v['id']) ? 'selected' : '' ?>>
                                                <?= $v['nom'] ?> <?= $v['frais_livraison'] > 0 ? '(' . formatPrix($v['frais_livraison']) . ')' : '(Gratuit)' ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Code postal</label>
                                        <input type="text" name="code_postal" class="form-control" value="<?= $_POST['code_postal'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">
                            <i class="bi bi-chat-text me-2"></i>Notes
                        </div>
                        <div class="card-body">
                            <textarea name="notes" class="form-control" rows="3" placeholder="Instructions spéciales, commentaires..."><?= $_POST['notes'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Récapitulatif -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm sticky-lg-top" style="top: 100px;">
                        <div class="card-header bg-primary text-white fw-bold">
                            <i class="bi bi-receipt me-2"></i>Récapitulatif de commande
                        </div>
                        <div class="card-body">
                            <?php foreach ($panier as $item): ?>
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                <div>
                                    <div class="fw-semibold small"><?= $item['nom'] ?></div>
                                    <small class="text-muted">x<?= $item['quantite'] ?></small>
                                </div>
                                <span class="fw-bold small"><?= formatPrix($item['prix_total']) ?></span>
                            </div>
                            <?php endforeach; ?>

                            <div class="d-flex justify-content-between mb-2 mt-3">
                                <span>Sous-total</span>
                                <span class="fw-bold"><?= formatPrix($total_panier) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Frais de livraison</span>
                                <span id="frais-livraison" class="fw-bold">
                                    <?= $total_panier >= FREE_DELIVERY_MIN ? '<span class="text-success">Gratuit</span>' : '-' ?>
                                </span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold fs-5">Total</span>
                                <span class="fw-bold fs-5 text-primary" id="total-commande"><?= formatPrix($total_panier) ?></span>
                            </div>

                            <div class="alert alert-warning mb-3">
                                <i class="bi bi-cash-coin me-2"></i>
                                <strong>Paiement à la livraison</strong><br>
                                <small>Vous payez en espèces à la réception de votre commande.</small>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="valider_commande" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Confirmer la commande
                                </button>
                            </div>

                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    En confirmant, vous acceptez nos conditions générales de vente.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
function toggleLivraison() {
    const isLivraison = document.getElementById('livraison').checked;
    document.getElementById('champs-livraison').style.display = isLivraison ? 'block' : 'none';
    if (!isLivraison) {
        document.getElementById('frais-livraison').innerHTML = '<span class="text-success">Gratuit</span>';
    }
}

function updateFrais() {
    const select = document.getElementById('ville_select');
    const option = select.options[select.selectedIndex];
    const frais = parseFloat(option.dataset.frais || 0);
    const total = <?= $total_panier ?>;
    const isFree = total >= <?= FREE_DELIVERY_MIN ?>;
    const finalFrais = isFree ? 0 : frais;
    document.getElementById('frais-livraison').innerHTML = finalFrais > 0 ? finalFrais.toFixed(2).replace('.', ',') + ' DH' : '<span class="text-success">Gratuit</span>';
    const totalFinal = total + finalFrais;
    document.getElementById('total-commande').innerHTML = totalFinal.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' DH';
}
</script>
