<?php
/**
 * BERRADI PRINT - Inscription Client
 */
$db = getDB();

// Auto-add password column if not exists
try { $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS mot_de_passe VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS derniere_connexion DATETIME DEFAULT NULL"); } catch (Exception $e) {}

if (clientEstConnecte()) {
    redirect('index.php?page=compte');
}

$error = '';
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscription'])) {
    verifyCsrf();
    $old = $_POST;
    $nom = clean($_POST['nom'] ?? '');
    $prenom = clean($_POST['prenom'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $telephone = clean($_POST['telephone'] ?? '');
    $mdp = $_POST['mot_de_passe'] ?? '';
    $mdp2 = $_POST['mot_de_passe_confirm'] ?? '';
    $adresse = clean($_POST['adresse'] ?? '');
    $ville = clean($_POST['ville'] ?? '');
    $type = clean($_POST['type_client'] ?? 'particulier');

    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($mdp)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($mdp) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($mdp !== $mdp2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $result = clientRegister([
            'nom' => $nom, 'prenom' => $prenom, 'email' => $email,
            'telephone' => $telephone, 'mot_de_passe' => $mdp,
            'adresse' => $adresse, 'ville' => $ville, 'type_client' => $type
        ]);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            setFlash('success', 'Votre compte a été créé avec succès ! Bienvenue, ' . htmlspecialchars($prenom) . ' !');
            redirect('index.php?page=compte');
        }
    }
}

$villes = getVillesLivraison();
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
                                <i class="bi bi-person-plus text-success fs-2"></i>
                            </div>
                            <h4 class="fw-bold">Créer un compte</h4>
                            <p class="text-muted small">Rejoignez <?= APP_NAME ?> pour suivre vos commandes et profiter de nos services</p>
                        </div>

                        <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= csrfField() ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" name="prenom" class="form-control" required value="<?= htmlspecialchars($old['prenom'] ?? '') ?>" placeholder="Votre prénom">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($old['nom'] ?? '') ?>" placeholder="Votre nom">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($old['email'] ?? '') ?>" placeholder="votre@email.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="tel" name="telephone" class="form-control" required value="<?= htmlspecialchars($old['telephone'] ?? '') ?>" placeholder="+212 6XX-XXXXXX">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mot de passe *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" name="mot_de_passe" class="form-control" required placeholder="Min. 6 caractères" id="mdp1">
                                        <button type="button" class="btn btn-outline-secondary" onclick="let f=document.getElementById('mdp1');f.type=f.type==='password'?'text':'password'">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmer le mot de passe *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                        <input type="password" name="mot_de_passe_confirm" class="form-control" required placeholder="Retapez le mot de passe">
                                    </div>
                                </div>

                                <div class="col-12"><hr class="my-1"></div>

                                <div class="col-md-6">
                                    <label class="form-label">Type de client</label>
                                    <select name="type_client" class="form-select">
                                        <option value="particulier" <?= ($old['type_client'] ?? '') === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                                        <option value="entreprise" <?= ($old['type_client'] ?? '') === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ville</label>
                                    <select name="ville" class="form-select">
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($villes as $v): ?>
                                        <option value="<?= htmlspecialchars($v['nom']) ?>" <?= ($old['ville'] ?? '') === $v['nom'] ? 'selected' : '' ?>><?= htmlspecialchars($v['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Adresse</label>
                                    <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($old['adresse'] ?? '') ?>" placeholder="Votre adresse complète">
                                </div>
                            </div>

                            <button type="submit" name="inscription" class="btn btn-success w-100 btn-lg mt-4 mb-3">
                                <i class="bi bi-person-plus me-2"></i>Créer mon compte
                            </button>
                        </form>

                        <div class="text-center">
                            <p class="mb-0 small text-muted">
                                Déjà un compte ?
                                <a href="index.php?page=compte-login" class="fw-semibold text-decoration-none">Se connecter</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
