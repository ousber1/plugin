<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasTranslations;

    protected $table = 'purchase_orders';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'expected_at' => 'date',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** Value of goods actually received (HT). */
    public function receivedValue(): string
    {
        return \App\Support\Money::str(\App\Support\Money::sum($this->items->map(fn ($i) => \App\Support\Money::of($i->quantity_received)->multipliedBy($i->unit_cost))));
    }
}
