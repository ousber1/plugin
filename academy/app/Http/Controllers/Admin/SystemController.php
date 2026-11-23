<?php

namespace App\Http\Controllers\Admin;

use App\Support\Audit;
use Illuminate\Support\Facades\Artisan;

/** One-click database update after uploading a new version (no terminal needed). */
class SystemController extends AdminController
{
    public static function pendingMigrations(): array
    {
        try {
            $migrator = app('migrator');
            if (! $migrator->repositoryExists()) {
                return [];
            }
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));

            return array_values(array_diff($files, $migrator->getRepository()->getRan()));
        } catch (\Throwable) {
            return [];
        }
    }

    public function migrate()
    {
        $pending = self::pendingMigrations();
        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('view:clear');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذّر تحديث قاعدة البيانات: '.$e->getMessage());
        }
        \App\Support\Settings::flush();
        Audit::log('system.migrated', null, ['migrations' => $pending]);

        return back()->with('success', 'تم تحديث قاعدة البيانات ('.count($pending).' تحديث).');
    }
}
