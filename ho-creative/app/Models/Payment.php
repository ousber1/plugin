<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasTranslations;

    protected $table = 'payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
