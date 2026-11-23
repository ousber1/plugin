<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Proof extends Model
{
    use HasTranslations;

    protected $table = 'proofs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
