<?php
/**
 * BERRADI PRINT - Paramètres Généraux
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder'])) {
    verifyCsrf();
    $champs = [
        'nom_entreprise', 'slogan', 'email', 'telephone', 'whatsapp',
        'adresse', 'ville', 'horaires', 'tva_active', 'tva_taux',
        'ice', 'rc', 'if_fiscal', 'livraison_gratuite_min',
        'frais_livraison_defaut', 'devise', 'symbole_devise',
        'facebook', 'instagram', 'whatsapp_url'
    ];
    foreach ($champs as $cle) {
        if (isset($_POST[$cle])) {
            setParametre($cle, clean($_POST[$cle]));
        }
    }
    setFlash('success', 'Paramètres sauvegardés.');
    redirect('index.php?page=parametres');
}

// Charger les paramètres
$params = [];
$stmt = $db->query("SELECT cle, valeur FROM parametres");
while ($row = $stmt->fetch()) {
    $params[$row['cle']] = $row['valeur'];
}
?>

<h4 class="fw-bold mb-4"><i class="bi bi-gear me-2"></i>Paramètres</h4>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <!-- Entreprise -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-building me-2"></i>Entreprise</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Nom de l'entreprise</label><input type="text" name="nom_entreprise" class="form-control" value="<?= $params['nom_entreprise'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Slogan</label><input type="text" name="slogan" class="form-control" value="<?= $params['slogan'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $params['email'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Téléphone</label><input type="text" name="telephone" class="form-control" value="<?= $params['telephone'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">WhatsApp</label><input type="text" name="whatsapp" class="form-control" value="<?= $params['whatsapp'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Adresse</label><textarea name="adresse" class="form-control" rows="2"><?= $params['adresse'] ?? '' ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Ville</label><input type="text" name="ville" class="form-control" value="<?= $params['ville'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Horaires</label><input type="text" name="horaires" class="form-control" value="<?= $params['horaires'] ?? '' ?>"></div>
                </div>
            </div>

            <!-- Réseaux sociaux -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-share me-2"></i>Réseaux Sociaux</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Facebook</label><input type="url" name="facebook" class="form-control" value="<?= $params['facebook'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Instagram</label><input type="url" name="instagram" class="form-control" value="<?= $params['instagram'] ?? '' ?>"></div>
                    <div class="mb-3"><label class="form-label">Lien WhatsApp</label><input type="url" name="whatsapp_url" class="form-control" value="<?= $params['whatsapp_url'] ?? '' ?>"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <!-- Fiscal -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-receipt me-2"></i>Fiscal & TVA</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="tva_active" value="1" id="tva_active" <?= ($params['tva_active'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tva_active">TVA Active</label>
                    </div>
                    <div class="mb-3"><label class="form-label">Taux TVA (%)</label><input type="number" name="tva_taux" class="form-control" value="<?= $params['tva_taux'] ?? '20' ?>" step="0.01"></div>
                    <div class="mb-3"><label class="form-label">ICE</label><input type="text" name="ice" class="form-control" value="<?= $params['ice'] ?? '' ?>" placeholder="Identifiant Commun de l'Entreprise"></div>
                    <div class="mb-3"><label class="form-label">RC</label><input type="text" name="rc" class="form-control" value="<?= $params['rc'] ?? '' ?>" placeholder="Registre de Commerce"></div>
                    <div class="mb-3"><label class="form-label">IF</label><input type="text" name="if_fiscal" class="form-control" value="<?= $params['if_fiscal'] ?? '' ?>" placeholder="Identifiant Fiscal"></div>
                </div>
            </div>

            <!-- Livraison -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-truck me-2"></i>Livraison</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Devise</label><input type="text" name="devise" class="form-control" value="<?= $params['devise'] ?? 'MAD' ?>"></div>
                    <div class="mb-3"><label class="form-label">Symbole devise</label><input type="text" name="symbole_devise" class="form-control" value="<?= $params['symbole_devise'] ?? 'DH' ?>"></div>
                    <div class="mb-3"><label class="form-label">Livraison gratuite à partir de (DH)</label><input type="number" name="livraison_gratuite_min" class="form-control" value="<?= $params['livraison_gratuite_min'] ?? '500' ?>"></div>
                    <div class="mb-3"><label class="form-label">Frais de livraison par défaut (DH)</label><input type="number" name="frais_livraison_defaut" class="form-control" value="<?= $params['frais_livraison_defaut'] ?? '30' ?>"></div>
                </div>
            </div>

            <button type="submit" name="sauvegarder" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder les paramètres
            </button>
        </div>
    </div>
</form>
