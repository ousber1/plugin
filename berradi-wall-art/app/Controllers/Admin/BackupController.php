<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use App\Services\System\BackupService;

/** Database backups and data-retention settings. */
final class BackupController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/backups', [
            'backups' => BackupService::list(),
            'settings' => [
                'backup_auto' => (bool) setting('backup_auto', true), 'backup_keep' => (int) setting('backup_keep', 14),
                'retention_unordered_days' => (int) setting('retention_unordered_days', 30), 'retention_ordered_days' => (int) setting('retention_ordered_days', 365),
                'retention_logs_days' => (int) setting('retention_logs_days', 365), 'retention_notifications_days' => (int) setting('retention_notifications_days', 180),
                'retention_carts_days' => (int) setting('retention_carts_days', 90),
            ],
            'gz' => function_exists('gzopen'),
        ]);
    }

    public function create(): Response
    {
        @set_time_limit(300);
        try {
            $name = BackupService::create('manual');
            $this->flash('success', __('backups.created', ['name' => $name]));
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Backup failed: ' . $e->getMessage());
            $this->flash('danger', __('backups.err_write'));
        }
        return $this->redirectRoute('admin.backups.index');
    }

    public function download(string $name): Response
    {
        $path = BackupService::path($name);
        if ($path === null) {
            throw new HttpException(404);
        }
        ActivityLogger::log('backup.downloaded', null, null, $name);
        return Response::file($path, 'application/gzip', $name, false);
    }

    public function destroy(string $name): Response
    {
        if (!BackupService::delete($name)) {
            throw new HttpException(404);
        }
        $this->flash('success', __('common.deleted'));
        return $this->redirectRoute('admin.backups.index');
    }

    public function settings(): Response
    {
        $d = $this->validate([
            'backup_keep' => 'required|integer|between:1,365',
            'retention_unordered_days' => 'required|integer|between:7,3650', 'retention_ordered_days' => 'required|integer|between:30,3650',
            'retention_logs_days' => 'required|integer|between:30,3650', 'retention_notifications_days' => 'required|integer|between:30,3650',
            'retention_carts_days' => 'required|integer|between:7,3650',
        ]);
        foreach ($d as $k => $v) {
            SettingsService::set($k, (int) $v);
        }
        SettingsService::set('backup_auto', $this->request->bool('backup_auto') ? 1 : 0);
        ActivityLogger::log('settings.retention', null, null, json_encode($d) ?: '');
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.backups.index');
    }
}
