<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Services\Auth;
use App\Services\Crypt;
use PDO;

/**
 * Performs the installation work: DB checks, .env writing, migrations, seeding, admin creation, lock.
 */
final class InstallerService
{
    public function __construct(private string $basePath)
    {
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string} $cfg
     * @return array{ok:bool,version?:string,server?:string,error?:string,checks?:list<array{label:string,ok:bool,current:string}>}
     */
    public function testConnection(array $cfg): array
    {
        try {
            $pdo = Database::connect($cfg);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->cleanError($e->getMessage())];
        }
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $isMaria = stripos($version, 'mariadb') !== false;
        preg_match('/^(\d+\.\d+\.\d+)/', $version, $m);
        $num = $m[1] ?? '0.0.0';
        $min = $isMaria ? '10.3.0' : '5.7.0';
        $checks = [];
        $checks[] = ['label' => ($isMaria ? 'MariaDB' : 'MySQL') . ' >= ' . $min, 'ok' => version_compare($num, $min, '>='), 'current' => $version];

        $innodb = false;
        foreach ($pdo->query('SHOW ENGINES')->fetchAll(PDO::FETCH_ASSOC) as $engine) {
            if (strcasecmp((string) $engine['Engine'], 'InnoDB') === 0 && in_array(strtoupper((string) $engine['Support']), ['YES', 'DEFAULT'], true)) {
                $innodb = true;
            }
        }
        $checks[] = ['label' => 'InnoDB', 'ok' => $innodb, 'current' => $innodb ? 'available' : 'missing'];

        $charset = (string) $pdo->query("SELECT @@character_set_database")->fetchColumn();
        $checks[] = ['label' => 'utf8mb4', 'ok' => true, 'current' => 'database default: ' . $charset . ' (tables are created as utf8mb4)'];

        $canCreate = true;
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS bwa_install_probe (id INT PRIMARY KEY, ref INT NULL) ENGINE=InnoDB');
            $pdo->exec('DROP TABLE bwa_install_probe');
        } catch (\Throwable $e) {
            $canCreate = false;
        }
        $checks[] = ['label' => 'CREATE / DROP privileges', 'ok' => $canCreate, 'current' => $canCreate ? 'granted' : 'denied'];

        $existing = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $checks[] = ['label' => 'Existing tables', 'ok' => true, 'current' => (string) $existing];

        $ok = true;
        foreach ($checks as $c) {
            $ok = $ok && $c['ok'];
        }
        return ['ok' => $ok, 'version' => $version, 'server' => $isMaria ? 'MariaDB' : 'MySQL', 'checks' => $checks, 'existing_tables' => $existing];
    }

    private function cleanError(string $msg): string
    {
        // never echo the password back
        return preg_replace('/\(using password: (YES|NO)\)/', '', $msg) ?? $msg;
    }

    /**
     * @param array<string,string|int|bool> $values
     */
    public function envContent(array $values): string
    {
        return Env::build($values);
    }

    /** @param array<string,string|int|bool> $values */
    public function writeEnv(array $values): bool
    {
        $content = $this->envContent($values);
        $target = $this->basePath . '/.env';
        $tmp = $target . '.tmp' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /**
     * Build the full set of .env values for a fresh installation.
     *
     * @param array{host:string,port:int,database:string,username:string,password:string} $db
     * @return array<string,string|int|bool>
     */
    public function envValues(array $db, string $appUrl, string $locale, ?string $existingKey = null): array
    {
        return [
            'APP_NAME' => 'BERRADI WALL ART',
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => rtrim($appUrl, '/'),
            'APP_KEY' => $existingKey && preg_match('/^[0-9a-f]{64}$/', $existingKey) ? $existingKey : Crypt::generateKey(),
            'APP_TIMEZONE' => 'Africa/Casablanca',
            'APP_LOCALE' => $locale,
            'ADMIN_PATH' => 'admin',
            'DB_HOST' => $db['host'],
            'DB_PORT' => $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
            'SESSION_NAME' => 'bwa_session',
            'SESSION_LIFETIME' => 120,
            'SESSION_SECURE' => 'auto',
            'UPLOAD_MAX_MB' => 25,
            'LOGIN_MAX_ATTEMPTS' => 5,
            'LOGIN_DECAY_MINUTES' => 15,
        ];
    }

    /**
     * Run migrations and seeders.
     *
     * @return list<string> log lines
     */
    public function installDatabase(Database $db): array
    {
        $log = [];
        $migrator = new Migrator($db, $this->basePath . '/database/migrations', $this->basePath . '/database/seeders');
        $migrator->migrate(function (string $line) use (&$log): void {
            $log[] = $line;
        });
        $migrator->seed(null, function (string $line) use (&$log): void {
            $log[] = $line;
        });
        $log[] = 'Tables: ' . count($migrator->tables());
        return $log;
    }

    /** Create (or update) the super administrator. */
    public function createAdmin(Database $db, string $name, string $email, string $password, string $locale = 'fr'): int
    {
        $roleId = (int) $db->value("SELECT id FROM roles WHERE slug = 'super_admin'");
        if ($roleId === 0) {
            throw new \RuntimeException('Roles are not seeded');
        }
        $email = mb_strtolower(trim($email));
        $existing = $db->value('SELECT id FROM users WHERE email = ?', [$email]);
        $data = [
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'password_hash' => Auth::hashPassword($password),
            'locale' => $locale,
            'is_active' => 1,
        ];
        if ($existing !== null) {
            $db->update('users', $data, ['id' => (int) $existing]);
            return (int) $existing;
        }
        return $db->insert('users', $data);
    }

    public function lock(string $version): bool
    {
        $content = json_encode([
            'installed_at' => date('c'),
            'version' => $version,
            'php' => PHP_VERSION,
        ], JSON_PRETTY_PRINT);
        return @file_put_contents($this->basePath . '/storage/installed.lock', (string) $content, LOCK_EX) !== false;
    }
}
