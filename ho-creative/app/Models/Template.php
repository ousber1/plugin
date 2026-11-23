<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasTranslations;

    protected $table = 'templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'bleed_mm' => 'decimal:2',
            'safe_mm' => 'decimal:2',
            'name' => 'array',
            'design' => 'array',
            'is_published' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
