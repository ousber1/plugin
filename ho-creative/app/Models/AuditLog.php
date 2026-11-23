<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasTranslations;

    protected $table = 'audit_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public $timestamps = false;
}
