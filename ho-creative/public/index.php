<?php
/*
 * HO CREATIVE – entry point.
 * Supported layouts (detected automatically):
 *  1. Simple cPanel: everything extracted into public_html, application in public_html/hocreative
 *  2. public_html + application next to it (~/hocreative)
 *  3. Classic Laravel: this file in <app>/public
 * This top part is written for old PHP versions on purpose, so it can explain the problem
 * instead of showing a blank "500" page.
 */
define('LARAVEL_START', microtime(true));

$hocBase = null;
foreach (array(__DIR__.'/hocreative', __DIR__.'/../hocreative', __DIR__.'/..') as $hocCandidate) {
    if (is_file($hocCandidate.'/bootstrap/app.php')) {
        $hocBase = realpath($hocCandidate);
        break;
    }
}

$hocProblems = array();
if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    $hocProblems[] = 'Version PHP actuelle : <b>'.PHP_VERSION.'</b>. HO CREATIVE nécessite <b>PHP 8.3 ou plus</b>.<br>cPanel → <i>Logiciel</i> → <b>MultiPHP Manager</b> (ou <b>Select PHP Version</b>) → choisissez <b>PHP 8.3</b> (ou 8.4) pour ce domaine, puis rechargez cette page.';
}
foreach (array('pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'gd', 'intl', 'zip', 'curl', 'dom') as $hocExt) {
    if (! extension_loaded($hocExt)) {
        $hocProblems[] = 'Extension PHP manquante : <b>'.$hocExt.'</b> (cPanel → Select PHP Version → Extensions → cochez « '.$hocExt.' »).';
    }
}
if ($hocBase === null || ! is_file($hocBase.'/vendor/autoload.php')) {
    $hocProblems[] = 'Dossier <b>hocreative</b> introuvable à côté de ce fichier. Extrayez toute l\'archive directement dans <b>public_html</b> (vous devez voir <code>public_html/index.php</code> et <code>public_html/hocreative/</code>).';
} else {
    foreach (array('storage', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache') as $hocDir) {
        if (! is_dir($hocBase.'/'.$hocDir) || ! is_writable($hocBase.'/'.$hocDir)) {
            $hocProblems[] = 'Le dossier <b>hocreative/'.$hocDir.'</b> doit être accessible en écriture (permissions 755 ou 775).';
        }
    }
}
if ($hocProblems) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HO CREATIVE – vérification</title>'
        .'<body style="font-family:system-ui,Arial;max-width:760px;margin:40px auto;padding:0 16px;color:#1B1F3B">'
        .'<h1 style="color:#E4007C">HO CREATIVE – configuration de l\'hébergement</h1><p>Corrigez ces points dans cPanel puis rechargez la page :</p><ol>';
    foreach ($hocProblems as $hocP) {
        echo '<li style="margin:10px 0">'.$hocP.'</li>';
    }
    echo '</ol></body>';
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $hocBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $hocBase.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $hocBase.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(\Illuminate\Http\Request::capture());
