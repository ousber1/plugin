<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasTranslations;

    protected $table = 'suppliers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
        ];
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function retailProducts()
    {
        return $this->hasMany(RetailProduct::class);
    }

    /** Amount owed = value of goods received on non-cancelled purchase orders − payments made. */
    public function balance(): array
    {
        $received = \App\Support\Money::sum($this->purchaseOrders()->where('status', '!=', 'cancelled')->with('items')->get()->map(fn ($po) => $po->receivedValue()));
        $paid = \App\Support\Money::sum($this->payments()->pluck('amount'));

        return ['received' => \App\Support\Money::str($received), 'paid' => \App\Support\Money::str($paid), 'due' => \App\Support\Money::str($received->minus($paid))];
    }
}
