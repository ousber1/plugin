<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasTranslations;

    protected $table = 'expenses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'is_demo' => 'boolean',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
