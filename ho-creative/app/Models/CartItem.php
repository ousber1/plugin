<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasTranslations;

    protected $table = 'cart_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'selection' => 'array',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function artworkFiles()
    {
        return $this->hasMany(ArtworkFile::class);
    }
}
