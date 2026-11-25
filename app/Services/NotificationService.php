<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Notifications internes.
 * Une notification cible un utilisateur (user_id), un rôle (role_id) ou tout
 * le monde (les deux à NULL). La lecture des notifications partagées est
 * suivie par utilisateur dans notification_reads.
 */
final class NotificationService
{
    /**
     * Crée une notification si son type est activé dans les paramètres.
     * $dedupKey évite les doublons non lus (ex. "low_stock:material:12").
     */
    public static function create(
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null,
        string $severity = 'info',
        ?int $userId = null,
        ?int $roleId = null,
        ?string $dedupKey = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): ?int {
        $db = Database::instance();
        $setting = $db->fetch('SELECT is_enabled, notify_role_id FROM notification_settings WHERE type = ?', [$type]);
        if ($setting && (int) $setting['is_enabled'] !== 1) {
            return null;
        }
        if ($userId === null && $roleId === null && $setting && $setting['notify_role_id'] !== null) {
            $roleId = (int) $setting['notify_role_id'];
        }
        if ($dedupKey !== null) {
            $exists = $db->fetchColumn(
                'SELECT id FROM notifications WHERE dedup_key = ? AND read_at IS NULL AND created_at > (NOW() - INTERVAL 7 DAY) LIMIT 1',
                [$dedupKey]
            );
            if ($exists) {
                return null;
            }
        }
        return $db->insert('notifications', [
            'user_id'     => $userId,
            'role_id'     => $roleId,
            'type'        => $type,
            'severity'    => in_array($severity, ['info', 'success', 'warning', 'danger'], true) ? $severity : 'info',
            'title'       => mb_substr($title, 0, 190),
            'message'     => $message !== null ? mb_substr($message, 0, 500) : null,
            'link'        => $link,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'dedup_key'   => $dedupKey,
        ]);
    }

    /** Condition SQL des notifications visibles par l'utilisateur courant. */
    private static function visibility(): array
    {
        $user = Auth::user();
        $sql = '(n.user_id = :uid OR (n.user_id IS NULL AND (n.role_id IS NULL OR n.role_id = :rid)))';
        $params = ['uid' => (int) ($user['id'] ?? 0), 'rid' => (int) ($user['role_id'] ?? 0)];
        // L'administrateur voit aussi les notifications adressées aux autres rôles
        if (Auth::isAdmin()) {
            $sql = '(n.user_id = :uid OR n.user_id IS NULL)';
            unset($params['rid']);
        }
        return [$sql, $params];
    }

    private const READ_EXPR = '(n.read_at IS NOT NULL OR nr.notification_id IS NOT NULL)';

    public static function unreadCount(): int
    {
        [$where, $params] = self::visibility();
        return (int) Database::instance()->fetchColumn(
            "SELECT COUNT(*) FROM notifications n
               LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = :reader
              WHERE {$where} AND NOT " . self::READ_EXPR,
            $params + ['reader' => Auth::id()]
        );
    }

    public static function latest(int $limit = 8): array
    {
        return self::list(false, $limit, 0);
    }

    public static function count(bool $unreadOnly): int
    {
        [$where, $params] = self::visibility();
        return (int) Database::instance()->fetchColumn(
            "SELECT COUNT(*) FROM notifications n
               LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = :reader
              WHERE {$where}" . ($unreadOnly ? ' AND NOT ' . self::READ_EXPR : ''),
            $params + ['reader' => Auth::id()]
        );
    }

    public static function list(bool $unreadOnly, int $limit, int $offset): array
    {
        [$where, $params] = self::visibility();
        return Database::instance()->fetchAll(
            "SELECT n.*, " . self::READ_EXPR . " AS is_read
               FROM notifications n
               LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = :reader
              WHERE {$where}" . ($unreadOnly ? ' AND NOT ' . self::READ_EXPR : '') . "
              ORDER BY n.id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params + ['reader' => Auth::id()]
        );
    }

    /** Marque comme lue une notification visible par l'utilisateur. */
    public static function markRead(int $id): bool
    {
        [$where, $params] = self::visibility();
        $n = Database::instance()->fetch("SELECT n.id, n.user_id FROM notifications n WHERE n.id = :id AND {$where}", $params + ['id' => $id]);
        if (!$n) {
            return false;
        }
        if ($n['user_id'] !== null) {
            Database::instance()->query('UPDATE notifications SET read_at = NOW() WHERE id = ? AND read_at IS NULL', [$id]);
        } else {
            Database::instance()->query('INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)', [$id, Auth::id()]);
        }
        return true;
    }

    public static function markAllRead(): int
    {
        $count = 0;
        foreach (self::list(true, 1000, 0) as $n) {
            $count += self::markRead((int) $n['id']) ? 1 : 0;
        }
        return $count;
    }
}
