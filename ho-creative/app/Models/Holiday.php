<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasTranslations;

    protected $table = 'holidays';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
