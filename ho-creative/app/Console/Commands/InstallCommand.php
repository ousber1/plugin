<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'hocreative:install {--email=} {--name=} {--password=} {--demo : Load clearly labelled demo data}';

    protected $description = 'Migrate, seed reference data, create the first owner account and lock the web installer';

    public function handle(): int
    {
        if (file_exists(storage_path('app/installed.lock')) && ! $this->confirm('Application déjà installée. Relancer migrations + seed de référence ?', false)) {
            return self::SUCCESS;
        }
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);
        if ($this->option('demo')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
        }
        $email = $this->option('email') ?: $this->ask('E-mail du propriétaire');
        $name = $this->option('name') ?: $this->ask('Nom', 'Propriétaire');
        $password = $this->option('password') ?: $this->secret('Mot de passe (10 caractères min.)');
        if (strlen((string) $password) < 10) {
            $this->error('Mot de passe trop court.');

            return self::FAILURE;
        }
        $u = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'locale' => 'fr']);
        $u->forceFill(['is_staff' => true, 'is_active' => true])->save();
        $u->syncRoles(['owner']);
        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }
        file_put_contents(storage_path('app/installed.lock'), 'installed '.now()->toIso8601String());
        @unlink(storage_path('app/install-token.txt'));
        $this->info("Installation terminée. Propriétaire : {$email}. Installateur web verrouillé.");

        return self::SUCCESS;
    }
}
