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
}
