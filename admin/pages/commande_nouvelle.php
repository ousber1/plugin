<?php
/**
 * BERRADI PRINT - Nouvelle Commande (depuis admin)
 */
$db = getDB();
$produits = $db->query("SELECT p.*, c.nom as categorie_nom FROM produits p JOIN categories c ON p.categorie_id = c.id WHERE p.actif = 1 ORDER BY c.ordre, p.ordre")->fetchAll();
$villes = getVillesLivraison();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_commande'])) {
    verifyCsrf();

    $nom = clean($_POST['nom'] ?? '');
    $prenom = clean($_POST['prenom'] ?? '');
    $telephone = clean($_POST['telephone'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $adresse = clean($_POST['adresse'] ?? '');
    $ville = clean($_POST['ville'] ?? '');
    $type_livraison = clean($_POST['type_livraison'] ?? 'livraison');
    $source = clean($_POST['source'] ?? 'direct');
    $priorite = clean($_POST['priorite'] ?? 'normale');
    $notes = clean($_POST['notes_internes'] ?? '');
    $remise = floatval($_POST['remise'] ?? 0);

    // Créer/trouver client
    $stmt = $db->prepare("SELECT id FROM clients WHERE telephone = ?");
    $stmt->execute([$telephone]);
    $client = $stmt->fetch();
    if ($client) {
        $client_id = $client['id'];
    } else {
        $db->prepare("INSERT INTO clients (nom, prenom, telephone, email, adresse, ville) VALUES (?,?,?,?,?,?)")->execute([$nom, $prenom, $telephone, $email, $adresse, $ville]);
        $client_id = $db->lastInsertId();
    }

    // Calculer le total
    $sous_total = 0;
    $articles = $_POST['articles'] ?? [];
    foreach ($articles as $art) {
        if (!empty($art['designation']) && $art['quantite'] > 0) {
            $sous_total += floatval($art['prix_unitaire']) * intval($art['quantite']);
        }
    }

    $frais_livraison = floatval($_POST['frais_livraison'] ?? 0);
    $total = $sous_total - $remise + $frais_livraison;
    $numero = genererNumeroCommande();

    $stmt = $db->prepare("INSERT INTO commandes (numero_commande, client_id, client_nom, client_telephone, client_email, client_adresse, client_ville, sous_total, remise_montant, frais_livraison, total, type_livraison, notes_internes, source, admin_id, priorite, statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$numero, $client_id, "$prenom $nom", $telephone, $email, $adresse, $ville, $sous_total, $remise, $frais_livraison, $total, $type_livraison, $notes, $source, $admin['id'], $priorite, 'confirmee']);
    $commande_id = $db->lastInsertId();

    foreach ($articles as $art) {
        if (!empty($art['designation']) && $art['quantite'] > 0) {
            $px_total = floatval($art['prix_unitaire']) * intval($art['quantite']);
            $db->prepare("INSERT INTO commande_lignes (commande_id, produit_id, designation, quantite, prix_unitaire, prix_total, notes) VALUES (?,?,?,?,?,?,?)")->execute([
                $commande_id, $art['produit_id'] ?: null, $art['designation'], $art['quantite'], $art['prix_unitaire'], $px_total, $art['notes'] ?? ''
            ]);
        }
    }

    $db->prepare("INSERT INTO commande_historique (commande_id, statut_nouveau, commentaire, admin_id) VALUES (?,?,?,?)")->execute([$commande_id, 'confirmee', 'Commande créée par ' . $admin['prenom'], $admin['id']]);
    $db->prepare("UPDATE clients SET total_commandes = total_commandes + 1, total_depense = total_depense + ? WHERE id = ?")->execute([$total, $client_id]);

    setFlash('success', "Commande #$numero créée avec succès !");
    redirect('index.php?page=commande_detail&id=' . $commande_id);
}
?>

<div class="mb-4">
    <a href="index.php?page=commandes" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <h4 class="fw-bold"><i class="bi bi-plus-circle me-2"></i>Nouvelle Commande</h4>
</div>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Client -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Client</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label small">Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">Téléphone *</label><input type="tel" name="telephone" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label small">Ville</label><input type="text" name="ville" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label small">Adresse</label><input type="text" name="adresse" class="form-control"></div>
                    </div>
                </div>
            </div>

            <!-- Articles -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-box me-2"></i>Articles</span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="ajouterLigne()"><i class="bi bi-plus me-1"></i>Ajouter</button>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0" id="tableArticles">
                        <thead class="bg-light">
                            <tr><th>Désignation</th><th style="width:100px">Qté</th><th style="width:130px">Prix unit.</th><th style="width:120px">Total</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="lignesArticles">
                            <tr class="ligne-article">
                                <td>
                                    <input type="text" name="articles[0][designation]" class="form-control form-control-sm" required>
                                    <input type="hidden" name="articles[0][produit_id]" value="">
                                    <input type="text" name="articles[0][notes]" class="form-control form-control-sm mt-1" placeholder="Notes...">
                                </td>
                                <td><input type="number" name="articles[0][quantite]" class="form-control form-control-sm qte-input" value="1" min="1" onchange="calculerTotal()"></td>
                                <td><input type="number" name="articles[0][prix_unitaire]" class="form-control form-control-sm prix-input" step="0.01" value="0" onchange="calculerTotal()"></td>
                                <td class="ligne-total fw-bold text-end align-middle">0,00 DH</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="supprimerLigne(this)"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Options -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold"><i class="bi bi-gear me-2"></i>Options</div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label small">Source</label>
                        <select name="source" class="form-select form-select-sm">
                            <option value="direct">Direct</option>
                            <option value="telephone">Téléphone</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="site">Site web</option>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label small">Priorité</label>
                        <select name="priorite" class="form-select form-select-sm">
                            <option value="normale">Normale</option>
                            <option value="urgente">Urgente</option>
                            <option value="express">Express</option>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label small">Type</label>
                        <select name="type_livraison" class="form-select form-select-sm">
                            <option value="livraison">Livraison</option>
                            <option value="retrait">Retrait en magasin</option>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label small">Notes internes</label>
                        <textarea name="notes_internes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <!-- Totaux -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold"><i class="bi bi-calculator me-2"></i>Totaux</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Sous-total</span><span id="sousTotal" class="fw-bold">0,00 DH</span></div>
                    <div class="mb-2">
                        <label class="form-label small">Remise (DH)</label>
                        <input type="number" name="remise" class="form-control form-control-sm" value="0" step="0.01" onchange="calculerTotal()">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Frais livraison (DH)</label>
                        <input type="number" name="frais_livraison" class="form-control form-control-sm" value="0" step="0.01" onchange="calculerTotal()">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between"><span class="fw-bold fs-5">Total</span><span id="grandTotal" class="fw-bold fs-5 text-primary">0,00 DH</span></div>
                </div>
            </div>

            <button type="submit" name="creer_commande" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-check-circle me-2"></i>Créer la commande
            </button>
        </div>
    </div>
</form>

<script>
let ligneIndex = 1;
function ajouterLigne() {
    const tbody = document.getElementById('lignesArticles');
    const tr = document.createElement('tr');
    tr.className = 'ligne-article';
    tr.innerHTML = `
        <td><input type="text" name="articles[${ligneIndex}][designation]" class="form-control form-control-sm" required>
        <input type="hidden" name="articles[${ligneIndex}][produit_id]" value="">
        <input type="text" name="articles[${ligneIndex}][notes]" class="form-control form-control-sm mt-1" placeholder="Notes..."></td>
        <td><input type="number" name="articles[${ligneIndex}][quantite]" class="form-control form-control-sm qte-input" value="1" min="1" onchange="calculerTotal()"></td>
        <td><input type="number" name="articles[${ligneIndex}][prix_unitaire]" class="form-control form-control-sm prix-input" step="0.01" value="0" onchange="calculerTotal()"></td>
        <td class="ligne-total fw-bold text-end align-middle">0,00 DH</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="supprimerLigne(this)"><i class="bi bi-trash"></i></button></td>`;
    tbody.appendChild(tr);
    ligneIndex++;
}

function supprimerLigne(btn) {
    if (document.querySelectorAll('.ligne-article').length > 1) {
        btn.closest('tr').remove();
        calculerTotal();
    }
}

function calculerTotal() {
    let sousTotal = 0;
    document.querySelectorAll('.ligne-article').forEach(tr => {
        const qte = parseFloat(tr.querySelector('.qte-input').value) || 0;
        const prix = parseFloat(tr.querySelector('.prix-input').value) || 0;
        const total = qte * prix;
        tr.querySelector('.ligne-total').textContent = total.toFixed(2).replace('.', ',') + ' DH';
        sousTotal += total;
    });
    document.getElementById('sousTotal').textContent = sousTotal.toFixed(2).replace('.', ',') + ' DH';
    const remise = parseFloat(document.querySelector('[name="remise"]').value) || 0;
    const frais = parseFloat(document.querySelector('[name="frais_livraison"]').value) || 0;
    const grandTotal = sousTotal - remise + frais;
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2).replace('.', ',') + ' DH';
}
</script>
