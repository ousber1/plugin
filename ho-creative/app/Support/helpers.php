<?php

use App\Support\Money;
use App\Support\Settings;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}
if (! function_exists('setting_t')) {
    function setting_t(string $key): string
    {
        return app(Settings::class)->t($key);
    }
}
if (! function_exists('money')) {
    function money(mixed $v): string
    {
        return Money::format($v);
    }
}
if (! function_exists('tr')) {
    /** Translate a {fr,ar} array or return string as-is. */
    function tr(mixed $v): string
    {
        if (is_array($v)) {
            $l = app()->getLocale();

            return (string) (($v[$l] ?? '') !== '' ? $v[$l] : ($v['fr'] ?? ''));
        }

        return (string) $v;
    }
}
if (! function_exists('d')) {
    /** Business date formatting (Africa/Casablanca). */
    function d($date, bool $time = false): string
    {
        if (! $date) {
            return '—';
        }
        $c = \Illuminate\Support\Carbon::parse($date)->timezone(config('app.timezone'))->locale(app()->getLocale());

        return $time ? $c->isoFormat('D MMM YYYY HH:mm') : $c->isoFormat('D MMM YYYY');
    }
}
if (! function_exists('is_installed')) {
    function is_installed(): bool
    {
        return file_exists(storage_path('app/installed.lock'));
    }
}
