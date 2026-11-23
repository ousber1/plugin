<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasTranslations;

    protected $table = 'addresses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }


    public function toSnapshot(): array
    {
        return $this->only(['name', 'company', 'line1', 'line2', 'city', 'postal_code', 'phone']);
    }
}
