<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class RetailSalePayment extends Model
{
    use HasTranslations;

    protected $table = 'retail_sale_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
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
