<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host' => (string) Env::get('DB_HOST', 'localhost'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => (string) Env::get('DB_DATABASE', ''),
    'username' => (string) Env::get('DB_USERNAME', ''),
    'password' => (string) Env::get('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];
