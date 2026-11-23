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
        'seo_title', 'seo_description', 'price_cents', 'compare_price_cents', 'price_usd_cents',
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

    public function centers()
    {
        return $this->belongsToMany(Center::class);
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

    // ---- Pricing (free when price_cents = 0) ----

    public function isFree(): bool
    {
        return (int) $this->price_cents <= 0;
    }

    public function isPaid(): bool
    {
        return ! $this->isFree();
    }

    /** Whether any published course is paid (adapts "all courses are free" wording). */
    public static function anyPaid(): bool
    {
        try {
            return once(fn () => self::published()->where('price_cents', '>', 0)->exists());
        } catch (\Throwable) {
            return false;
        }
    }

    public function price(?string $currency = null): int
    {
        $currency ??= \App\Support\Shop::currency();
        if ($this->isFree()) {
            return 0;
        }

        return $currency === 'USD' ? ($this->price_usd_cents ?: \App\Support\Shop::toUsd($this->price_cents)) : (int) $this->price_cents;
    }

    public function comparePrice(?string $currency = null): ?int
    {
        if (! $this->compare_price_cents || $this->isFree()) {
            return null;
        }
        $currency ??= \App\Support\Shop::currency();
        $v = $currency === 'USD' ? \App\Support\Shop::toUsd($this->compare_price_cents) : (int) $this->compare_price_cents;

        return $v > $this->price($currency) ? $v : null;
    }

    public function priceLabel(?string $currency = null): string
    {
        return $this->isFree() ? __('مجانية') : \App\Support\Shop::money($this->price($currency), $currency);
    }

    public function billingLabel(): ?string
    {
        return null;
    }

    public function coverUrl(): string
    {
        return $this->thumbnailUrl();
    }

    public function cartKey(): string
    {
        return 'c:'.$this->id;
    }

    public function itemUrl(): string
    {
        return route('courses.show', $this);
    }

    public function thumbnailUrl(): string
    {
        return $this->thumbnail_path
            ? \App\Support\Media::url($this->thumbnail_path)
            : route('covers.course', ['course' => $this->id, 'v' => substr(md5($this->tr('title')), 0, 6).\App\Support\CoverArt::version()]);
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
