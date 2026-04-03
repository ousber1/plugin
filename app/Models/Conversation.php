<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp_contact_id',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function whatsappContact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getLatestMessage()
    {
        return $this->messages()->latest()->first();
    }
}
