<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Pure-PHP backup (no mysqldump needed): SQL dump of every table + private files,
 * zipped into storage/app/backups (outside the web root). Keeps the N most recent.
 */
class BackupCommand extends Command
{
    protected $signature = 'hocreative:backup {--keep=14} {--no-files}';

    protected $description = 'Create a database + private files backup in storage/app/backups';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        @mkdir($dir, 0750, true);
        $stamp = now()->format('Ymd-His');
        $sqlFile = "$dir/db-$stamp.sql";
        $this->dump($sqlFile);
        $zipPath = "$dir/backup-$stamp.zip";
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($sqlFile, 'database.sql');
        if (! $this->option('no-files')) {
            foreach (['private', 'public'] as $sub) {
                $base = storage_path("app/$sub");
                if (! is_dir($base)) {
                    continue;
                }
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    $zip->addFile($f->getPathname(), "files/$sub/".substr($f->getPathname(), strlen($base) + 1));
                }
            }
        }
        $zip->close();
        @unlink($sqlFile);
        $all = glob("$dir/backup-*.zip");
        rsort($all);
        foreach (array_slice($all, (int) $this->option('keep')) as $old) {
            @unlink($old);
        }
        $this->info('Sauvegarde : '.$zipPath.' ('.round(filesize($zipPath) / 1048576, 1).' Mo)');

        return self::SUCCESS;
    }

    protected function dump(string $file): void
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $fh = fopen($file, 'w');
        fwrite($fh, "-- HO CREATIVE backup ".now()->toIso8601String()." driver={$driver}\n");
        if ($driver === 'mysql' || $driver === 'mariadb') {
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
        }
        $tables = match ($driver) {
            'sqlite' => array_column(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"), 'name'),
            default => array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES')),
        };
        foreach ($tables as $t) {
            if ($driver !== 'sqlite') {
                $create = (array) DB::selectOne("SHOW CREATE TABLE `$t`");
                fwrite($fh, "DROP TABLE IF EXISTS `$t`;\n".array_values($create)[1].";\n");
            }
            foreach (DB::table($t)->cursor() as $row) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row);
                fwrite($fh, "INSERT INTO `$t` (`".implode('`,`', array_keys((array) $row)).'`) VALUES ('.implode(',', $vals).");\n");
            }
        }
        if ($driver === 'mysql' || $driver === 'mariadb') {
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        }
        fclose($fh);
    }
}
