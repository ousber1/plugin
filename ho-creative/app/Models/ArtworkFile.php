<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class ArtworkFile extends Model
{
    use HasTranslations;

    protected $table = 'artwork_files';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
        ];
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
