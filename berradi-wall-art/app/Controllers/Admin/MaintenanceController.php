<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Demo\DemoCatalog;
use App\Services\SettingsService;

/**
 * Updates without SSH: apply pending migrations + idempotent seeders after uploading a new version,
 * manage the demo catalogue, show system information.
 */
final class MaintenanceController extends Controller
{
    private function migrator(): Migrator
    {
        return new Migrator(Database::instance(), base_path('database/migrations'), base_path('database/seeders'));
    }

    public function index(): Response
    {
        $db = Database::instance();
        $m = $this->migrator();
        return $this->view('admin/maintenance', [
            'pending' => $m->pending(),
            'ran' => count($m->ran()),
            'demo' => (new DemoCatalog($db, public_path(), base_path()))->isInstalled(),
            'system' => [
                'app' => config('app.version'), 'php' => PHP_VERSION, 'db' => (string) $db->value('SELECT VERSION()'),
                'memory_limit' => (string) ini_get('memory_limit'), 'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'post_max_size' => (string) ini_get('post_max_size'), 'gd' => extension_loaded('gd'), 'vendor' => is_file(base_path('vendor/autoload.php')),
                'https' => $this->request->isSecure(), 'debug' => (bool) config('app.debug'),
                'tables' => (int) $db->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'),
                'private_uploads' => self::dirSize(storage_path('private_uploads')), 'public_uploads' => self::dirSize(public_path('uploads')),
                'last_cron' => (string) setting('last_cron_run', ''),
            ],
        ]);
    }

    private static function dirSize(string $dir): string
    {
        $bytes = 0;
        if (is_dir($dir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $bytes += $f->getSize();
            }
        }
        return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    }

    public function update(): Response
    {
        $m = $this->migrator();
        try {
            $done = $m->migrate();
            $m->seed();
            SettingsService::flush();
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Update failed: ' . $e->getMessage());
            $this->flash('danger', __('admin.update_failed') . ' ' . $e->getMessage());
            return $this->redirectRoute('admin.maintenance');
        }
        ActivityLogger::log('system.updated', null, null, implode(', ', $done) ?: 'seeders only');
        $this->flash('success', __('admin.update_done', ['n' => count($done)]));
        return $this->redirectRoute('admin.maintenance');
    }

    public function demo(): Response
    {
        $demo = new DemoCatalog(Database::instance(), public_path(), base_path());
        try {
            if ($this->request->str('action') === 'remove') {
                $r = $demo->remove();
                $this->flash('success', __('admin.demo_removed', ['n' => $r['products']]));
            } else {
                $r = $demo->install();
                $this->flash('success', __('admin.demo_installed', ['n' => $r['products']]));
            }
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        ActivityLogger::log('system.demo_' . ($this->request->str('action') === 'remove' ? 'removed' : 'installed'));
        return $this->redirectRoute('admin.maintenance');
    }
}
