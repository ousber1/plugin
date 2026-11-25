<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Accès normalisé à la requête HTTP.
 */
final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        // Proxy / hébergement mutualisé derrière un équilibreur
        return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Route courante, ex. "customers/edit". */
    public static function route(): string
    {
        $route = $_GET['route'] ?? '';
        if (!is_string($route)) {
            return '__invalid__';
        }
        if ($route === '' && !empty($_SERVER['PATH_INFO'])) {
            $route = (string) $_SERVER['PATH_INFO'];
        }
        if ($route === '') {
            // Réécriture sans paramètre (ex. Nginx try_files /index.php) : route déduite de l'URL
            $path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
            $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            if ($base !== '' && str_starts_with($path, $base . '/')) {
                $path = substr($path, strlen($base));
            }
            $route = preg_replace('#^/index\.php#', '', $path) ?? '';
        }
        $route = trim((string) $route, '/');
        // Seuls caractères autorisés dans une route
        return preg_match('#^[a-z0-9_\-/]*$#i', $route) ? strtolower($route) : '__invalid__';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** Chaîne nettoyée (trim, sans caractères de contrôle). */
    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);
        if (!is_scalar($value)) {
            return $default;
        }
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '');
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    /** Nombre décimal, accepte la virgule française. */
    public static function decimal(string $key, float $default = 0.0): float
    {
        $value = self::input($key);
        if (!is_scalar($value)) {
            return $default;
        }
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $value);
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
