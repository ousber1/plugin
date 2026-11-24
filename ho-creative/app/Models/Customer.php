<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasTranslations;

    protected $table = 'customers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'is_demo' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function customerNotes()
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }


    public function displayName(): string
    {
        return $this->type === 'company' && $this->company_name ? $this->company_name.' ('.$this->name.')' : $this->name;
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Order::class);
    }

    public function retailSales()
    {
        return $this->hasMany(RetailSale::class);
    }
}
