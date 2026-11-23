<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'title', 'price_cents', 'billing', 'license_key', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isActive(): bool
    {
        return ! $this->expires_at || $this->expires_at->isFuture();
    }
}
