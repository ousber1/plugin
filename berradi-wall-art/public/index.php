<?php

declare(strict_types=1);

/*
 * BERRADI WALL ART — front controller.
 *
 * Standard deployment: the domain document root points to this "public" folder.
 * Alternative (document root cannot be changed): copy the CONTENT of this folder into public_html
 * and create public_html/app_path.php returning the absolute path of the application folder,
 * e.g.  <?php return '/home/USER/berradi-wall-art';
 */

$appPath = dirname(__DIR__);
if (is_file(__DIR__ . '/app_path.php')) {
    $custom = require __DIR__ . '/app_path.php';
    if (is_string($custom) && is_dir($custom)) {
        $appPath = rtrim($custom, '/');
    }
}

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(500);
    echo 'BERRADI WALL ART requires PHP 8.2 or newer. Current version: ' . htmlspecialchars(PHP_VERSION);
    exit;
}

$boot = require $appPath . '/app/bootstrap.php';
$app = $boot(__DIR__);
$response = $app->handle(\App\Core\Request::capture());
$response->send();
