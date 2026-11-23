<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value business settings with config defaults (config/hocreative.php).
 */
class Settings
{
    protected ?array $loaded = null;

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }
        $defaults = config('hocreative.defaults', []);
        try {
            $stored = Cache::remember('hoc.settings', 3600, fn () => Schema::hasTable('settings')
                ? Setting::query()->pluck('value', 'key')->all() : []);
        } catch (\Throwable) {
            $stored = [];
        }

        return $this->loaded = array_merge($defaults, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** Translated setting ({fr,ar} arrays) */
    public function t(string $key, ?string $locale = null): string
    {
        $v = $this->get($key);
        if (is_array($v)) {
            $locale ??= app()->getLocale();

            return (string) ($v[$locale] ?? $v['fr'] ?? '');
        }

        return (string) $v;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key), FILTER_VALIDATE_BOOL);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget('hoc.settings');
        $this->loaded = null;
    }
}
