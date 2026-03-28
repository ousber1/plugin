<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'phone',
        'name',
        'whatsapp_id',
        'is_subscribed',
    ];

    protected function casts(): array
    {
        return [
            'is_subscribed' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
