<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasTranslations;

    protected $table = 'carts';

    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
