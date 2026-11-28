<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'BERRADI WALL ART'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'key' => (string) Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'Africa/Casablanca'),
    'locale' => Env::get('APP_LOCALE', 'fr'),
    'locales' => ['fr' => 'Français', 'ar' => 'العربية'],
    'admin_path' => trim((string) Env::get('ADMIN_PATH', 'admin'), '/') ?: 'admin',
    'version' => '1.2.0',

    'session' => [
        'name' => Env::get('SESSION_NAME', 'bwa_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
        'secure' => (static function (): string {
            $v = Env::get('SESSION_SECURE', 'auto');
            return is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        })(),
        'path' => dirname(__DIR__) . '/storage/sessions',
    ],

    'security' => [
        'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_minutes' => (int) Env::get('LOGIN_DECAY_MINUTES', 15),
        'password_reset_minutes' => 60,
    ],

    'requirements' => [
        'php' => '8.2.0',
        'mysql' => '5.7.0',
        'mariadb' => '10.3.0',
    ],
];
