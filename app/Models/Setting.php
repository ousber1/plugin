<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value, ?string $group = null): static
    {
        $existing = static::where('key', $key)->first();

        if ($existing) {
            $existing->update([
                'value' => $value,
                'group' => $group ?? $existing->group,
            ]);
            return $existing;
        }

        return static::create([
            'key' => $key,
            'value' => $value,
            'group' => $group ?? 'general',
        ]);
    }
}
