<?php

namespace App\Models;

use App\Enums\Level;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'subtitle', 'description', 'outcomes', 'prerequisites', 'audience', 'seo_title', 'seo_description'];

    protected $fillable = [
        'category_id', 'instructor_id', 'slug', 'title', 'subtitle', 'description', 'outcomes', 'prerequisites',
        'audience', 'level', 'language', 'thumbnail_path', 'is_featured', 'featured_order', 'requires_quiz_pass',
        'seo_title', 'seo_description',
    ];

    protected $casts = [
        'level' => Level::class,
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'requires_quiz_pass' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->lte(now());
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('sort_order')->orderBy('id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function publishedLessons()
    {
        return $this->hasMany(Lesson::class)->where('status', 'published');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    public function thumbnailUrl(): string
    {
        return $this->thumbnail_path
            ? Storage::disk('public')->url($this->thumbnail_path)
            : route('covers.course', ['course' => $this->id, 'v' => substr(md5($this->tr('title')), 0, 6)]);
    }

    /** Total duration of published video lessons, in seconds. */
    public function durationSeconds(): int
    {
        return (int) ($this->published_lessons_sum_duration_seconds ?? $this->publishedLessons()->sum('duration_seconds'));
    }

    public function lessonCount(): int
    {
        return (int) ($this->published_lessons_count ?? $this->publishedLessons()->count());
    }

    public function averageRating(): ?float
    {
        $avg = $this->approved_reviews_avg_rating ?? $this->approvedReviews()->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    /** Eager-load the aggregates used by course cards. */
    public function scopeWithCardData(Builder $q): Builder
    {
        return $q->with('category')
            ->withCount(['publishedLessons', 'approvedReviews'])
            ->withSum('publishedLessons', 'duration_seconds')
            ->withAvg('approvedReviews', 'rating');
    }
}
