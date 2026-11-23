<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasTranslations;

    protected $table = 'pages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'meta_description' => 'array',
            'is_published' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }
}
