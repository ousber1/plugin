<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'body', 'seo_title', 'seo_description'];

    protected $fillable = ['slug', 'title', 'body', 'status', 'show_in_footer', 'seo_title', 'seo_description'];

    protected $casts = ['show_in_footer' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
