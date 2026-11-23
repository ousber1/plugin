<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    use HasTranslations;

    protected $table = 'machines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hourly_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
