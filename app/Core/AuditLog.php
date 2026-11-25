<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Journal d'audit : toute action sensible est tracée (qui, quoi, quand, avant/après).
 * Ne lève jamais d'exception : un échec de journalisation ne doit pas bloquer l'activité.
 */
final class AuditLog
{
    private const SENSITIVE_KEYS = ['password', 'password_hash', 'pass', 'token', 'token_hash', 'smtp_password', '_csrf'];

    public static function log(
        string $action,
        string $module,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        try {
            Database::instance()->insert('activity_logs', [
                'user_id'     => $userId ?? Auth::id(),
                'action'      => mb_substr($action, 0, 50),
                'module'      => mb_substr($module, 0, 50),
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'description' => $description !== null ? mb_substr($description, 0, 500) : null,
                'old_values'  => $oldValues !== null ? json_encode(self::scrub($oldValues), JSON_UNESCAPED_UNICODE) : null,
                'new_values'  => $newValues !== null ? json_encode(self::scrub($newValues), JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => Request::ip(),
                'user_agent'  => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Échec du journal d\'audit : ' . $e->getMessage());
        }
    }

    /** Retire les champs sensibles avant enregistrement. */
    private static function scrub(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $values[$key] = '***';
            } elseif (is_array($value)) {
                $values[$key] = self::scrub($value);
            }
        }
        return $values;
    }

    /** Ne garde que les champs modifiés entre deux états. */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before) || (string) $before[$key] !== (string) $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }
        return [$old, $new];
    }
}
