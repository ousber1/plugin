<?php
/**
 * BERRADI PRINT - Installation Wizard
 * Système de Gestion de Services d'Impression
 *
 * Accédez à ce fichier via: http://votre-domaine/install.php
 * IMPORTANT: Supprimez ce fichier après l'installation!
 */

// Empêcher l'accès si déjà installé
$lock_file = __DIR__ . '/config/.installed';
if (file_exists($lock_file)) {
    die('
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Déjà installé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container"><div class="row justify-content-center"><div class="col-md-6">
    <div class="card shadow"><div class="card-body text-center p-5">
    <i class="bi bi-check-circle text-success" style="font-size:4rem;"></i>
    <h3 class="mt-3">BERRADI PRINT est déjà installé</h3>
    <p class="text-muted">Pour réinstaller, supprimez le fichier <code>config/.installed</code></p>
    <a href="index.php" class="btn btn-primary me-2">Voir le site</a>
    <a href="admin/index.php" class="btn btn-outline-primary">Administration</a>
    </div></div></div></div></div></body></html>');
}

session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Étape 1: Vérification des prérequis
    if ($action === 'check_requirements') {
        header('Location: install.php?step=2');
        exit;
    }

    // Étape 2: Configuration base de données
    if ($action === 'setup_database') {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? 'berradi_print');
        $db_user = trim($_POST['db_user'] ?? 'root');
        $db_pass = $_POST['db_pass'] ?? '';

        try {
            // Tester la connexion
            $pdo = new PDO(
                "mysql:host={$db_host};charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Créer la base de données
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db_name}`");

            // Exécuter le schéma SQL
            $sql = getFullSchema();
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }

            // Mettre à jour le fichier de configuration
            $config_content = "<?php
/**
 * BERRADI PRINT - Configuration Base de Données
 * Système de Gestion de Services d'Impression
 */

define('DB_HOST', " . var_export($db_host, true) . ");
define('DB_NAME', " . var_export($db_name, true) . ");
define('DB_USER', " . var_export($db_user, true) . ");
define('DB_PASS', " . var_export($db_pass, true) . ");
define('DB_CHARSET', 'utf8mb4');

// Connexion PDO
function getDB() {
    static \$pdo = null;
    if (\$pdo === null) {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException \$e) {
            die(\"Erreur de connexion à la base de données: \" . \$e->getMessage());
        }
    }
    return \$pdo;
}
";
            file_put_contents(__DIR__ . '/config/database.php', $config_content);

            $_SESSION['install_db'] = true;
            header('Location: install.php?step=3');
            exit;
        } catch (PDOException $e) {
            $error = 'Erreur de connexion: ' . $e->getMessage();
        }
    }

    // Étape 3: Configuration du site
    if ($action === 'setup_site') {
        $site_name = trim($_POST['site_name'] ?? 'BERRADI PRINT');
        $site_url = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $site_email = trim($_POST['site_email'] ?? '');
        $site_phone = trim($_POST['site_phone'] ?? '');
        $site_address = trim($_POST['site_address'] ?? '');

        $config_content = "<?php
/**
 * BERRADI PRINT - Configuration Générale
 */

// Informations de l'entreprise
define('APP_NAME', " . var_export($site_name, true) . ");
define('APP_TAGLINE', 'Services d\\'Impression Professionnels');
define('APP_VERSION', '1.0.0');
define('APP_URL', " . var_export($site_url, true) . ");
define('APP_EMAIL', " . var_export($site_email, true) . ");
define('APP_PHONE', " . var_export($site_phone, true) . ");
define('APP_ADDRESS', " . var_export($site_address, true) . ");
define('APP_CURRENCY', 'MAD');
define('APP_CURRENCY_SYMBOL', 'DH');
define('APP_LANG', 'fr');
define('APP_TIMEZONE', 'Africa/Casablanca');

// Paramètres de livraison
define('DELIVERY_METHOD', 'cash_on_delivery');
define('FREE_DELIVERY_MIN', 500);
define('DELIVERY_FEE', 30);

// Paramètres d'upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'ai', 'psd', 'eps', 'svg', 'doc', 'docx', 'tiff', 'tif']);

// Paramètres de session
define('SESSION_LIFETIME', 3600 * 24);

// TVA Maroc
define('TAX_RATE', 0.20);

date_default_timezone_set(APP_TIMEZONE);
";
        file_put_contents(__DIR__ . '/config/app.php', $config_content);

        // Mettre à jour les paramètres en base
        try {
            require_once __DIR__ . '/config/database.php';
            $pdo = getDB();
            $updates = [
                'nom_entreprise' => $site_name,
                'email' => $site_email,
                'telephone' => $site_phone,
                'adresse' => $site_address,
            ];
            foreach ($updates as $cle => $valeur) {
                $stmt = $pdo->prepare("UPDATE parametres SET valeur = ? WHERE cle = ?");
                $stmt->execute([$valeur, $cle]);
            }
        } catch (Exception $e) {
            // Ignorer les erreurs
        }

        $_SESSION['install_site'] = true;
        header('Location: install.php?step=4');
        exit;
    }

    // Étape 4: Création du compte admin
    if ($action === 'setup_admin') {
        $admin_nom = trim($_POST['admin_nom'] ?? '');
        $admin_prenom = trim($_POST['admin_prenom'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_phone = trim($_POST['admin_phone'] ?? '');
        $admin_pass = $_POST['admin_pass'] ?? '';
        $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

        if (empty($admin_email) || empty($admin_pass)) {
            $error = 'L\'email et le mot de passe sont obligatoires';
        } elseif ($admin_pass !== $admin_pass_confirm) {
            $error = 'Les mots de passe ne correspondent pas';
        } elseif (strlen($admin_pass) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères';
        } else {
            try {
                require_once __DIR__ . '/config/database.php';
                $pdo = getDB();
                $hash = password_hash($admin_pass, PASSWORD_DEFAULT);

                // Supprimer l'admin par défaut et insérer le nouveau
                $pdo->exec("DELETE FROM admins WHERE email = 'admin@berradiprint.ma'");
                $stmt = $pdo->prepare("INSERT INTO admins (nom, prenom, email, telephone, mot_de_passe, role) VALUES (?, ?, ?, ?, ?, 'super_admin')");
                $stmt->execute([$admin_nom, $admin_prenom, $admin_email, $admin_phone, $hash]);

                // Créer le fichier de verrouillage
                file_put_contents($lock_file, date('Y-m-d H:i:s') . "\nInstalled successfully");

                // Créer les dossiers nécessaires
                $dirs = [
                    __DIR__ . '/uploads/commandes',
                    __DIR__ . '/uploads/produits',
                    __DIR__ . '/uploads/imports',
                ];
                foreach ($dirs as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                }

                $_SESSION['install_complete'] = true;
                header('Location: install.php?step=5');
                exit;
            } catch (PDOException $e) {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Vérification des prérequis
function checkRequirements() {
    $checks = [];
    $checks['php_version'] = ['label' => 'PHP 7.4+', 'ok' => version_compare(PHP_VERSION, '7.4.0', '>='), 'value' => PHP_VERSION];
    $checks['pdo'] = ['label' => 'Extension PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? 'Activée' : 'Non installée'];
    $checks['mbstring'] = ['label' => 'Extension mbstring', 'ok' => extension_loaded('mbstring'), 'value' => extension_loaded('mbstring') ? 'Activée' : 'Non installée'];
    $checks['json'] = ['label' => 'Extension JSON', 'ok' => extension_loaded('json'), 'value' => extension_loaded('json') ? 'Activée' : 'Non installée'];
    $checks['session'] = ['label' => 'Extension Session', 'ok' => extension_loaded('session'), 'value' => extension_loaded('session') ? 'Activée' : 'Non installée'];
    $checks['fileinfo'] = ['label' => 'Extension FileInfo', 'ok' => extension_loaded('fileinfo'), 'value' => extension_loaded('fileinfo') ? 'Activée' : 'Non installée'];
    $checks['config_writable'] = ['label' => 'Dossier config/ accessible en écriture', 'ok' => is_writable(__DIR__ . '/config/'), 'value' => is_writable(__DIR__ . '/config/') ? 'OK' : 'Non accessible'];
    $checks['uploads_writable'] = ['label' => 'Dossier uploads/ accessible en écriture', 'ok' => is_writable(__DIR__ . '/uploads/'), 'value' => is_writable(__DIR__ . '/uploads/') ? 'OK' : 'Non accessible'];
    return $checks;
}

// Schéma SQL complet
function getFullSchema() {
    $sql_file = __DIR__ . '/database/schema.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        // Retirer CREATE DATABASE et USE statements car on les gère déjà
        $sql = preg_replace('/CREATE DATABASE.*?;\s*/i', '', $sql);
        $sql = preg_replace('/USE\s+\w+\s*;\s*/i', '', $sql);
        return $sql;
    }
    return '';
}

$all_ok = true;
if ($step === 1) {
    $checks = checkRequirements();
    foreach ($checks as $c) {
        if (!$c['ok']) $all_ok = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - BERRADI PRINT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-container { max-width: 700px; margin: 0 auto; padding: 40px 20px; }
        .install-card { background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden; }
        .install-header { background: linear-gradient(135deg, #1e3a5f, #2563eb); color: white; padding: 30px; text-align: center; }
        .install-body { padding: 40px; }
        .step-indicator { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .step-dot { width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,0.3); transition: all 0.3s; }
        .step-dot.active { background: white; transform: scale(1.3); }
        .step-dot.done { background: #4ade80; }
        .check-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .check-item:last-child { border-bottom: none; }
        .check-icon { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; }
        .check-ok { background: #dcfce7; color: #16a34a; }
        .check-fail { background: #fee2e2; color: #dc2626; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn-install { background: linear-gradient(135deg, #2563eb, #1e40af); border: none; padding: 12px 40px; font-weight: 600; border-radius: 8px; }
        .btn-install:hover { background: linear-gradient(135deg, #1d4ed8, #1e3a8a); }
        .success-icon { font-size: 80px; color: #16a34a; }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h2 class="mb-1"><i class="bi bi-printer-fill me-2"></i>BERRADI PRINT</h2>
                <p class="mb-2 opacity-75">Assistant d'Installation</p>
                <div class="step-indicator">
                    <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
                    <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
                    <div class="step-dot <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>"></div>
                    <div class="step-dot <?= $step >= 4 ? ($step > 4 ? 'done' : 'active') : '' ?>"></div>
                    <div class="step-dot <?= $step >= 5 ? 'active' : '' ?>"></div>
                </div>
                <small class="opacity-75">Étape <?= min($step, 5) ?> sur 5</small>
            </div>

            <div class="install-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                <!-- ÉTAPE 1: Prérequis -->
                <h4 class="fw-bold mb-4"><i class="bi bi-check2-square text-primary me-2"></i>Vérification des Prérequis</h4>
                <?php foreach ($checks as $key => $check): ?>
                <div class="check-item">
                    <div class="check-icon <?= $check['ok'] ? 'check-ok' : 'check-fail' ?>">
                        <i class="bi <?= $check['ok'] ? 'bi-check-lg' : 'bi-x-lg' ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= $check['label'] ?></div>
                        <small class="text-muted"><?= $check['value'] ?></small>
                    </div>
                </div>
                <?php endforeach; ?>

                <form method="post" class="mt-4">
                    <input type="hidden" name="action" value="check_requirements">
                    <button type="submit" class="btn btn-primary btn-install w-100" <?= !$all_ok ? 'disabled' : '' ?>>
                        <?= $all_ok ? 'Continuer <i class="bi bi-arrow-right ms-2"></i>' : 'Corrigez les erreurs ci-dessus' ?>
                    </button>
                </form>

                <?php elseif ($step === 2): ?>
                <!-- ÉTAPE 2: Base de données -->
                <h4 class="fw-bold mb-4"><i class="bi bi-database text-primary me-2"></i>Configuration Base de Données</h4>
                <form method="post">
                    <input type="hidden" name="action" value="setup_database">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Serveur MySQL</label>
                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom de la base de données</label>
                        <input type="text" class="form-control" name="db_name" value="berradi_print" required>
                        <div class="form-text">La base sera créée automatiquement si elle n'existe pas.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Utilisateur MySQL</label>
                        <input type="text" class="form-control" name="db_user" value="root" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mot de passe MySQL</label>
                        <input type="password" class="form-control" name="db_pass" value="">
                    </div>
                    <div class="d-flex gap-2">
                        <a href="install.php?step=1" class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-primary btn-install flex-grow-1">
                            Installer la base <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>

                <?php elseif ($step === 3): ?>
                <!-- ÉTAPE 3: Configuration du site -->
                <h4 class="fw-bold mb-4"><i class="bi bi-globe text-primary me-2"></i>Configuration du Site</h4>
                <form method="post">
                    <input type="hidden" name="action" value="setup_site">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom de l'entreprise</label>
                        <input type="text" class="form-control" name="site_name" value="BERRADI PRINT" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL du site</label>
                        <input type="url" class="form-control" name="site_url" value="<?= 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email de contact</label>
                        <input type="email" class="form-control" name="site_email" value="contact@berradiprint.ma" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" class="form-control" name="site_phone" value="+212 6XX-XXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Adresse</label>
                        <input type="text" class="form-control" name="site_address" value="Maroc">
                    </div>
                    <div class="d-flex gap-2">
                        <a href="install.php?step=2" class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-primary btn-install flex-grow-1">
                            Continuer <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>

                <?php elseif ($step === 4): ?>
                <!-- ÉTAPE 4: Compte administrateur -->
                <h4 class="fw-bold mb-4"><i class="bi bi-person-gear text-primary me-2"></i>Compte Administrateur</h4>
                <form method="post">
                    <input type="hidden" name="action" value="setup_admin">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nom</label>
                            <input type="text" class="form-control" name="admin_nom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Prénom</label>
                            <input type="text" class="form-control" name="admin_prenom" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" name="admin_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" class="form-control" name="admin_phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mot de passe</label>
                        <input type="password" class="form-control" name="admin_pass" minlength="6" required>
                        <div class="form-text">Minimum 6 caractères</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                        <input type="password" class="form-control" name="admin_pass_confirm" minlength="6" required>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="install.php?step=3" class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-primary btn-install flex-grow-1">
                            Terminer l'installation <i class="bi bi-check-lg ms-2"></i>
                        </button>
                    </div>
                </form>

                <?php elseif ($step === 5): ?>
                <!-- ÉTAPE 5: Terminé! -->
                <div class="text-center py-4">
                    <div class="success-icon mb-3"><i class="bi bi-check-circle-fill"></i></div>
                    <h3 class="fw-bold text-success mb-3">Installation Terminée!</h3>
                    <p class="text-muted mb-4">
                        BERRADI PRINT a été installé avec succès. Vous pouvez maintenant accéder à votre site et au panneau d'administration.
                    </p>
                    <div class="alert alert-warning text-start">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Pour des raisons de sécurité, supprimez le fichier <code>install.php</code> après l'installation.
                    </div>
                    <div class="d-flex gap-3 justify-content-center mt-4">
                        <a href="index.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-globe me-2"></i>Voir le site
                        </a>
                        <a href="admin/index.php" class="btn btn-primary btn-lg btn-install">
                            <i class="bi bi-speedometer2 me-2"></i>Administration
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-3">
            <small class="text-white opacity-75">BERRADI PRINT v1.0.0 - Installation Wizard</small>
        </div>
    </div>
</body>
</html>
