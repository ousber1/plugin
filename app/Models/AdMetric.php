<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_id',
        'date',
        'impressions',
        'clicks',
        'cost',
        'conversions',
        'revenue',
        'cpc',
        'cpa',
        'ctr',
        'roi',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cost' => 'decimal:2',
            'revenue' => 'decimal:2',
            'cpc' => 'decimal:2',
            'cpa' => 'decimal:2',
            'ctr' => 'decimal:2',
            'roi' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (AdMetric $metric) {
            $metric->cpc = $metric->clicks > 0
                ? $metric->cost / $metric->clicks
                : 0;

            $metric->cpa = $metric->conversions > 0
                ? $metric->cost / $metric->conversions
                : 0;

            $metric->ctr = $metric->impressions > 0
                ? ($metric->clicks / $metric->impressions) * 100
                : 0;

            $metric->roi = $metric->cost > 0
                ? (($metric->revenue - $metric->cost) / $metric->cost) * 100
                : 0;
        });
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
