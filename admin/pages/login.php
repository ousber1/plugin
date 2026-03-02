<?php
/**
 * BERRADI PRINT - Page de Connexion Admin
 */
if (estConnecte()) {
    redirect('index.php?page=dashboard');
}

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['mot_de_passe'] ?? '';

    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE email = ? AND actif = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['admin_id'] = $user['id'];
            $db->prepare("UPDATE admins SET derniere_connexion = NOW() WHERE id = ?")->execute([$user['id']]);
            redirect('index.php?page=dashboard');
        } else {
            $erreur = 'Email ou mot de passe incorrect.';
        }
    } else {
        $erreur = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { border-radius: 16px; overflow: hidden; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="text-center mb-4">
                    <i class="bi bi-printer-fill text-white display-4"></i>
                    <h3 class="text-white fw-bold mt-2"><?= APP_NAME ?></h3>
                    <p class="text-white-50">Panneau d'Administration</p>
                </div>
                <div class="card login-card border-0 shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="fw-bold mb-4 text-center">Connexion</h5>

                        <?php if ($erreur): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-circle me-1"></i><?= $erreur ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Adresse email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required autofocus value="<?= $email ?? '' ?>" placeholder="admin@berradiprint.ma">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="mot_de_passe" class="form-control" required placeholder="••••••••">
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Identifiants par défaut:<br>
                                admin@berradiprint.ma / admin123
                            </small>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="../index.php" class="text-white-50 text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Retour au site
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
