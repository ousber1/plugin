<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Shop;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'subtitle', 'description', 'features', 'requirements', 'seo_title', 'seo_description'];

    protected $fillable = [
        'slug', 'category_id', 'title', 'subtitle', 'description', 'features', 'requirements', 'changelog', 'gallery',
        'price_cents', 'compare_price_cents', 'price_usd_cents', 'compare_price_usd_cents', 'billing', 'license_sites',
        'version', 'requires', 'tested_up_to', 'file_format', 'files_count', 'demo_url', 'updated_on', 'is_featured',
        'sort_order', 'status', 'seo_title', 'seo_description',
    ];

    protected $casts = [
        'changelog' => \App\Casts\Json::class, 'gallery' => \App\Casts\Json::class,
        'updated_on' => 'date', 'is_featured' => 'boolean',
    ];

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

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(ProductReview::class)->where('is_approved', true);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Price in the given currency (cents). USD falls back to the configured exchange rate. */
    public function price(?string $currency = null): int
    {
        $currency ??= Shop::currency();
        if ($currency === 'USD') {
            return $this->price_usd_cents ?: Shop::toUsd($this->price_cents);
        }

        return (int) $this->price_cents;
    }

    public function comparePrice(?string $currency = null): ?int
    {
        $currency ??= Shop::currency();
        $value = $currency === 'USD'
            ? ($this->compare_price_usd_cents ?: ($this->compare_price_cents ? Shop::toUsd($this->compare_price_cents) : null))
            : $this->compare_price_cents;

        return $value && $value > $this->price($currency) ? (int) $value : null;
    }

    public function discountPercent(): ?int
    {
        $c = $this->comparePrice('MAD');

        return $c ? (int) round(($c - $this->price_cents) * 100 / $c) : null;
    }

    public function priceLabel(?string $currency = null): string
    {
        return Shop::money($this->price($currency), $currency);
    }

    public function billingLabel(): ?string
    {
        return ['year' => __('سنة'), 'month' => __('شهر')][$this->billing] ?? null;
    }

    public function isNew(): bool
    {
        return $this->created_at && $this->created_at->gt(now()->subDays(30));
    }

    public function isRecentlyUpdated(): bool
    {
        return $this->updated_on && $this->updated_on->gt(now()->subDays(60));
    }

    public function salesCount(): int
    {
        return $this->sales_count ?? $this->orderItems()->whereHas('order', fn ($q) => $q->where('status', 'paid'))->count();
    }

    public function averageRating(): ?float
    {
        $avg = $this->approved_reviews_avg_rating ?? $this->approvedReviews()->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    public function galleryUrls(): array
    {
        return collect($this->gallery ?: [])->filter(fn ($p) => is_string($p) && $p !== '')->map(fn ($p) => \App\Support\Media::url($p))->values()->all();
    }

    public function changelogEntries(): array
    {
        return collect($this->changelog ?: [])->filter(fn ($e) => is_array($e) && ! empty($e['version']))->values()->all();
    }

    public function cartKey(): string
    {
        return 'p:'.$this->id;
    }

    public function itemUrl(): string
    {
        return route('store.show', $this);
    }

    public function coverUrl(): string
    {
        return $this->cover_path
            ? \App\Support\Media::url($this->cover_path)
            : route('covers.product', ['product' => $this->id, 'v' => \App\Support\CoverArt::version()]);
    }
}
