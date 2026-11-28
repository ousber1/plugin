<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Runs database/migrations/*.php in filename order and records them in `migrations`.
 * Each migration file returns ['up' => [sql, ...], 'down' => [sql, ...]].
 * Usable from the web installer (no SSH) and from bin/console.
 */
final class Migrator
{
    public function __construct(private Database $db, private string $migrationsPath, private string $seedersPath = '')
    {
    }

    public function ensureTable(): void
    {
        $this->db->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(191) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return list<string> migration names (without .php) */
    public function files(): array
    {
        $files = glob(rtrim($this->migrationsPath, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        return array_map(fn ($f) => basename($f, '.php'), $files);
    }

    /** @return list<string> */
    public function ran(): array
    {
        $this->ensureTable();
        return array_map('strval', $this->db->table('migrations')->orderBy('id')->pluck('migration'));
    }

    /** @return list<string> */
    public function pending(): array
    {
        return array_values(array_diff($this->files(), $this->ran()));
    }

    /**
     * @return list<string> migrations executed
     */
    public function migrate(?callable $output = null): array
    {
        $this->ensureTable();
        $pending = $this->pending();
        if ($pending === []) {
            return [];
        }
        $batch = (int) $this->db->value('SELECT COALESCE(MAX(batch), 0) FROM migrations') + 1;
        $done = [];
        foreach ($pending as $name) {
            $definition = require rtrim($this->migrationsPath, '/') . '/' . $name . '.php';
            if (!is_array($definition) || !isset($definition['up']) || !is_array($definition['up'])) {
                throw new \RuntimeException("Invalid migration file {$name}");
            }
            foreach ($definition['up'] as $i => $sql) {
                try {
                    $this->db->pdo()->exec($sql);
                } catch (\PDOException $e) {
                    throw new \RuntimeException("Migration {$name} failed at statement #{$i}: " . $e->getMessage(), 0, $e);
                }
            }
            $this->db->insert('migrations', ['migration' => $name, 'batch' => $batch]);
            $done[] = $name;
            if ($output) {
                $output("Migrated: {$name}");
            }
        }
        return $done;
    }

    /** Roll back the last batch. @return list<string> */
    public function rollback(?callable $output = null): array
    {
        $this->ensureTable();
        $batch = (int) $this->db->value('SELECT COALESCE(MAX(batch), 0) FROM migrations');
        if ($batch === 0) {
            return [];
        }
        $names = $this->db->table('migrations')->where('batch', $batch)->orderBy('id', 'DESC')->pluck('migration');
        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $done = [];
        try {
            foreach ($names as $name) {
                $definition = require rtrim($this->migrationsPath, '/') . '/' . $name . '.php';
                foreach ($definition['down'] ?? [] as $sql) {
                    try {
                        $this->db->pdo()->exec($sql);
                    } catch (\PDOException $e) {
                        // ignore missing objects on rollback (e.g. FK already dropped with its table)
                        if (!in_array((int) ($e->errorInfo[1] ?? 0), [1091, 1051, 1146], true)) {
                            throw $e;
                        }
                    }
                }
                $this->db->delete('migrations', ['migration' => $name]);
                $done[] = (string) $name;
                if ($output) {
                    $output("Rolled back: {$name}");
                }
            }
        } finally {
            $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        return $done;
    }

    /**
     * Run seeders (all, or a given list). Seeders are idempotent.
     *
     * @param list<string>|null $only
     * @return list<string>
     */
    public function seed(?array $only = null, ?callable $output = null): array
    {
        $files = glob(rtrim($this->seedersPath, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $done = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($only !== null && !in_array($name, $only, true)) {
                continue;
            }
            $seeder = require $file;
            if (!is_callable($seeder)) {
                throw new \RuntimeException("Seeder {$name} must return a callable");
            }
            $this->db->transaction(fn (Database $db) => $seeder($db));
            $done[] = $name;
            if ($output) {
                $output("Seeded: {$name}");
            }
        }
        return $done;
    }

    /** List tables currently present in the database. @return list<string> */
    public function tables(): array
    {
        return array_map(static fn (array $row): string => (string) array_values($row)[0], $this->db->fetchAll('SHOW TABLES'));
    }
}
