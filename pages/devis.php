<?php
/**
 * BERRADI PRINT - Demande de Devis
 */
$db = getDB();
$categories = $db->query("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();
$succes = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_devis'])) {
    verifyCsrf();

    $nom = clean($_POST['nom'] ?? '');
    $prenom = clean($_POST['prenom'] ?? '');
    $telephone = clean($_POST['telephone'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $entreprise = clean($_POST['entreprise'] ?? '');
    $service = clean($_POST['service'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $quantite = clean($_POST['quantite'] ?? '');
    $budget = clean($_POST['budget'] ?? '');

    if ($nom && $telephone && $description) {
        // Créer le devis
        $numero_devis = genererNumeroDevis();
        $lignes = json_encode([
            ['service' => $service, 'description' => $description, 'quantite' => $quantite, 'budget' => $budget]
        ]);

        $stmt = $db->prepare("INSERT INTO devis (numero_devis, client_nom, client_telephone, client_email, lignes, sous_total, total, notes) VALUES (?,?,?,?,?,0,0,?)");
        $stmt->execute([$numero_devis, "$prenom $nom", $telephone, $email, $lignes, "Entreprise: $entreprise | Budget: $budget"]);

        creerNotification('devis', 'Nouvelle demande de devis #' . $numero_devis, "De $prenom $nom - $service");

        $succes = true;
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
                    <p class="text-muted">Nous vous contacterons très rapidement avec votre devis personnalisé.</p>
                    <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <form method="POST">
                            <?= csrfField() ?>
                            <h5 class="fw-bold mb-4"><i class="bi bi-person me-2"></i>Vos informations</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" name="prenom" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" name="nom" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                                    <input type="tel" name="telephone" class="form-control" required placeholder="06XXXXXXXX">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Nom de l'entreprise (optionnel)</label>
                                    <input type="text" name="entreprise" class="form-control">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-4"><i class="bi bi-printer me-2"></i>Votre projet</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Service souhaité</label>
                                    <select name="service" class="form-select">
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['nom'] ?>"><?= $cat['nom'] ?></option>
                                        <?php endforeach; ?>
                                        <option value="Autre">Autre (précisez ci-dessous)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Quantité estimée</label>
                                    <input type="text" name="quantite" class="form-control" placeholder="Ex: 500 pièces">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description du projet <span class="text-danger">*</span></label>
                                    <textarea name="description" class="form-control" rows="4" required placeholder="Décrivez votre besoin en détail : format, couleurs, type de papier, finition souhaitée..."></textarea>
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
        </div>
        <?php endif; ?>
    </div>
</section>
