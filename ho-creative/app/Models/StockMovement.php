<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasTranslations;

    protected $table = 'stock_movements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function job()
    {
        return $this->belongsTo(ProductionJob::class, 'production_job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
