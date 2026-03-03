<?php
/**
 * BERRADI PRINT - Connexion Client
 */
$db = getDB();

// Auto-add password column if not exists
try { $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS mot_de_passe VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS derniere_connexion DATETIME DEFAULT NULL"); } catch (Exception $e) {}

// If already logged in, redirect to account
if (clientEstConnecte()) {
    redirect('index.php?page=compte');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connexion'])) {
    verifyCsrf();
    $email = clean($_POST['email'] ?? '');
    $mdp = $_POST['mot_de_passe'] ?? '';

    if (empty($email) || empty($mdp)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $client = clientLogin($email, $mdp);
        if ($client) {
            setFlash('success', 'Bienvenue, ' . htmlspecialchars($client['prenom']) . ' !');
            redirect('index.php?page=compte');
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
                                <i class="bi bi-person-circle text-primary fs-2"></i>
                            </div>
                            <h4 class="fw-bold">Connexion</h4>
                            <p class="text-muted small">Accédez à votre espace client</p>
                        </div>

                        <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Adresse email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required placeholder="votre@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="mot_de_passe" class="form-control" required placeholder="Votre mot de passe" id="mdp">
                                    <button type="button" class="btn btn-outline-secondary" onclick="let f=document.getElementById('mdp');f.type=f.type==='password'?'text':'password'">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="connexion" class="btn btn-primary w-100 btn-lg mb-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                            </button>
                        </form>

                        <div class="text-center">
                            <p class="mb-0 small text-muted">
                                Pas encore de compte ?
                                <a href="index.php?page=compte-register" class="fw-semibold text-decoration-none">Créer un compte</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
