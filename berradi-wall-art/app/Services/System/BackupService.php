<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Core\Database;
use App\Services\ActivityLogger;

/**
 * Database backups written in pure PHP (no mysqldump / shell access needed on shared hosting):
 * a gzip-compressed SQL file in storage/backups (deny-all), restorable with phpMyAdmin → Import.
 * Rows are streamed table by table, so memory stays low; secrets stay encrypted as stored.
 */
final class BackupService
{
    public static function dir(): string
    {
        return storage_path('backups');
    }

    /** @return string file name */
    public static function create(string $reason = 'manual'): string
    {
        $db = Database::instance();
        $pdo = $db->pdo();
        $name = 'bwa-db-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.sql.gz';
        $path = self::dir() . '/' . $name;
        $tmp = $path . '.part';
        $gz = gzopen($tmp, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException(__('backups.err_write'));
        }
        try {
            $w = fn (string $s) => gzwrite($gz, $s);
            $w("-- BERRADI WALL ART database backup\n-- Created " . date('Y-m-d H:i:s') . " (" . $reason . "), application " . config('app.version') . "\n");
            $w("-- Restore: phpMyAdmin > Import (into an empty database), or `gunzip < file | mysql dbname`\n\n");
            $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
            foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM) as [$table]) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) {
                    continue;
                }
                $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_NUM)[1];
                $w("DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
                $stmt = $pdo->query('SELECT * FROM `' . $table . '`', \PDO::FETCH_ASSOC);
                $batch = [];
                $cols = null;
                foreach ($stmt as $row) {
                    $cols ??= '`' . implode('`, `', array_keys($row)) . '`';
                    $batch[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                    if (count($batch) >= 200) {
                        $w("INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $w("INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                $w("\n");
            }
            $w("SET FOREIGN_KEY_CHECKS = 1;\n-- end of backup\n");
        } finally {
            gzclose($gz);
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException(__('backups.err_write'));
        }
        @chmod($path, 0640);
        ActivityLogger::log('backup.created', null, null, $name . ' (' . $reason . ')');
        return $name;
    }

    /** @return list<array{name:string,size:int,time:int}> newest first */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/bwa-db-*.sql.gz') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /** Absolute path of a backup, or null (only names produced by create() are accepted). */
    public static function path(string $name): ?string
    {
        if (!preg_match('/^bwa-db-\d{8}-\d{6}-[a-f0-9]{8}\.sql\.gz$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }

    public static function delete(string $name): bool
    {
        $p = self::path($name);
        if ($p === null) {
            return false;
        }
        unlink($p);
        ActivityLogger::log('backup.deleted', null, null, $name);
        return true;
    }

    /** Keep the N newest backups. */
    public static function rotate(int $keep): int
    {
        $n = 0;
        foreach (array_slice(self::list(), max(1, $keep)) as $b) {
            if (self::delete($b['name'])) {
                $n++;
            }
        }
        return $n;
    }

    /** Cron: one automatic backup per day when enabled. */
    public static function daily(): ?string
    {
        if (!(bool) setting('backup_auto', true)) {
            return null;
        }
        $latest = self::list()[0] ?? null;
        if ($latest !== null && $latest['time'] > time() - 23 * 3600) {
            return null;
        }
        $name = self::create('auto');
        self::rotate((int) setting('backup_keep', 14));
        return $name;
    }
}
