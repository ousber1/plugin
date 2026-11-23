<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
 * Two supported layouts:
 *  - cPanel (default): this file is in ~/public_html and the application in ~/hocreative
 *    (site served directly at https://your-domain/ without changing the document root);
 *  - classic Laravel: this file is in <app>/public.
 */
$base = is_file(__DIR__.'/../hocreative/bootstrap/app.php') ? __DIR__.'/../hocreative' : __DIR__.'/..';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
