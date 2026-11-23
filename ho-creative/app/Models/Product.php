<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasTranslations;

    protected $table = 'products';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_price' => 'decimal:2',
            'design_fee' => 'decimal:2',
            'name' => 'array',
            'summary' => 'array',
            'description' => 'array',
            'images' => 'array',
            'specs' => 'array',
            'finishes' => 'array',
            'faq' => 'array',
            'guides' => 'array',
            'artwork_modes' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'is_popular' => 'boolean',
            'is_demo' => 'boolean',
            'requires_proof' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function ruleSets()
    {
        return $this->hasMany(PricingRuleSet::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }


    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            $parts = [$p->sku];
            foreach (['name', 'summary'] as $f) {
                foreach ((array) $p->{$f} as $v) {
                    $parts[] = $v;
                }
            }
            $p->search_text = mb_strtolower(implode(' ', array_filter($parts)));
        });
    }

    public const MODES = ['instant', 'quote', 'unpublished'];

    public const ARTWORK_MODES = ['upload', 'later', 'template', 'editor', 'design_service'];

    public function publishedRuleSet()
    {
        return $this->hasOne(PricingRuleSet::class)->ofMany(['version' => 'max'], fn ($q) => $q->where('status', 'published'));
    }

    public function draftRuleSet()
    {
        return $this->hasOne(PricingRuleSet::class)->ofMany(['version' => 'max'], fn ($q) => $q->where('status', 'draft'));
    }

    public function approvedReviews()
    {
        return $this->reviews()->where('status', 'approved');
    }

    public function scopeVisible($q)
    {
        return $q->whereIn('sale_mode', ['instant', 'quote']);
    }

    public function isPurchasable(): bool
    {
        return $this->sale_mode === 'instant' && $this->publishedRuleSet !== null;
    }

    public function artworkModes(): array
    {
        return array_values(array_intersect(self::ARTWORK_MODES, $this->artwork_modes ?: ['upload', 'later']));
    }

    public function image(): string
    {
        return $this->images[0] ?? '/images/placeholder.svg';
    }

    /** Rating derived from approved reviews only. */
    public function ratingSummary(): array
    {
        $row = $this->approvedReviews()->selectRaw('COUNT(*) c, AVG(rating) a')->first();

        return ['count' => (int) $row->c, 'average' => $row->c ? round((float) $row->a, 1) : null];
    }
}
