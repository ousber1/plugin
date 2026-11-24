<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class RetailSaleItem extends Model
{
    use HasTranslations;

    protected $table = 'retail_sale_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_ttc' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'total_ht' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'returned_qty' => 'decimal:3',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function product()
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }
}
