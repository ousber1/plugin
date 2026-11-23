<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time web installer for hosts without SSH.
 *  - Inactive (404) as soon as storage/app/installed.lock exists.
 *  - Proof of server access: the operator must paste the token written to
 *    storage/app/install-token.txt (readable via cPanel File Manager only).
 *  - Stateless (no session/cookies) because APP_KEY may not exist yet.
 */
class InstallController extends Controller
{
    protected function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    protected function tokenPath(): string
    {
        return storage_path('app/install-token.txt');
    }

    protected function guard(): void
    {
        abort_if(file_exists($this->lockPath()), 404);
    }

    public function show()
    {
        $this->guard();
        if (! file_exists($this->tokenPath())) {
            @mkdir(dirname($this->tokenPath()), 0775, true);
            file_put_contents($this->tokenPath(), Str::random(40));
            @chmod($this->tokenPath(), 0600);
        }
        $checks = [
            'PHP ≥ 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'pdo_mysql' => extension_loaded('pdo_mysql'), 'mbstring' => extension_loaded('mbstring'), 'gd' => extension_loaded('gd'),
            'intl' => extension_loaded('intl'), 'fileinfo' => extension_loaded('fileinfo'), 'zip' => extension_loaded('zip'), 'openssl' => extension_loaded('openssl'),
            'storage/ inscriptible' => is_writable(storage_path()), 'bootstrap/cache inscriptible' => is_writable(base_path('bootstrap/cache')),
            '.env inscriptible' => file_exists(base_path('.env')) ? is_writable(base_path('.env')) : is_writable(base_path()),
        ];

        return response()->view('install.show', ['checks' => $checks, 'url' => request()->getSchemeAndHttpHost(), 'error' => null, 'done' => false]);
    }

    public function run(Request $request)
    {
        $this->guard();
        $token = trim((string) @file_get_contents($this->tokenPath()));
        if (! $token || ! hash_equals($token, trim((string) $request->input('token')))) {
            return response()->view('install.show', ['checks' => [], 'url' => $request->input('app_url'), 'error' => 'Jeton d\'installation invalide.', 'done' => false], 403);
        }
        $v = validator($request->all(), [
            'app_url' => 'required|url', 'db_host' => 'required', 'db_port' => 'required|integer', 'db_database' => 'required', 'db_username' => 'required', 'db_password' => 'nullable',
            'admin_name' => 'required|max:120', 'admin_email' => 'required|email', 'admin_password' => 'required|min:10',
            'mail_host' => 'nullable', 'mail_port' => 'nullable|integer', 'mail_username' => 'nullable', 'mail_password' => 'nullable', 'mail_from' => 'nullable|email', 'demo' => 'nullable',
        ]);
        if ($v->fails()) {
            return response()->view('install.show', ['checks' => [], 'url' => $request->input('app_url'), 'error' => implode(' ', $v->errors()->all()), 'done' => false], 422);
        }
        $d = $v->validated();
        try {
            new \PDO("mysql:host={$d['db_host']};port={$d['db_port']};dbname={$d['db_database']}", $d['db_username'], $d['db_password'] ?? '');
        } catch (\Throwable $e) {
            return response()->view('install.show', ['checks' => [], 'url' => $d['app_url'], 'error' => 'Connexion MySQL impossible : '.$e->getMessage(), 'done' => false], 422);
        }
        $env = file_get_contents(base_path('.env.example'));
        $set = function (string $k, ?string $val) use (&$env) {
            $val = (string) $val;
            $q = preg_match('/[\s#"\'\\\\$]/', $val) ? '"'.addcslashes($val, '"\\$').'"' : $val;
            $env = preg_match("/^{$k}=.*$/m", $env) ? preg_replace("/^{$k}=.*$/m", $k.'='.str_replace('$', '\$', $q), $env) : $env."\n{$k}={$q}";
        };
        $set('APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        $set('APP_ENV', 'production');
        $set('APP_DEBUG', 'false');
        $set('APP_URL', rtrim($d['app_url'], '/'));
        $set('DB_CONNECTION', 'mysql');
        foreach (['host', 'port', 'database', 'username', 'password'] as $k) {
            $set('DB_'.strtoupper($k), (string) ($d['db_'.$k] ?? ''));
        }
        if (! empty($d['mail_host'])) {
            $set('MAIL_MAILER', 'smtp');
            $set('MAIL_HOST', $d['mail_host']);
            $set('MAIL_PORT', (string) ($d['mail_port'] ?? 587));
            $set('MAIL_USERNAME', $d['mail_username'] ?? '');
            $set('MAIL_PASSWORD', $d['mail_password'] ?? '');
            $set('MAIL_FROM_ADDRESS', $d['mail_from'] ?? $d['admin_email']);
        }
        file_put_contents(base_path('.env'), $env);
        @chmod(base_path('.env'), 0640);

        // Apply the new DB config to this request and migrate.
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => $d['db_host'], 'database.connections.mysql.port' => $d['db_port'],
            'database.connections.mysql.database' => $d['db_database'], 'database.connections.mysql.username' => $d['db_username'],
            'database.connections.mysql.password' => $d['db_password'] ?? '']);
        // Nothing may still point at the pre-install default connection (sqlite) or database cache.
        config(['cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync']);
        DB::purge();
        DB::setDefaultConnection('mysql');
        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
        \Illuminate\Support\Facades\Cache::clearResolvedInstances();
        app(\Spatie\Permission\PermissionRegistrar::class)->initializeCache();
        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder', '--force' => true]);
            if (! empty($d['demo'])) {
                Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
            }
            $owner = User::updateOrCreate(['email' => $d['admin_email']], ['name' => $d['admin_name'], 'password' => $d['admin_password'], 'locale' => 'fr']);
            $owner->forceFill(['is_staff' => true, 'is_active' => true])->save();
            $owner->syncRoles(['owner']);
            @symlink(storage_path('app/public'), public_path('storage')) || Artisan::call('storage:link');
        } catch (\Throwable $e) {
            report($e);

            return response()->view('install.show', ['checks' => [], 'url' => $d['app_url'], 'error' => 'Erreur pendant l\'installation : '.$e->getMessage(), 'done' => false], 500);
        }
        file_put_contents($this->lockPath(), 'installed '.now()->toIso8601String());
        @unlink($this->tokenPath());

        return response()->view('install.show', ['checks' => [], 'url' => $d['app_url'], 'error' => null, 'done' => true]);
    }
}
