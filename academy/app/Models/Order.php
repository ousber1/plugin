<?php

namespace App\Models;

use App\Support\Shop;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUSES = ['pending', 'review', 'paid', 'cancelled'];

    protected $fillable = ['reference', 'user_id', 'product_id', 'name', 'phone', 'email', 'amount_cents', 'subtotal_cents', 'discount_cents', 'coupon_code', 'currency', 'payment_method', 'status', 'transfer_reference', 'customer_note'];

    protected $casts = ['paid_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Legacy single-product link (orders created before the cart). */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLabel(): string
    {
        return [
            'pending' => __('بانتظار الدفع'), 'review' => __('قيد مراجعة الدفع'),
            'paid' => __('مدفوع'), 'cancelled' => __('ملغى'),
        ][$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['pending' => 'amber', 'review' => 'blue', 'paid' => 'green', 'cancelled' => 'gray'][$this->status] ?? 'gray';
    }

    public function totalLabel(): string
    {
        return Shop::money($this->amount_cents, $this->currency);
    }

    public function methodLabel(): string
    {
        return Shop::methods()[$this->payment_method]['label'] ?? (string) $this->payment_method;
    }
}
