<?php

/*
 * Router for PHP's built-in web server (local development & automated HTTP tests only):
 *   php -S 127.0.0.1:8080 -t public tests/server-router.php
 * Mimics Apache: serves existing public files, denies dotfiles, everything else -> index.php.
 */

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (preg_match('#/\.#', $path) || preg_match('#^/uploads/.*\.(php\d?|phtml|phar)$#i', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
$file = __DIR__ . '/../public' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../public/index.php';
