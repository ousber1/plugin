<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Translator;
use App\Helpers\Money;
use App\Services\Auth;
use App\Services\SettingsService;

/*
 * Global helper functions used by controllers and views.
 */

if (!function_exists('e')) {
    /** Escape for HTML output (XSS protection). */
    function e(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value) && !method_exists($value, '__toString')) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

function app(): App
{
    return App::instance();
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function base_path(string $path = ''): string
{
    return App::instance()->basePath($path);
}

function storage_path(string $path = ''): string
{
    return App::instance()->storagePath($path);
}

function public_path(string $path = ''): string
{
    return App::instance()->publicPath($path);
}

/** URL relative to the site root (handles installation in a sub-folder). */
function url(string $path = '/', array $query = []): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $request = Request::current();
    $base = $request?->basePath() ?? '';
    $url = $base . '/' . ltrim($path, '/');
    if ($query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
    return $url === '' ? '/' : $url;
}

/** Absolute URL (scheme + host), for e-mails, canonical tags, sitemaps. */
function absolute_url(string $path = '/'): string
{
    $configured = rtrim((string) Config::get('app.url', ''), '/');
    $request = Request::current();
    if ($configured === '' && $request !== null) {
        $configured = $request->rootUrl();
        return $configured . '/' . ltrim($path, '/');
    }
    return $configured . '/' . ltrim($path, '/');
}

/** @param array<string,string|int> $params */
function route(string $name, array $params = []): string
{
    return url(App::instance()->router()->url($name, $params));
}

function asset(string $path): string
{
    $file = App::instance()->publicPath('assets/' . ltrim($path, '/'));
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Public upload URL (product images, logos...). Never used for private customer photos. */
function upload_url(?string $path): string
{
    if ($path === null || $path === '') {
        return '';
    }
    return url('uploads/' . ltrim($path, '/'));
}

/** @param array<string,string|int|float> $replace */
function __(string $key, array $replace = []): string
{
    return Translator::get($key, $replace);
}

function locale(): string
{
    return Translator::locale();
}

function is_rtl(): bool
{
    return Translator::isRtl();
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

/** Previously submitted value after a validation error. */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('old', []);
    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

function error(string $key): ?string
{
    $errors = Session::getFlash('errors', []);
    return is_array($errors) ? ($errors[$key] ?? null) : null;
}

/** @return array<string,string> */
function errors(): array
{
    $errors = Session::getFlash('errors', []);
    return is_array($errors) ? $errors : [];
}

/** Bootstrap "is-invalid" class helper. */
function invalid(string $key): string
{
    return error($key) !== null ? ' is-invalid' : '';
}

function setting(string $key, mixed $default = null): mixed
{
    if (!App::hasInstance() || !App::instance()->isInstalled()) {
        return $default;
    }
    try {
        return SettingsService::get($key, $default);
    } catch (\Throwable) {
        return $default;
    }
}

function default_locale(): string
{
    $l = (string) setting('default_language', Config::get('app.locale', 'fr'));
    return in_array($l, Translator::SUPPORTED, true) ? $l : 'fr';
}

/** Format an amount (string/int cents not accepted here: decimal string) as "1 250,00 DH". */
function money(string|int|float|null $amount, bool $withSymbol = true): string
{
    return Money::format($amount, $withSymbol);
}

function auth_user(): ?array
{
    return Auth::user();
}

function auth_customer(): ?array
{
    return Auth::customer();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function admin_url(string $path = ''): string
{
    return url(trim((string) Config::get('app.admin_path', 'admin'), '/') . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Localized date output. */
function format_date(?string $date, bool $withTime = false): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

/** Active nav helper: returns "active" when current path starts with $prefix. */
function active(string $prefix, bool $exact = false): string
{
    $path = Request::current()?->path() ?? '/';
    $prefix = '/' . trim(parse_url(url($prefix), PHP_URL_PATH) ?: '/', '/');
    $base = Request::current()?->basePath() ?? '';
    if ($base !== '' && str_starts_with($prefix, $base)) {
        $prefix = '/' . trim(substr($prefix, strlen($base)), '/');
    }
    if ($exact) {
        return $path === $prefix ? 'active' : '';
    }
    return ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/')) ? 'active' : '';
}

function str_slug(string $text): string
{
    return \App\Helpers\Str::slug($text);
}

/** WhatsApp click-to-chat link (no API needed). */
function whatsapp_link(?string $number = null, string $message = ''): string
{
    $number = preg_replace('/\D+/', '', (string) ($number ?? setting('company_whatsapp', '')));
    if ($number === '') {
        return '';
    }
    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}
