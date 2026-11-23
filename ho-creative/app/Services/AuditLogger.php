<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public static function log(string $action, ?Model $model = null, ?array $old = null, ?array $new = null, ?string $reason = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model ? $model->getMorphClass() : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason ? mb_substr($reason, 0, 500) : null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
