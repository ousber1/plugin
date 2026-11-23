<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

trait Auditable
{
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
