<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasTranslations;

    protected $table = 'menu_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'label' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
