<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasTranslations;

    protected $table = 'coupons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'product_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }


    public function isUsable(): bool
    {
        $now = now();

        return $this->is_active
            && (! $this->starts_at || $this->starts_at <= $now)
            && (! $this->ends_at || $this->ends_at >= $now)
            && (! $this->max_uses || $this->used_count < $this->max_uses);
    }
}
