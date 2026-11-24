<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class RetailSale extends Model
{
    use HasTranslations;

    protected $table = 'retail_sales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal_ht' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_given' => 'decimal:2',
            'refunded_total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'is_demo' => 'boolean',
        ];
    }

    public function items()
    {
        return $this->hasMany(RetailSaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(RetailSalePayment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function session()
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }


    use Concerns\Auditable;
}
