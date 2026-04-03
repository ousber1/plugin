<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'external_id',
        'status',
        'budget',
        'targeting',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'targeting' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
