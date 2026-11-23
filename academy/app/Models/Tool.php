<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    use HasTranslations;

    public const KNOWN = ['code-editor', 'cv-builder', 'invoice', 'certificate-designer', 'quiz-builder'];

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['key', 'name', 'description', 'badge', 'is_enabled', 'sort_order'];

    protected $casts = ['is_enabled' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
