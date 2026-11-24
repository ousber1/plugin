<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class RetailProduct extends Model
{
    use HasTranslations;

    protected $table = 'retail_products';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_ttc' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'cost_price' => 'decimal:4',
            'stock_qty' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(RetailCategory::class, 'retail_category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements()
    {
        return $this->hasMany(RetailStockMovement::class);
    }


    public function priceHt(): string
    {
        return \App\Support\Money::str(\App\Support\Money::of($this->price_ttc)->multipliedBy(100)->dividedBy(\App\Support\Money::of($this->tax_rate)->plus(100), 2, \Brick\Math\RoundingMode::HalfUp));
    }

    public function isLow(): bool
    {
        return $this->track_stock && (float) $this->stock_qty <= (float) $this->low_stock_threshold;
    }

    /** Gross margin rate on the HT price, null when no cost is known. */
    public function marginPct(): ?float
    {
        $ht = (float) $this->priceHt();

        return $ht > 0 && (float) $this->cost_price > 0 ? round(($ht - (float) $this->cost_price) / $ht * 100, 1) : null;
    }
}
