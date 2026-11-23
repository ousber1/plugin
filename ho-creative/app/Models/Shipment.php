<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasTranslations;

    protected $table = 'shipments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
