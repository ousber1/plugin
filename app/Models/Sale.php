<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'user_id',
        'channel',
        'source',
        'status',
        'subtotal',
        'discount',
        'tax',
        'total',
        'payment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Sale $sale) {
            if (empty($sale->invoice_number)) {
                $sale->invoice_number = static::generateInvoiceNumber();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd') . '-';
        $lastSale = static::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastSale) {
            $lastNumber = (int) substr($lastSale->invoice_number, strlen($prefix));
            return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix . '0001';
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total');
        $this->total = $this->subtotal - $this->discount + $this->tax;
        $this->save();
    }

    public function isPaid(): bool
    {
        return $this->getTotalPaid() >= $this->total;
    }

    public function getTotalPaid(): float
    {
        return (float) $this->payments->sum('amount');
    }
}
