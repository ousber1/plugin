<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasTranslations;

    protected $table = 'quote_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'total' => 'decimal:2',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'selection' => 'array',
            'options_snapshot' => 'array',
            'breakdown' => 'array',
            'is_custom' => 'boolean',
        ];
    }

    public function revision()
    {
        return $this->belongsTo(QuoteRevision::class, 'quote_revision_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
