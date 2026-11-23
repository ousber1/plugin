<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasTranslations;

    protected $table = 'orders';

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
            'amount_paid' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'deposit_required' => 'decimal:2',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'placed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_ready_at' => 'date',
            'estimated_delivery_at' => 'date',
            'is_demo' => 'boolean',
            'estimate_depends_on_proof' => 'boolean',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function jobs()
    {
        return $this->hasMany(ProductionJob::class);
    }

    public function shippingZone()
    {
        return $this->belongsTo(ShippingZone::class);
    }


    use Concerns\Auditable;

    public function balance(): string
    {
        return \App\Support\Money::str(\App\Support\Money::of($this->total)->minus($this->amount_paid)->plus($this->amount_refunded));
    }

    public function netPaid(): string
    {
        return \App\Support\Money::str(\App\Support\Money::of($this->amount_paid)->minus($this->amount_refunded));
    }

    /** Customer-facing simplified status. */
    public function publicStatus(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if (in_array($this->fulfillment_status, ['delivered', 'picked_up'])) {
            return 'delivered';
        }
        if ($this->fulfillment_status === 'shipped') {
            return 'shipped';
        }
        if ($this->fulfillment_status === 'ready') {
            return 'ready';
        }
        if ($this->items->contains(fn ($i) => $i->requires_proof && $i->proof_status !== 'approved' && $i->proof_status !== 'none')) {
            return 'awaiting_approval';
        }
        if ($this->items->contains(fn ($i) => $i->artwork_status === 'pending' && ! in_array($i->artwork_mode, ['design_service']))) {
            return 'awaiting_artwork';
        }

        return $this->status === 'pending' ? 'received' : 'in_production';
    }
}
