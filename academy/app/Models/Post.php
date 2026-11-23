<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'excerpt', 'body', 'seo_title', 'seo_description'];

    protected $fillable = ['category_id', 'author_id', 'slug', 'title', 'excerpt', 'body', 'cover_path', 'status', 'published_at', 'seo_title', 'seo_description'];

    protected $casts = ['published_at' => 'datetime', 'is_demo' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function coverUrl(): string
    {
        return $this->cover_path
            ? \App\Support\Media::url($this->cover_path)
            : route('covers.post', ['post' => $this->id, 'v' => substr(md5($this->tr('title')), 0, 6)]);
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->tr('body'))) / 200) ?: (int) ceil(mb_strlen(strip_tags($this->tr('body'))) / 1100));
    }
}
