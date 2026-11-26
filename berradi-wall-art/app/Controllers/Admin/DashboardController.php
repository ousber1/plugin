<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;

/**
 * Admin home. Phase 1 shows real system information only (no fake statistics);
 * business KPIs and charts are added in Phase 5 from real order data.
 */
final class DashboardController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $system = [
            'php' => PHP_VERSION,
            'db' => (string) $db->value('SELECT VERSION()'),
            'tables' => (int) $db->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'),
            'migrations' => (int) $db->value('SELECT COUNT(*) FROM migrations'),
            'users' => (int) $db->value('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            'categories' => (int) $db->value('SELECT COUNT(*) FROM categories'),
            'gd' => extension_loaded('gd'),
            'vendor' => is_file(base_path('vendor/autoload.php')),
            'https' => $this->request->isSecure(),
            'debug' => (bool) config('app.debug'),
        ];
        $setupChecklist = [
            ['label' => __('admin.check_company'), 'done' => (string) setting('company_email', '') !== '' && (string) setting('company_phone', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_logo'), 'done' => (string) setting('company_logo', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_legal'), 'done' => (string) setting('company_ice', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_mail'), 'done' => (string) setting('mail_driver', 'mail') === 'smtp' && (string) setting('mail_host', '') !== '', 'url' => route('admin.settings', ['tab' => 'mail'])],
            ['label' => __('admin.check_whatsapp'), 'done' => (string) setting('company_whatsapp', '') !== '', 'url' => route('admin.settings', ['tab' => 'company'])],
            ['label' => __('admin.check_https'), 'done' => $this->request->isSecure(), 'url' => null],
        ];
        $recentActivity = $db->fetchAll(
            "SELECT a.*, u.name AS user_name FROM activity_logs a
             LEFT JOIN users u ON a.actor_type = 'user' AND u.id = a.actor_id
             ORDER BY a.id DESC LIMIT 8"
        );
        return $this->view('admin/dashboard', compact('system', 'setupChecklist', 'recentActivity'));
    }
}
