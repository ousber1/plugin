<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session sécurisée : cookies HttpOnly/SameSite, mode strict, expiration
 * d'inactivité, régénération d'identifiant, empreinte navigateur et messages flash.
 */
final class Session
{
    private const NAME = 'HOCSESSID';

    public static function start(int $lifetimeMinutes = 120): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $savePath = BASE_PATH . '/storage/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) max(1800, $lifetimeMinutes * 60));

        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::cookiePath(),
            'secure'   => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();

        // Expiration d'inactivité
        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $lifetimeMinutes * 60) {
            self::destroy();
            session_start();
            $_SESSION['_flash']['warning'] = 'Votre session a expiré. Veuillez vous reconnecter.';
        }

        // Empreinte navigateur : détecte un vol de cookie grossier
        $fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (isset($_SESSION['_fingerprint']) && !hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
            self::destroy();
            session_start();
        }
        $_SESSION['_fingerprint'] = $fingerprint;

        // Régénération périodique de l'identifiant (toutes les 15 min)
        if (!isset($_SESSION['_regenerated_at'])) {
            $_SESSION['_regenerated_at'] = $now;
        } elseif ($now - (int) $_SESSION['_regenerated_at'] > 900) {
            self::regenerate();
        }

        $_SESSION['_last_activity'] = $now;
        self::ageFlash();
    }

    private static function cookiePath(): string
    {
        $path = (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_PATH) ?? '');
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path . '/';
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_at'] = time();
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /* ---------- Messages flash (affichés à la requête suivante) ---------- */

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /** Retourne et consomme les messages flash de la requête précédente. */
    public static function getFlashes(): array
    {
        $flashes = $_SESSION['_flash_old'] ?? [];
        unset($_SESSION['_flash_old']);
        return $flashes;
    }

    private static function ageFlash(): void
    {
        $_SESSION['_flash_old'] = array_merge($_SESSION['_flash_old'] ?? [], $_SESSION['_flash'] ?? []);
        unset($_SESSION['_flash']);
    }

    /* ---------- Anciennes valeurs de formulaire ---------- */

    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirm'], $input['_csrf']);
        $_SESSION['_old_input'] = $input;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input_prev'][$key] ?? $default;
    }

    /** Appelé en début de requête : déplace les valeurs pour la requête courante. */
    public static function rotateOldInput(): void
    {
        $_SESSION['_old_input_prev'] = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);
    }
}
