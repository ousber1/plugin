<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** A physical training centre (Casablanca, Rabat, …). */
class Center extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'city', 'address', 'description', 'hours'];

    protected $fillable = ['name', 'slug', 'city', 'address', 'description', 'hours', 'phone', 'whatsapp', 'email', 'map_url', 'latitude', 'longitude', 'image_path', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];

    private static ?bool $hasPublic = null;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function students()
    {
        return $this->hasMany(User::class);
    }

    public function certificateRequests()
    {
        return $this->hasMany(CertificateRequest::class);
    }

    /** Whether any active centre exists (cached per request; safe before migrating). */
    public static function hasPublic(): bool
    {
        if (self::$hasPublic === null) {
            try {
                self::$hasPublic = Schema::hasTable('centers') && self::where('is_active', true)->exists();
            } catch (\Throwable) {
                self::$hasPublic = false;
            }
        }

        return self::$hasPublic;
    }

    public static function flushCache(): void
    {
        self::$hasPublic = null;
    }

    public function imageUrl(): ?string
    {
        return \App\Support\Media::url($this->image_path);
    }

    /** Google Maps search link (or the custom link the admin pasted). */
    public function mapLink(): string
    {
        if ($this->map_url) {
            return $this->map_url;
        }
        $q = $this->latitude && $this->longitude ? $this->latitude.','.$this->longitude : trim($this->tr('address').' '.$this->tr('city'));

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($q);
    }

    /** Embeddable map (no API key needed). */
    public function mapEmbed(): string
    {
        $q = $this->latitude && $this->longitude ? $this->latitude.','.$this->longitude : trim($this->tr('address', 'fr').' '.$this->tr('city', 'fr'));

        return 'https://maps.google.com/maps?q='.rawurlencode($q).'&z=15&output=embed';
    }

    public function whatsappLink(string $text): ?string
    {
        $n = preg_replace('/\D+/', '', (string) $this->whatsapp);

        return $n ? 'https://wa.me/'.$n.'?text='.rawurlencode($text) : \App\Support\Settings::whatsappLink($text);
    }
}
