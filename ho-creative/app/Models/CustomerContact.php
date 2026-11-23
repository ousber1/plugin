<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class CustomerContact extends Model
{
    use HasTranslations;

    protected $table = 'customer_contacts';

    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
