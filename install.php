<?php
/**
 * BERRADI PRINT - Installation Wizard
 * Compatible avec tous les hébergements (cPanel, Plesk, VPS, localhost)
 *
 * Accédez à ce fichier via: http://votre-domaine/install.php
 * IMPORTANT: Supprimez ce fichier après l'installation!
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Empêcher l'accès si déjà installé
$lock_file = __DIR__ . '/config/.installed';
if (file_exists($lock_file)) {
    die('
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Déjà installé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    </head><body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container"><div class="row justify-content-center"><div class="col-md-6">
    <div class="card shadow"><div class="card-body text-center p-5">
    <i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
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

// ============================================
// TRAITEMENT DES FORMULAIRES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- ÉTAPE 1: Vérification des prérequis ----
    if ($action === 'check_requirements') {
        header('Location: install.php?step=2');
        exit;
    }

    // ---- ÉTAPE 2: Configuration base de données ----
    if ($action === 'setup_database') {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';

        if (empty($db_name) || empty($db_user)) {
            $error = 'Le nom de la base de données et l\'utilisateur sont obligatoires.';
        } else {
            $pdo = null;

            // MÉTHODE 1: Connexion directe à la base existante (cPanel / hébergement mutualisé)
            try {
                $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e1) {
                // MÉTHODE 2: La base n'existe pas encore, on essaie de la créer (VPS / localhost)
                try {
                    $dsn_no_db = "mysql:host={$db_host};charset=utf8mb4";
                    $pdo_temp = new PDO($dsn_no_db, $db_user, $db_pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo_temp = null;

                    // Reconnexion avec la base créée
                    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_user, $db_pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                } catch (PDOException $e2) {
                    $error = 'Impossible de se connecter à la base de données.<br><br>';
                    $error .= '<strong>Erreur:</strong> ' . htmlspecialchars($e1->getMessage()) . '<br><br>';
                    $error .= '<strong>Vérifiez:</strong><ul>';
                    $error .= '<li>Le nom de la base de données est correct</li>';
                    $error .= '<li>L\'utilisateur et le mot de passe sont corrects</li>';
                    $error .= '<li>L\'utilisateur est assigné à la base de données dans cPanel</li>';
                    $error .= '<li>Le serveur MySQL est bien <code>' . htmlspecialchars($db_host) . '</code></li>';
                    $error .= '</ul>';
                }
            }

            // Si connexion réussie, importer les tables
            if ($pdo && empty($error)) {
                try {
                    // Importer le schéma SQL
                    $sql_file = __DIR__ . '/database/schema.sql';
                    if (!file_exists($sql_file)) {
                        $error = 'Fichier schema.sql introuvable dans le dossier database/';
                    } else {
                        $sql_content = file_get_contents($sql_file);

                        // Nettoyer le SQL: retirer CREATE DATABASE et USE
                        $sql_content = preg_replace('/^\s*CREATE\s+DATABASE\s+.*?;\s*$/mi', '', $sql_content);
                        $sql_content = preg_replace('/^\s*USE\s+.*?;\s*$/mi', '', $sql_content);

                        // Découper proprement les requêtes SQL (gère les ; dans les chaînes)
                        $statements = splitSqlStatements($sql_content);

                        $executed = 0;
                        $errors_sql = [];
                        foreach ($statements as $stmt_sql) {
                            $stmt_sql = trim($stmt_sql);
                            if (empty($stmt_sql)) continue;
                            // Ignorer les commentaires seuls
                            if (preg_match('/^--/', $stmt_sql) && strpos($stmt_sql, "\n") === false) continue;

                            try {
                                $pdo->exec($stmt_sql);
                                $executed++;
                            } catch (PDOException $e) {
                                // Ignorer les erreurs "table already exists"
                                if (strpos($e->getMessage(), '1050') === false) {
                                    $errors_sql[] = $e->getMessage();
                                }
                            }
                        }

                        if ($executed === 0 && !empty($errors_sql)) {
                            $error = 'Erreurs lors de l\'import SQL:<br>' . implode('<br>', array_slice($errors_sql, 0, 3));
                        } else {
                            // SUCCÈS: Écrire le fichier config/database.php
                            $config_db = '<?php' . "\n";
                            $config_db .= "/**\n * BERRADI PRINT - Configuration Base de Données\n * Généré automatiquement par l'installateur\n */\n\n";
                            $config_db .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
                            $config_db .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
                            $config_db .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
                            $config_db .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
                            $config_db .= "define('DB_CHARSET', 'utf8mb4');\n\n";
                            $config_db .= "// Connexion PDO\n";
                            $config_db .= "function getDB() {\n";
                            $config_db .= "    static \$pdo = null;\n";
                            $config_db .= "    if (\$pdo === null) {\n";
                            $config_db .= "        try {\n";
                            $config_db .= "            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;\n";
                            $config_db .= "            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [\n";
                            $config_db .= "                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
                            $config_db .= "                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
                            $config_db .= "                PDO::ATTR_EMULATE_PREPARES => false,\n";
                            $config_db .= "            ]);\n";
                            $config_db .= "        } catch (PDOException \$e) {\n";
                            $config_db .= "            die(\"Erreur de connexion à la base de données: \" . \$e->getMessage());\n";
                            $config_db .= "        }\n";
                            $config_db .= "    }\n";
                            $config_db .= "    return \$pdo;\n";
                            $config_db .= "}\n";

                            if (file_put_contents(__DIR__ . '/config/database.php', $config_db) === false) {
                                $error = 'Impossible d\'écrire le fichier config/database.php. Vérifiez les permissions du dossier config/.';
                            } else {
                                $_SESSION['install_db_host'] = $db_host;
                                $_SESSION['install_db_name'] = $db_name;
                                $_SESSION['install_db_user'] = $db_user;
                                $_SESSION['install_db_pass'] = $db_pass;
                                $_SESSION['install_db'] = true;
                                $_SESSION['install_sql_count'] = $executed;

                                header('Location: install.php?step=3');
                                exit;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erreur lors de l\'import: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }

    // ---- ÉTAPE 3: Configuration du site ----
    if ($action === 'setup_site') {
        $site_name = trim($_POST['site_name'] ?? 'BERRADI PRINT');
        $site_url = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $site_email = trim($_POST['site_email'] ?? '');
        $site_phone = trim($_POST['site_phone'] ?? '');
        $site_address = trim($_POST['site_address'] ?? '');

        // Écrire config/app.php
        $config_app = '<?php' . "\n";
        $config_app .= "/**\n * BERRADI PRINT - Configuration Générale\n * Généré automatiquement par l'installateur\n */\n\n";
        $config_app .= "// Informations de l'entreprise\n";
        $config_app .= "define('APP_NAME', " . var_export($site_name, true) . ");\n";
        $config_app .= "define('APP_TAGLINE', 'Services d\\'Impression Professionnels');\n";
        $config_app .= "define('APP_VERSION', '1.0.0');\n";
        $config_app .= "define('APP_URL', " . var_export($site_url, true) . ");\n";
        $config_app .= "define('APP_EMAIL', " . var_export($site_email, true) . ");\n";
        $config_app .= "define('APP_PHONE', " . var_export($site_phone, true) . ");\n";
        $config_app .= "define('APP_ADDRESS', " . var_export($site_address, true) . ");\n";
        $config_app .= "define('APP_CURRENCY', 'MAD');\n";
        $config_app .= "define('APP_CURRENCY_SYMBOL', 'DH');\n";
        $config_app .= "define('APP_LANG', 'fr');\n";
        $config_app .= "define('APP_TIMEZONE', 'Africa/Casablanca');\n\n";
        $config_app .= "// Paramètres de livraison\n";
        $config_app .= "define('DELIVERY_METHOD', 'cash_on_delivery');\n";
        $config_app .= "define('FREE_DELIVERY_MIN', 500);\n";
        $config_app .= "define('DELIVERY_FEE', 30);\n\n";
        $config_app .= "// Paramètres d'upload\n";
        $config_app .= "define('UPLOAD_DIR', __DIR__ . '/../uploads/');\n";
        $config_app .= "define('MAX_FILE_SIZE', 50 * 1024 * 1024);\n";
        $config_app .= "define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'ai', 'psd', 'eps', 'svg', 'doc', 'docx', 'tiff', 'tif']);\n\n";
        $config_app .= "// Paramètres de session\n";
        $config_app .= "define('SESSION_LIFETIME', 3600 * 24);\n\n";
        $config_app .= "// TVA Maroc\n";
        $config_app .= "define('TAX_RATE', 0.20);\n\n";
        $config_app .= "date_default_timezone_set(APP_TIMEZONE);\n";

        if (file_put_contents(__DIR__ . '/config/app.php', $config_app) === false) {
            $error = 'Impossible d\'écrire le fichier config/app.php. Vérifiez les permissions.';
        } else {
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
                // Ignorer - les paramètres peuvent être mis à jour plus tard depuis l'admin
            }

            $_SESSION['install_site'] = true;
            header('Location: install.php?step=4');
            exit;
        }
    }

    // ---- ÉTAPE 4: Création du compte admin ----
    if ($action === 'setup_admin') {
        $admin_nom = trim($_POST['admin_nom'] ?? '');
        $admin_prenom = trim($_POST['admin_prenom'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_phone = trim($_POST['admin_phone'] ?? '');
        $admin_pass = $_POST['admin_pass'] ?? '';
        $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

        if (empty($admin_email) || empty($admin_pass)) {
            $error = 'L\'email et le mot de passe sont obligatoires.';
        } elseif ($admin_pass !== $admin_pass_confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($admin_pass) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            try {
                require_once __DIR__ . '/config/database.php';
                $pdo = getDB();
                $hash = password_hash($admin_pass, PASSWORD_DEFAULT);

                // Supprimer l'admin par défaut et créer le nouveau
                $pdo->exec("DELETE FROM admins WHERE email = 'admin@berradiprint.ma'");

                // Vérifier si l'email existe déjà
                $check = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
                $check->execute([$admin_email]);
                if ($check->fetch()) {
                    // Mettre à jour l'admin existant
                    $stmt = $pdo->prepare("UPDATE admins SET nom = ?, prenom = ?, telephone = ?, mot_de_passe = ?, role = 'super_admin' WHERE email = ?");
                    $stmt->execute([$admin_nom, $admin_prenom, $admin_phone, $hash, $admin_email]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO admins (nom, prenom, email, telephone, mot_de_passe, role) VALUES (?, ?, ?, ?, ?, 'super_admin')");
                    $stmt->execute([$admin_nom, $admin_prenom, $admin_email, $admin_phone, $hash]);
                }

                // Créer les dossiers nécessaires
                $dirs = [
                    __DIR__ . '/uploads',
                    __DIR__ . '/uploads/commandes',
                    __DIR__ . '/uploads/produits',
                    __DIR__ . '/uploads/imports',
                ];
                foreach ($dirs as $dir) {
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                }

                // Créer le fichier de verrouillage
                file_put_contents($lock_file, date('Y-m-d H:i:s') . "\nInstalled successfully\n");

                $_SESSION['install_complete'] = true;
                $_SESSION['install_admin_email'] = $admin_email;
                header('Location: install.php?step=5');
                exit;
            } catch (PDOException $e) {
                $error = 'Erreur: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Découpe un fichier SQL en requêtes individuelles
 * Gère correctement les ; dans les chaînes de caractères et les commentaires
 */
function splitSqlStatements($sql) {
    $statements = [];
    $current = '';
    $in_string = false;
    $string_char = '';
    $len = strlen($sql);
    $i = 0;

    while ($i < $len) {
        $char = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        // Gérer les commentaires en ligne --
        if (!$in_string && $char === '-' && $next === '-') {
            $end = strpos($sql, "\n", $i);
            if ($end === false) break;
            $i = $end + 1;
            continue;
        }

        // Gérer les commentaires en bloc /* */
        if (!$in_string && $char === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            if ($end === false) break;
            $i = $end + 2;
            continue;
        }

        // Entrée/sortie de chaîne
        if (($char === "'" || $char === '"') && !$in_string) {
            $in_string = true;
            $string_char = $char;
            $current .= $char;
            $i++;
            continue;
        }

        if ($in_string && $char === $string_char) {
            // Vérifier si c'est un caractère échappé
            if ($i > 0 && $sql[$i - 1] === '\\') {
                $current .= $char;
                $i++;
                continue;
            }
            // Vérifier le double quoting ('')
            if ($next === $string_char) {
                $current .= $char . $next;
                $i += 2;
                continue;
            }
            $in_string = false;
            $current .= $char;
            $i++;
            continue;
        }

        // Séparateur de requête ;
        if (!$in_string && $char === ';') {
            $trimmed = trim($current);
            if (!empty($trimmed)) {
                $statements[] = $trimmed;
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $char;
        $i++;
    }

    // Dernière requête sans ;
    $trimmed = trim($current);
    if (!empty($trimmed)) {
        $statements[] = $trimmed;
    }

    return $statements;
}

/**
 * Vérification des prérequis système
 */
function checkRequirements() {
    $checks = [];
    $checks['php_version'] = [
        'label' => 'PHP 7.4 ou supérieur',
        'ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'value' => 'Version ' . PHP_VERSION
    ];
    $checks['pdo'] = [
        'label' => 'Extension PDO MySQL',
        'ok' => extension_loaded('pdo_mysql'),
        'value' => extension_loaded('pdo_mysql') ? 'Activée' : 'Non installée'
    ];
    $checks['mbstring'] = [
        'label' => 'Extension mbstring',
        'ok' => extension_loaded('mbstring'),
        'value' => extension_loaded('mbstring') ? 'Activée' : 'Non installée'
    ];
    $checks['json'] = [
        'label' => 'Extension JSON',
        'ok' => extension_loaded('json'),
        'value' => extension_loaded('json') ? 'Activée' : 'Non installée'
    ];
    $checks['session'] = [
        'label' => 'Extension Session',
        'ok' => extension_loaded('session'),
        'value' => extension_loaded('session') ? 'Activée' : 'Non installée'
    ];
    $checks['config_writable'] = [
        'label' => 'Dossier config/ accessible en écriture',
        'ok' => is_writable(__DIR__ . '/config/'),
        'value' => is_writable(__DIR__ . '/config/') ? 'OK - Accessible' : 'Non accessible - chmod 755 config/'
    ];
    $checks['uploads_exists'] = [
        'label' => 'Dossier uploads/ existe',
        'ok' => is_dir(__DIR__ . '/uploads/'),
        'value' => is_dir(__DIR__ . '/uploads/') ? 'OK - Existe' : 'Manquant - créez le dossier uploads/'
    ];
    $checks['schema_exists'] = [
        'label' => 'Fichier database/schema.sql présent',
        'ok' => file_exists(__DIR__ . '/database/schema.sql'),
        'value' => file_exists(__DIR__ . '/database/schema.sql') ? 'OK - Trouvé' : 'Manquant!'
    ];
    return $checks;
}

// ============================================
// PRÉPARATION DE L'AFFICHAGE
// ============================================
$all_ok = true;
if ($step === 1) {
    $checks = checkRequirements();
    foreach ($checks as $c) {
        if (!$c['ok']) $all_ok = false;
    }
}

// Détecter l'URL du site automatiquement
$auto_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if ($script_dir && $script_dir !== '/' && $script_dir !== '\\') {
    $auto_url .= $script_dir;
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
        .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn-install { background: linear-gradient(135deg, #2563eb, #1e40af); border: none; padding: 12px 40px; font-weight: 600; border-radius: 8px; color: white; }
        .btn-install:hover { background: linear-gradient(135deg, #1d4ed8, #1e3a8a); color: white; }
        .success-icon { font-size: 80px; color: #16a34a; }
        .toggle-pass { cursor: pointer; }
        .alert ul { margin-bottom: 0; }
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
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $error ?></div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                <!-- ===== ÉTAPE 1: Prérequis ===== -->
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
                    <button type="submit" class="btn btn-install w-100" <?= !$all_ok ? 'disabled' : '' ?>>
                        <?= $all_ok ? 'Continuer <i class="bi bi-arrow-right ms-2"></i>' : 'Corrigez les erreurs ci-dessus' ?>
                    </button>
                </form>

                <?php elseif ($step === 2): ?>
                <!-- ===== ÉTAPE 2: Base de données ===== -->
                <h4 class="fw-bold mb-2"><i class="bi bi-database text-primary me-2"></i>Configuration Base de Données</h4>
                <p class="text-muted small mb-4">Entrez les informations de votre base de données MySQL. Sur un hébergement cPanel, créez d'abord la base et l'utilisateur dans cPanel > Bases de données MySQL.</p>

                <form method="post">
                    <input type="hidden" name="action" value="setup_database">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Serveur MySQL</label>
                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                        <div class="form-text">Généralement <code>localhost</code> sur la plupart des hébergements.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom de la base de données <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="db_name" value="" placeholder="ex: lobefuthkh_print" required>
                        <div class="form-text">La base de données doit exister (créez-la dans cPanel si nécessaire).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Utilisateur MySQL <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="db_user" value="" placeholder="ex: lobefuthkh_print" required>
                        <div class="form-text">L'utilisateur doit avoir tous les privilèges sur la base.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mot de passe MySQL</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="db_pass" id="dbPass" value="" placeholder="Votre mot de passe MySQL">
                            <button class="btn btn-outline-secondary toggle-pass" type="button" onclick="togglePassword('dbPass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-info small mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Hébergement cPanel:</strong> Allez dans cPanel > Bases de données MySQL > Créez la base, créez l'utilisateur, puis <strong>assignez l'utilisateur à la base</strong> avec tous les privilèges.
                    </div>

                    <div class="d-flex gap-2">
                        <a href="install.php?step=1" class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-install flex-grow-1">
                            Tester & Installer <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>

                <?php elseif ($step === 3): ?>
                <!-- ===== ÉTAPE 3: Configuration du site ===== -->
                <?php if (isset($_SESSION['install_sql_count'])): ?>
                <div class="alert alert-success small">
                    <i class="bi bi-check-circle me-1"></i>
                    Base de données installée avec succès! (<?= $_SESSION['install_sql_count'] ?> requêtes exécutées)
                </div>
                <?php endif; ?>
                <h4 class="fw-bold mb-4"><i class="bi bi-globe text-primary me-2"></i>Configuration du Site</h4>
                <form method="post">
                    <input type="hidden" name="action" value="setup_site">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom de l'entreprise</label>
                        <input type="text" class="form-control" name="site_name" value="BERRADI PRINT" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL du site</label>
                        <input type="url" class="form-control" name="site_url" value="<?= htmlspecialchars($auto_url) ?>" required>
                        <div class="form-text">L'adresse complète de votre site sans / à la fin.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email de contact</label>
                        <input type="email" class="form-control" name="site_email" value="" placeholder="contact@votredomaine.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" class="form-control" name="site_phone" value="" placeholder="+212 6XX-XXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Adresse</label>
                        <input type="text" class="form-control" name="site_address" value="Maroc" placeholder="Votre adresse complète">
                    </div>
                    <div class="d-flex gap-2">
                        <a href="install.php?step=2" class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-install flex-grow-1">
                            Continuer <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>

                <?php elseif ($step === 4): ?>
                <!-- ===== ÉTAPE 4: Compte administrateur ===== -->
                <h4 class="fw-bold mb-4"><i class="bi bi-person-gear text-primary me-2"></i>Compte Administrateur</h4>
                <form method="post">
                    <input type="hidden" name="action" value="setup_admin">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nom</label>
                            <input type="text" class="form-control" name="admin_nom" placeholder="Votre nom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Prénom</label>
                            <input type="text" class="form-control" name="admin_prenom" placeholder="Votre prénom" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="admin_email" placeholder="admin@votredomaine.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" class="form-control" name="admin_phone" placeholder="+212 6XX-XXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mot de passe <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="admin_pass" id="adminPass" minlength="6" required placeholder="Minimum 6 caractères">
                            <button class="btn btn-outline-secondary toggle-pass" type="button" onclick="togglePassword('adminPass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmer le mot de passe <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="admin_pass_confirm" id="adminPassConfirm" minlength="6" required>
                            <button class="btn btn-outline-secondary toggle-pass" type="button" onclick="togglePassword('adminPassConfirm', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="install.php?step=3" class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-install flex-grow-1">
                            Terminer l'installation <i class="bi bi-check-lg ms-2"></i>
                        </button>
                    </div>
                </form>

                <?php elseif ($step === 5): ?>
                <!-- ===== ÉTAPE 5: Terminé! ===== -->
                <div class="text-center py-4">
                    <div class="success-icon mb-3"><i class="bi bi-check-circle-fill"></i></div>
                    <h3 class="fw-bold text-success mb-3">Installation Terminée!</h3>
                    <p class="text-muted mb-4">
                        BERRADI PRINT a été installé avec succès sur votre serveur.
                    </p>

                    <?php if (isset($_SESSION['install_admin_email'])): ?>
                    <div class="alert alert-success text-start">
                        <i class="bi bi-person-check me-1"></i>
                        <strong>Compte admin créé:</strong> <?= htmlspecialchars($_SESSION['install_admin_email']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="alert alert-warning text-start">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Sécurité:</strong> Supprimez le fichier <code>install.php</code> de votre serveur.
                    </div>

                    <div class="d-flex gap-3 justify-content-center mt-4">
                        <a href="index.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-globe me-2"></i>Voir le site
                        </a>
                        <a href="admin/index.php" class="btn btn-install btn-lg">
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

    <script>
    function togglePassword(inputId, btn) {
        var input = document.getElementById(inputId);
        var icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }
    </script>
</body>
</html>
