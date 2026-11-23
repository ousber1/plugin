<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class ProductionJob extends Model
{
    use HasTranslations;

    protected $table = 'production_jobs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'due_at' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function item()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function events()
    {
        return $this->hasMany(ProductionJobEvent::class);
    }

    public function costs()
    {
        return $this->hasMany(JobCost::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }


    use Concerns\Auditable;

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->lt(today()) && ! in_array($this->stage, ['ready_for_pickup', 'shipped', 'delivered', 'cancelled']);
    }
}
