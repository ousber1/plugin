<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Migrations de base de données (mises à jour du schéma).
 *
 * Chaque fichier database/migrations/AAAA_MM_JJ_NNN_description.sql est appliqué
 * une seule fois puis enregistré dans schema_migrations. Les migrations sont
 * écrites pour être rejouables sans dommage (CREATE TABLE IF NOT EXISTS,
 * INSERT IGNORE…), car le DDL MySQL n'est pas transactionnel.
 *
 * Appliquées automatiquement après une mise à jour des fichiers (extraction du
 * ZIP par-dessus l'installation) : aucune manipulation SQL n'est demandée.
 */
final class Migrator
{
    private const DIR = BASE_PATH . '/database/migrations';
    private const STAMP = BASE_PATH . '/storage/cache/schema.version';

    /** @return array<string, string> version => chemin */
    public static function available(): array
    {
        $out = [];
        foreach (glob(self::DIR . '/*.sql') ?: [] as $file) {
            $version = basename($file, '.sql');
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{3}_[a-z0-9_]+$/', $version)) {
                $out[$version] = $file;
            }
        }
        ksort($out);
        return $out;
    }

    /** Dernière version disponible (sert d'empreinte pour éviter toute requête inutile). */
    public static function latest(): string
    {
        $all = array_keys(self::available());
        return $all ? (string) end($all) : '';
    }

    /**
     * Applique les migrations en attente si nécessaire. Appel très léger quand la
     * base est à jour (lecture d'un fichier cache).
     */
    public static function autoMigrate(): void
    {
        $latest = self::latest();
        if ($latest === '' || (is_file(self::STAMP) && trim((string) file_get_contents(self::STAMP)) === $latest)) {
            return;
        }
        self::migrate(Database::instance()->pdo());
    }

    /**
     * @return string[] versions appliquées
     */
    public static function migrate(\PDO $pdo): array
    {
        // Verrou MySQL : deux requêtes simultanées ne lancent pas deux fois la même migration
        $locked = (int) $pdo->query("SELECT GET_LOCK('hoc_migrations', 30)")->fetchColumn();
        if ($locked !== 1) {
            throw new \RuntimeException('Mise à jour de la base déjà en cours. Réessayez dans un instant.');
        }
        try {
            // Les identifiants de migration sont descriptifs : colonne élargie si besoin (schéma 1.0.0 : 30 caractères)
            $width = (int) $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'schema_migrations' AND column_name = 'version'")->fetchColumn();
            if ($width > 0 && $width < 100) {
                $pdo->exec('ALTER TABLE schema_migrations MODIFY version VARCHAR(100) NOT NULL');
            }
            $done = array_flip($pdo->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN));
            $applied = [];
            foreach (self::available() as $version => $file) {
                if (isset($done[$version])) {
                    continue;
                }
                $sql = (string) file_get_contents($file);
                $description = preg_match('/^--\s*(.+)$/m', $sql, $m) ? mb_substr(trim($m[1]), 0, 255) : $version;
                foreach (self::split($sql) as $statement) {
                    $pdo->exec($statement);
                }
                $pdo->prepare('INSERT INTO schema_migrations (version, description) VALUES (?, ?)')->execute([$version, $description]);
                $applied[] = $version;
                Logger::info('Migration appliquée : ' . $version);
            }
            $dir = dirname(self::STAMP);
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents(self::STAMP, self::latest());
            }
            return $applied;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('hoc_migrations')");
        }
    }

    /** Découpe un script SQL (chaînes entre apostrophes et commentaires « -- » respectés). */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if (!$inString && $ch === '-' && ($sql[$i + 1] ?? '') === '-') {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl;
                $buffer .= "\n";
                continue;
            }
            if ($ch === "'") {
                if ($inString && ($sql[$i + 1] ?? '') === "'") {
                    $buffer .= "''";
                    $i++;
                    continue;
                }
                $inString = !$inString;
            }
            if ($ch === '\\' && $inString) {
                $buffer .= $ch . ($sql[$i + 1] ?? '');
                $i++;
                continue;
            }
            if ($ch === ';' && !$inString) {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}
