<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasTranslations;

    protected $table = 'purchase_order_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function job()
    {
        return $this->belongsTo(ProductionJob::class, 'production_job_id');
    }
}
