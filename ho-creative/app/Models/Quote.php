<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasTranslations;

    protected $table = 'quotes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'responded_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisions()
    {
        return $this->hasMany(QuoteRevision::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function quoteRequest()
    {
        return $this->belongsTo(QuoteRequest::class);
    }


    use Concerns\Auditable;

    public function currentRevision()
    {
        return $this->hasOne(QuoteRevision::class)->ofMany(['revision' => 'max']);
    }

    public function revisionNo(int $n): ?QuoteRevision
    {
        return $this->revisions()->where('revision', $n)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->endOfDay()->isPast();
    }

    public function canRespond(): bool
    {
        return $this->status === 'sent' && ! $this->isExpired();
    }
}
