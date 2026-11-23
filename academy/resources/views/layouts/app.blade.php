@php
    use App\Support\Settings;
    use App\Support\Locales;
    $locale = app()->getLocale();
    $features = Settings::get('features');
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle ? ($pageTitle.(Settings::get('seo_title_suffix') && ! str_contains($pageTitle, $siteName) ? ' | '.$siteName : '')) : $siteName.' — '.Settings::tr('tagline');
    $description = trim($__env->yieldContent('description')) ?: Settings::tr('seo_description');
    $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
    $ogImage = trim($__env->yieldContent('og_image')) ?: (Settings::get('og_image_path') ? \App\Support\Media::url(Settings::get('og_image_path')) : asset('images/og-default.png'));
    $favicon = Settings::get('favicon_path');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 300) }}">
    <link rel="canonical" href="{{ $canonical }}">
    @hasSection('noindex')<meta name="robots" content="noindex, nofollow">@endif
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $pageTitle ?: $siteName }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 300) }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ ['ar' => 'ar_MA', 'fr' => 'fr_MA', 'en' => 'en_US'][$locale] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (Settings::get('google_verification'))<meta name="google-site-verification" content="{{ Settings::get('google_verification') }}">@endif
    @if (Settings::get('bing_verification'))<meta name="msvalidate.01" content="{{ Settings::get('bing_verification') }}">@endif
    <link rel="icon" href="{{ $favicon ? \App\Support\Media::url($favicon) : asset('favicon.svg') }}">
    <link rel="preload" href="{{ asset('fonts/tajawal-arabic-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @include('partials.theme')
    @stack('head')
    @if (Settings::get('org_schema') && request()->routeIs('home'))
        @include('partials.org-schema')
    @endif
    {!! Settings::get('head_code') !!}
</head>
<body class="{{ \App\Support\Themes::bodyClasses() }} @yield('body_class')">
<a class="skip-link" href="#main">{{ __('انتقل إلى المحتوى') }}</a>
<div id="live-region" class="sr-only" aria-live="polite"></div>

@include('partials.header')

<main id="main" tabindex="-1">
    @if (session('status') || session('success') || session('error') || session('info'))
        <div class="flash-wrap" role="status">
            @if (session('success') || session('status'))<div class="alert alert--success" data-flash><x-icon name="check-circle"/> <span>{{ session('success') ?? session('status') }}</span></div>@endif
            @if (session('error'))<div class="alert alert--error" data-flash><x-icon name="alert"/> <span>{{ session('error') }}</span></div>@endif
            @if (session('info'))<div class="alert alert--info" data-flash><x-icon name="info"/> <span>{{ session('info') }}</span></div>@endif
        </div>
    @endif
    @yield('content')
</main>

@unless (View::hasSection('hide_footer'))
@include('partials.footer')
@endunless

@include('partials.whatsapp-float')
@include('partials.cookie-bar')
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
{!! Settings::get('body_code') !!}
</body>
</html>
