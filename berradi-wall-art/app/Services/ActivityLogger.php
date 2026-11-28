<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;

/**
 * Audit trail written to `activity_logs`. Failures never break the request.
 *
 * @phpstan-type Meta array<string,mixed>
 */
final class ActivityLogger
{
    /** @param array<string,mixed> $meta */
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null, array $meta = []): void
    {
        try {
            $request = Request::current();
            $user = Auth::user();
            $customer = $user === null ? Auth::customer() : null;
            Database::instance()->insert('activity_logs', [
                'actor_type' => $user ? 'user' : ($customer ? 'customer' : 'system'),
                'actor_id' => $user['id'] ?? $customer['id'] ?? null,
                'action' => mb_substr($action, 0, 80),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description !== null ? mb_substr($description, 0, 500) : null,
                'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Activity log failed: ' . $e->getMessage());
        }
    }
}
