<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class ProductionJobEvent extends Model
{
    use HasTranslations;

    protected $table = 'production_job_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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

    public $timestamps = false;
}
