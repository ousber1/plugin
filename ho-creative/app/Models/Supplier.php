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
}
