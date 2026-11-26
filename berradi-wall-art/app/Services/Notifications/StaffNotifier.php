<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Database;

/**
 * In-app notifications for the team (bell in the admin bar). One row per recipient in `notifications`
 * (channel 'internal'): subject = message, body = relative admin link, status queued (unread) → read.
 * Recipients are the active staff whose role holds the permission (super admins always).
 */
final class StaffNotifier
{
    /**
     * @param string $link path relative to the admin root, e.g. "orders/12"
     * @param int $dedupeHours skip when the same event for the same record was already sent within this window
     * @return int notifications created
     */
    public static function notify(string $event, string $permission, string $message, string $link, ?string $relatedType = null, ?int $relatedId = null, int $dedupeHours = 0): int
    {
        $db = Database::instance();
        if ($dedupeHours > 0 && $relatedId !== null && (int) $db->value(
            "SELECT COUNT(*) FROM notifications WHERE channel = 'internal' AND event = ? AND related_type <=> ? AND related_id = ? AND created_at > NOW() - INTERVAL ? HOUR",
            [$event, $relatedType, $relatedId, $dedupeHours]
        ) > 0) {
            return 0;
        }
        $users = $db->fetchAll(
            "SELECT DISTINCT u.id, u.email FROM users u JOIN roles r ON r.id = u.role_id
             LEFT JOIN role_permissions rp ON rp.role_id = r.id LEFT JOIN permissions p ON p.id = rp.permission_id
             WHERE u.is_active = 1 AND (r.slug = 'super_admin' OR p.slug = ?)",
            [$permission]
        );
        foreach ($users as $u) {
            $db->insert('notifications', [
                'channel' => 'internal', 'event' => mb_substr($event, 0, 60), 'recipient' => mb_substr((string) $u['email'], 0, 191),
                'subject' => mb_substr($message, 0, 255), 'body' => ltrim($link, '/'), 'related_type' => $relatedType, 'related_id' => $relatedId,
                'user_id' => (int) $u['id'], 'status' => 'queued',
            ]);
        }
        return count($users);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::instance()->value("SELECT COUNT(*) FROM notifications WHERE channel = 'internal' AND user_id = ? AND status = 'queued'", [$userId]);
    }

    /** @return list<array<string,mixed>> */
    public static function latest(int $userId, int $limit = 8): array
    {
        return Database::instance()->fetchAll(
            "SELECT id, event, subject, body, status, created_at FROM notifications WHERE channel = 'internal' AND user_id = ? ORDER BY id DESC LIMIT " . max(1, $limit),
            [$userId]
        );
    }

    public static function markRead(int $userId, ?int $id = null): int
    {
        $sql = "UPDATE notifications SET status = 'read', read_at = NOW() WHERE channel = 'internal' AND user_id = ? AND status = 'queued'";
        $params = [$userId];
        if ($id !== null) {
            $sql .= ' AND id = ?';
            $params[] = $id;
        }
        return Database::instance()->query($sql, $params)->rowCount();
    }

    /**
     * Daily checks run by cron: materials under their threshold, overdue invoices, late production jobs.
     * Each alert is sent at most once per 24 h per record.
     */
    public static function dailyChecks(): int
    {
        $db = Database::instance();
        $n = 0;
        foreach ($db->fetchAll('SELECT id, name, stock_quantity, unit FROM materials WHERE is_active = 1 AND min_stock > 0 AND stock_quantity <= min_stock') as $m) {
            $n += self::notify('low_stock', 'inventory.view', __('notifications.low_stock', ['name' => $m['name'], 'qty' => rtrim(rtrim((string) $m['stock_quantity'], '0'), '.')]),
                'inventory/' . $m['id'], 'material', (int) $m['id'], 24);
        }
        foreach ($db->fetchAll("SELECT id, invoice_number, customer_name, due_date FROM invoices WHERE invoice_type = 'invoice' AND status IN ('issued','partially_paid') AND due_date < CURDATE()") as $i) {
            $n += self::notify('invoice_overdue', 'invoices.view', __('notifications.invoice_overdue', ['number' => $i['invoice_number'], 'name' => $i['customer_name']]),
                'invoices/' . $i['id'], 'invoice', (int) $i['id'], 24);
        }
        foreach ($db->fetchAll(
            "SELECT j.id, j.job_number, o.order_number FROM production_jobs j JOIN orders o ON o.id = j.order_id
             WHERE j.status IN ('pending','in_progress','on_hold') AND j.deadline_at < NOW() AND o.status NOT IN ('cancelled','returned')"
        ) as $j) {
            $n += self::notify('production_late', 'production.view', __('notifications.production_late', ['job' => $j['job_number'], 'order' => $j['order_number']]),
                'production/jobs/' . $j['id'], 'production_job', (int) $j['id'], 24);
        }
        return $n;
    }
}
