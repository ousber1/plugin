<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'platform',
        'external_id',
        'status',
        'budget',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    public function ads(): HasManyThrough
    {
        return $this->hasManyThrough(Ad::class, AdSet::class);
    }

}
