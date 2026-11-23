<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    use HasTranslations;

    protected $table = 'designs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'bleed_mm' => 'decimal:2',
            'safe_mm' => 'decimal:2',
            'data' => 'array',
            'exports' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
