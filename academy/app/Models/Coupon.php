<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['code', 'type', 'value', 'max_uses', 'used', 'expires_at', 'is_active'];

    protected $casts = ['expires_at' => 'datetime', 'is_active' => 'boolean'];

    public static function findValid(?string $code): ?self
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $c = self::where('code', $code)->where('is_active', true)->first();

        return $c && $c->usable() ? $c : null;
    }

    public function usable(): bool
    {
        return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture()) && (! $this->max_uses || $this->used < $this->max_uses);
    }

    /** Discount (cents) on a subtotal in the given currency. Fixed values are in MAD. */
    public function discountFor(int $subtotal, string $currency): int
    {
        $d = $this->type === 'percent'
            ? (int) round($subtotal * min(100, $this->value) / 100)
            : ($currency === 'USD' ? \App\Support\Shop::toUsd($this->value * 100) : $this->value * 100);

        return max(0, min($subtotal, $d));
    }

    public function label(): string
    {
        return $this->type === 'percent' ? $this->value.'%' : $this->value.' MAD';
    }
}
