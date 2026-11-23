<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class QuoteRevision extends Model
{
    use HasTranslations;

    protected $table = 'quote_revisions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function shippingZone()
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
