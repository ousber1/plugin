<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    use HasTranslations;

    protected $table = 'payment_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
