<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class PricingRuleSet extends Model
{
    use HasTranslations;

    protected $table = 'pricing_rule_sets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
