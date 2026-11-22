<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'description'];

    protected $fillable = ['category_id', 'course_id', 'slug', 'title', 'description', 'access', 'status'];

    protected $casts = ['is_demo' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function isRestricted(): bool
    {
        return $this->access === 'enrolled';
    }

    public function extension(): string
    {
        return strtoupper(pathinfo((string) $this->original_name, PATHINFO_EXTENSION) ?: 'FILE');
    }
}
