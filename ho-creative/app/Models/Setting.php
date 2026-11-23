<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasTranslations;

    protected $table = 'settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
