<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'description'];

    protected $fillable = ['slug', 'title', 'description', 'price_cents', 'status'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function priceLabel(): string
    {
        return number_format($this->price_cents / 100, 2, '.', ' ').' MAD';
    }

    public function coverUrl(): string
    {
        return $this->cover_path
            ? \App\Support\Media::url($this->cover_path)
            : route('covers.product', ['product' => $this->id, 'v' => \App\Support\CoverArt::version()]);
    }
}
