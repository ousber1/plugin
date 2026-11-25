<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentification et autorisations.
 * - Mots de passe : password_hash (bcrypt/argon2 selon PHP), rehash automatique.
 * - Anti force brute : verrouillage par compte + limitation par adresse IP.
 * - Permissions : rôle + surcharges utilisateur, mises en cache en session.
 */
final class Auth
{
    private const SESSION_KEY = '_auth_user_id';
    private const IP_MAX_FAILURES = 30; // par fenêtre de verrouillage
    /** Hash bcrypt valide servant à égaliser le temps de réponse si le compte n'existe pas. */
    private const DUMMY_HASH = '$2y$10$o2.0nyVAUfZE/CtRelgyce0CqBSPfy0gUh5ScNXypKULTUVM8KjNC';
    private static ?array $user = null;
    private static ?array $permissions = null;

    /**
     * Tente une connexion. Retourne [bool succès, string message].
     */
    public static function attempt(string $identifier, string $password): array
    {
        $db = Database::instance();
        $identifier = mb_strtolower(trim($identifier));
        $ip = Request::ip();
        $maxAttempts = max(3, (int) Settings::get('max_login_attempts', 5));
        $lockMinutes = max(1, (int) Settings::get('lockout_minutes', 15));

        // Limitation par IP (protège contre l'énumération de comptes)
        $ipFailures = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [$ip, $lockMinutes]
        );
        if ($ipFailures >= self::IP_MAX_FAILURES) {
            self::recordAttempt($identifier, false);
            return [false, "Trop de tentatives depuis cette adresse. Réessayez dans {$lockMinutes} minutes."];
        }

        $user = $db->fetch(
            'SELECT * FROM users WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND deleted_at IS NULL LIMIT 1',
            [$identifier, $identifier]
        );

        $generic = 'Identifiant ou mot de passe incorrect.';

        if (!$user) {
            // Temps de réponse constant : on vérifie un hash factice
            password_verify($password, self::DUMMY_HASH);
            self::recordAttempt($identifier, false);
            return [false, $generic];
        }

        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            self::recordAttempt($identifier, false);
            $minutes = (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60);
            return [false, "Compte temporairement verrouillé. Réessayez dans {$minutes} minute(s)."];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $failed = (int) $user['failed_attempts'] + 1;
            $lockedUntil = $failed >= $maxAttempts ? date('Y-m-d H:i:s', time() + $lockMinutes * 60) : null;
            $db->update('users', [
                'failed_attempts' => $lockedUntil ? 0 : $failed,
                'locked_until'    => $lockedUntil,
            ], 'id = :id', ['id' => $user['id']]);
            self::recordAttempt($identifier, false);
            if ($lockedUntil) {
                AuditLog::log('account_locked', 'security', 'user', (int) $user['id'], 'Compte verrouillé après échecs de connexion', null, null, (int) $user['id']);
                return [false, "Trop de tentatives. Compte verrouillé pendant {$lockMinutes} minutes."];
            }
            return [false, $generic];
        }

        if ((int) $user['is_active'] !== 1) {
            self::recordAttempt($identifier, false);
            return [false, 'Ce compte est désactivé. Contactez l\'administrateur.'];
        }

        // Rehash si l'algorithme par défaut a évolué
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        $db->update('users', [
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $ip,
        ], 'id = :id', ['id' => $user['id']]);

        self::recordAttempt($identifier, true);
        self::login((int) $user['id']);
        AuditLog::log('login', 'security', 'user', (int) $user['id'], 'Connexion réussie');
        return [true, 'Bienvenue ' . $user['full_name'] . ' !'];
    }

    public static function login(int $userId): void
    {
        Session::regenerate();     // anti fixation de session
        Csrf::rotate();
        $_SESSION[self::SESSION_KEY] = $userId;
        unset($_SESSION['_permissions']);
        self::$user = null;
        self::$permissions = null;
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditLog::log('logout', 'security', 'user', self::id(), 'Déconnexion');
        }
        Session::destroy();
        self::$user = null;
        self::$permissions = null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        return $id === null ? null : (int) $id;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $user = Database::instance()->fetch(
            'SELECT u.id, u.role_id, u.full_name, u.username, u.email, u.phone, u.avatar, u.theme, u.is_active,
                    r.code AS role_code, r.name AS role_name
               FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
        if (!$user || (int) $user['is_active'] !== 1) {
            unset($_SESSION[self::SESSION_KEY]);
            return null;
        }
        return self::$user = $user;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role_code'] ?? '') === 'admin';
    }

    /** Liste des codes de permission de l'utilisateur courant. */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $user = self::user();
        if (!$user) {
            return self::$permissions = [];
        }
        // Cache de session invalidé dès qu'un rôle ou une surcharge change (version globale)
        $version = (int) Settings::get('permissions_version', 0);
        if (isset($_SESSION['_permissions']) && is_array($_SESSION['_permissions'])
            && (int) ($_SESSION['_permissions_version'] ?? -1) === $version) {
            return self::$permissions = $_SESSION['_permissions'];
        }
        $db = Database::instance();
        $rows = $db->fetchAll(
            'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [$user['role_id']]
        );
        $perms = array_fill_keys(array_column($rows, 'code'), true);
        foreach ($db->fetchAll(
            'SELECT p.code, up.allowed FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?',
            [$user['id']]
        ) as $override) {
            if ((int) $override['allowed'] === 1) {
                $perms[$override['code']] = true;
            } else {
                unset($perms[$override['code']]);
            }
        }
        $_SESSION['_permissions'] = array_keys($perms);
        $_SESSION['_permissions_version'] = $version;
        return self::$permissions = $_SESSION['_permissions'];
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::isAdmin()) {
            return true;
        }
        return in_array($permission, self::permissions(), true);
    }

    /**
     * Invalide le cache de permissions de TOUS les utilisateurs connectés
     * (après modification d'un rôle ou des droits d'un utilisateur).
     */
    public static function refreshPermissions(): void
    {
        Settings::set('permissions_version', (int) Settings::get('permissions_version', 0) + 1, 'system', 'int');
        unset($_SESSION['_permissions']);
        self::$permissions = null;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Vérifie la robustesse d'un mot de passe. Retourne la liste des erreurs.
     */
    public static function passwordErrors(string $password, int $minLength = 10): array
    {
        $errors = [];
        if (mb_strlen($password) < $minLength) {
            $errors[] = "Le mot de passe doit contenir au moins {$minLength} caractères.";
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir des minuscules et des majuscules.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }
        return $errors;
    }

    private static function recordAttempt(string $identifier, bool $success): void
    {
        $db = Database::instance();
        $db->insert('login_attempts', [
            'identifier' => mb_substr($identifier, 0, 190),
            'ip_address' => Request::ip(),
            'success'    => $success ? 1 : 0,
            'user_agent' => Request::userAgent(),
        ]);
        // Purge des tentatives de plus de 30 jours (1 chance sur 50)
        if (random_int(1, 50) === 1) {
            $db->query('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 30 DAY)');
        }
    }
}
