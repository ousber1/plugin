<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Protection CSRF : jeton par session (synchronizer token).
 * Vérifié automatiquement par le routeur pour toute requête POST.
 * Les appels AJAX envoient le jeton dans l'en-tête X-CSRF-Token.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION[self::KEY] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    public static function verifyRequest(): bool
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return self::verify(is_string($token) ? $token : null);
    }

    /** Nouveau jeton (après connexion / déconnexion). */
    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
