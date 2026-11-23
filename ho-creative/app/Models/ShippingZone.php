<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasTranslations;

    protected $table = 'shipping_zones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'free_over' => 'decimal:2',
            'name' => 'array',
            'cities' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }


    public function matchesCity(?string $city): bool
    {
        $city = mb_strtolower(trim((string) $city));
        foreach ((array) $this->cities as $c) {
            if ($city !== '' && mb_strtolower(trim($c)) === $city) {
                return true;
            }
        }

        return false;
    }

    public static function forCity(?string $city): ?self
    {
        $zones = static::query()->where('is_active', true)->orderBy('sort')->get();

        return $zones->first(fn ($z) => $z->matchesCity($city)) ?? $zones->firstWhere('is_default', true);
    }
}
