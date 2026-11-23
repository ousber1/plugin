<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Install extends Command
{
    protected $signature = 'academy:install {--demo : Seed demo courses, articles and resources} {--force : Run in production without confirmation}';

    protected $description = 'Check requirements, migrate, link storage, seed base data and create the first administrator';

    public function handle(): int
    {
        $this->info('Academy installer');

        $ok = true;
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath|intl', 'gd'] as $ext) {
            $any = collect(explode('|', $ext))->contains(fn ($e) => extension_loaded($e));
            $this->line(sprintf('  %-14s %s', $ext, $any ? '<info>OK</info>' : '<error>MISSING</error>'));
            $ok = $ok && ($any || in_array($ext, ['gd', 'bcmath|intl', 'pdo_mysql'], true));
        }
        foreach ([storage_path(), storage_path('app/private'), storage_path('app/public'), base_path('bootstrap/cache')] as $dir) {
            @mkdir($dir, 0775, true);
            $writable = is_writable($dir);
            $this->line(sprintf('  %-40s %s', str_replace(base_path().'/', '', $dir), $writable ? '<info>writable</info>' : '<error>NOT writable</error>'));
            $ok = $ok && $writable;
        }
        $this->line(sprintf('  upload_max_filesize=%s post_max_size=%s (chunk size %d MB)', ini_get('upload_max_filesize'), ini_get('post_max_size'), config('academy.video.chunk_mb')));
        if (! $ok) {
            $this->error('Fix the items above and run the installer again.');

            return self::FAILURE;
        }
        if (! config('app.key')) {
            $this->call('key:generate', ['--force' => true]);
        }
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('Database connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->call('migrate', ['--force' => $this->option('force') || $this->confirm('Run database migrations now?', true)]);
        $this->call('storage:link');
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\BaseSeeder', '--force' => true]);
        if ($this->option('demo') || $this->confirm('Seed demo content (clearly marked as demo in the dashboard)?', false)) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
        }
        if ($this->confirm('Create the first administrator now?', true)) {
            $this->call('academy:create-admin');
        }
        $this->info('Done. Review the settings in /admin/settings (WhatsApp number, contact details, branding).');

        return self::SUCCESS;
    }
}
