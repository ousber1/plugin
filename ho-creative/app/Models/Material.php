<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasTranslations;

    protected $table = 'materials';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'decimal:3',
            'reserved_qty' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }


    public function available(): string
    {
        return \App\Support\Money::str(\App\Support\Money::of($this->stock_qty)->minus($this->reserved_qty), 3);
    }

    public function isLow(): bool
    {
        return \App\Support\Money::of($this->available())->isLessThanOrEqualTo($this->low_stock_threshold);
    }
}
