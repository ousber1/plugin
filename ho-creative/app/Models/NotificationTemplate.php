<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasTranslations;

    protected $table = 'notification_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subject' => 'array',
            'body' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
