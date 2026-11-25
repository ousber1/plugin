<?php
declare(strict_types=1);

/**
 * HO CREATIVE — assistant d'installation.
 * Étapes : prérequis → base de données → entreprise → paramètres → administrateur → installation.
 * Une fois l'installation terminée, storage/installed.lock bloque définitivement cet assistant.
 */

define('BASE_PATH', dirname(__DIR__));

// Session dédiée à l'installation (démarrée avant le contrôle du verrou pour
// pouvoir afficher l'écran de fin d'installation une seule fois)
$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
if (is_dir(BASE_PATH . '/storage/sessions') && is_writable(BASE_PATH . '/storage/sessions')) {
    session_save_path(BASE_PATH . '/storage/sessions');
}
session_name('HOCINSTALL');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => $https, 'path' => '/']);
session_start();

// Verrou : aucune ré-installation possible tant que le fichier existe
if (is_file(BASE_PATH . '/storage/installed.lock')) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $done = $_SESSION['install_done'] ?? null;
    if (is_array($done)) {
        // Écran de succès affiché une seule fois, puis la session d'installation est détruite
        $_SESSION = [];
        session_destroy();
        require __DIR__ . '/views/done.php';
        exit;
    }
    http_response_code(403);
    require __DIR__ . '/views/locked.php';
    exit;
}

require BASE_PATH . '/app/bootstrap.php';   // autoloader + helpers (retourne false : non installé)
require __DIR__ . '/Installer.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$_SESSION['install'] ??= ['step' => 1];
$state = &$_SESSION['install'];

const STEPS = [1 => 'Prérequis', 2 => 'Base de données', 3 => 'Entreprise', 4 => 'Paramètres', 5 => 'Administrateur', 6 => 'Installation'];

$errors = [];
$notice = null;
$step = (int) ($_GET['step'] ?? $state['step']);
$step = max(1, min($step, (int) $state['step'], 6)); // impossible de sauter une étape

/** Champ texte POST nettoyé. */
function in_str(string $key, int $max = 255): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['install_csrf'], $token)) {
        $errors[] = 'Jeton de sécurité invalide. Rechargez la page et recommencez.';
    } else {
        $posted = (int) ($_POST['step'] ?? 0);
        switch ($posted) {
            case 1:
                if (Installer::requirementsMet(Installer::requirements())) {
                    $state['step'] = max($state['step'], 2);
                    $step = 2;
                } else {
                    $errors[] = 'Certains prérequis obligatoires ne sont pas remplis.';
                    $step = 1;
                }
                break;

            case 2:
                $dbData = [
                    'host'        => in_str('db_host', 190) ?: 'localhost',
                    'port'        => (int) ($_POST['db_port'] ?? 3306) ?: 3306,
                    'name'        => in_str('db_name', 64),
                    'user'        => in_str('db_user', 190),
                    'pass'        => is_string($_POST['db_pass'] ?? null) ? $_POST['db_pass'] : '',
                    'create'      => !empty($_POST['db_create']),
                    'app_url'     => rtrim(in_str('app_url', 255), '/'),
                    'pretty_urls' => !empty($_POST['pretty_urls']),
                ];
                // Conserver le mot de passe déjà saisi si le champ est laissé vide
                if ($dbData['pass'] === '' && !empty($state['database']['pass']) && !empty($_POST['keep_pass'])) {
                    $dbData['pass'] = $state['database']['pass'];
                }
                if ($dbData['name'] === '' || $dbData['user'] === '') {
                    $errors[] = 'Le nom de la base et l\'utilisateur sont obligatoires.';
                }
                if (!filter_var($dbData['app_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $dbData['app_url'])) {
                    $errors[] = 'L\'URL de l\'application est invalide.';
                }
                if (!$errors) {
                    [$ok, $msg] = Installer::testDatabase($dbData, $dbData['create']);
                    if ($ok) {
                        $state['database'] = $dbData;
                        $state['step'] = max($state['step'], 3);
                        $step = 3;
                        $notice = $msg;
                    } else {
                        $errors[] = $msg;
                    }
                }
                if ($errors) {
                    $state['database_draft'] = array_diff_key($dbData, ['pass' => true]);
                    $step = 2;
                }
                break;

            case 3:
                $business = [];
                foreach (['business_name' => 150, 'business_activity' => 190, 'business_address' => 255, 'business_city' => 100,
                          'business_phone' => 30, 'business_whatsapp' => 30, 'business_email' => 190, 'business_website' => 190,
                          'business_ice' => 20, 'business_if' => 20, 'business_rc' => 30, 'business_patente' => 30,
                          'business_cnss' => 30, 'business_capital' => 50, 'bank_name' => 100, 'bank_rib' => 30, 'bank_swift' => 15] as $k => $max) {
                    $business[$k] = in_str($k, $max);
                }
                $state['business'] = $business;
                $errors = Installer::validateBusiness($business);
                if (!$errors) {
                    $state['step'] = max($state['step'], 4);
                    $step = 4;
                } else {
                    $step = 3;
                }
                break;

            case 4:
                $settings = [
                    'currency_code'      => strtoupper(in_str('currency_code', 3)),
                    'currency_symbol'    => in_str('currency_symbol', 5),
                    'currency_position'  => in_str('currency_position', 6),
                    'currency_decimals'  => (int) ($_POST['currency_decimals'] ?? 2),
                    'tax_enabled'        => !empty($_POST['tax_enabled']),
                    'prices_include_tax' => !empty($_POST['prices_include_tax']),
                    'default_tax_id'     => (int) ($_POST['default_tax_id'] ?? 1),
                    'quotation_validity_days' => (int) ($_POST['quotation_validity_days'] ?? 30),
                    'invoice_due_days'   => (int) ($_POST['invoice_due_days'] ?? 30),
                    'default_payment_terms' => in_str('default_payment_terms', 500),
                    'number_format'      => in_str('number_format', 60),
                    'number_padding'     => (int) ($_POST['number_padding'] ?? 4),
                    'reset_policy'       => in_str('reset_policy', 10),
                ];
                foreach (array_keys(Installer::NUMBERED_DOCS) as $type) {
                    $settings['prefix_' . $type] = strtoupper(in_str('prefix_' . $type, 10));
                    $settings['start_' . $type] = (int) ($_POST['start_' . $type] ?? 1);
                }
                $state['settings'] = $settings;
                $errors = Installer::validateSettings($settings);
                if (!$errors) {
                    $state['step'] = max($state['step'], 5);
                    $step = 5;
                } else {
                    $step = 4;
                }
                break;

            case 5:
                $admin = [
                    'full_name'        => in_str('full_name', 150),
                    'username'         => in_str('username', 60),
                    'email'            => in_str('email', 190),
                    'phone'            => in_str('phone', 30),
                    'password'         => is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
                    'password_confirm' => is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '',
                ];
                $errors = Installer::validateAdmin($admin);
                $state['admin_draft'] = array_diff_key($admin, ['password' => 1, 'password_confirm' => 1]);
                if (!$errors) {
                    unset($admin['password_confirm']);
                    $state['admin'] = $admin;
                    $state['step'] = max($state['step'], 6);
                    $step = 6;
                } else {
                    $step = 5;
                }
                break;

            case 6:
                if (empty($state['database']) || empty($state['business']) || empty($state['settings']) || empty($state['admin'])) {
                    $errors[] = 'Des informations sont manquantes. Reprenez les étapes précédentes.';
                    $step = 1;
                    break;
                }
                @set_time_limit(300);
                [$ok, $msg] = Installer::run($state);
                if ($ok) {
                    $done = ['username' => $state['admin']['username'], 'url' => $state['database']['app_url']];
                    $_SESSION = ['install_done' => $done];
                    session_regenerate_id(true);
                    header('Location: index.php');
                    exit;
                }
                $errors[] = $msg;
                $step = 6;
                break;
        }
    }
}

$csrf = $_SESSION['install_csrf'] ?? '';
$s = $state ?? [];
/** Valeur d'un champ mémorisé en session. */
$val = static function (string $group, string $key, mixed $default = '') use ($s): mixed {
    return $s[$group][$key] ?? $s[$group . '_draft'][$key] ?? $default;
};

include __DIR__ . '/views/layout.php';
