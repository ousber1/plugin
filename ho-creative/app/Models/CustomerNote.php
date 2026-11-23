<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class CustomerNote extends Model
{
    use HasTranslations;

    protected $table = 'customer_notes';

    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
