<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['reference', 'user_id', 'product_id', 'amount_cents', 'currency', 'status', 'transfer_reference'];

    protected $casts = ['paid_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function statusLabel(): string
    {
        return ['pending' => __('بانتظار التحويل'), 'paid' => __('مدفوع'), 'cancelled' => __('ملغى')][$this->status] ?? $this->status;
    }
}
