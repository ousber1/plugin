<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class JobCost extends Model
{
    use HasTranslations;

    protected $table = 'job_costs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total' => 'decimal:2',
            'is_estimate' => 'boolean',
        ];
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
