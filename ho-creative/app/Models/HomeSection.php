<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    use HasTranslations;

    protected $table = 'home_sections';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'content' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
