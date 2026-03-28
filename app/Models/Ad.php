<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_set_id',
        'name',
        'external_id',
        'headline',
        'body',
        'cta',
        'image_url',
        'status',
    ];

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(AdMetric::class);
    }

    public function analysis(): HasOne
    {
        return $this->hasOne(AdAnalysis::class);
    }

    public function getLatestMetrics()
    {
        return $this->metrics()->latest('date')->first();
    }

    public function getTotalMetrics(): array
    {
        $metrics = $this->metrics();

        return [
            'impressions' => $metrics->sum('impressions'),
            'clicks' => $metrics->sum('clicks'),
            'cost' => $metrics->sum('cost'),
            'conversions' => $metrics->sum('conversions'),
            'revenue' => $metrics->sum('revenue'),
        ];
    }
}
