<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'notes',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'total_purchases' => 'decimal:2',
            'total_spent' => 'decimal:2',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function whatsappContact(): HasOne
    {
        return $this->hasOne(WhatsappContact::class);
    }

    public function getFullPurchaseHistory()
    {
        return $this->sales()->with(['items.product', 'payments'])->orderBy('created_at', 'desc')->get();
    }
}
