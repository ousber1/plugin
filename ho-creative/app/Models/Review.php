<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasTranslations;

    protected $table = 'reviews';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
