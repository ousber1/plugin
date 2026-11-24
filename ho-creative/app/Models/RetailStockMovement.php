<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class RetailStockMovement extends Model
{
    use HasTranslations;

    protected $table = 'retail_stock_movements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function product()
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }

    public function sale()
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
