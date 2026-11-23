@php
    use App\Support\Settings;
    use App\Support\Locales;
    $locale = app()->getLocale();
    $features = Settings::get('features');
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle ? $pageTitle.' | '.$siteName : $siteName.' — '.Settings::tr('tagline');
    $description = trim($__env->yieldContent('description')) ?: Settings::tr('seo_description');
    $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
    $ogImage = trim($__env->yieldContent('og_image')) ?: asset('images/og-default.png');
    $favicon = Settings::get('favicon_path');
    $libraryCats = ($features['library'] ?? false) ? \App\Models\Category::ofType('resource')->where('is_active', true)->get() : collect();
    $extraLinks = collect(Settings::get('nav_extra_links') ?: [])->filter(fn ($l) => ! empty($l['url']));
    $whatsapp = Settings::whatsappLink(__('السلام عليكم، لدي سؤال حول الأكاديمية.'));
    $is = fn (...$patterns) => request()->routeIs(...$patterns) ? 'is-active' : '';
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
    <meta name="theme-color" content="#1e1550">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ $favicon ? \App\Support\Media::url($favicon) : asset('favicon.svg') }}">
    <link rel="preload" href="{{ asset('fonts/tajawal-arabic-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @include('partials.theme')
    @stack('head')
    {!! Settings::get('head_code') !!}
</head>
<body class="@yield('body_class')">
<a class="skip-link" href="#main">{{ __('انتقل إلى المحتوى') }}</a>
<div id="live-region" class="sr-only" aria-live="polite"></div>

@if (Settings::get('topbar_enabled'))
<div class="topbar">
    <div class="container">
        <p class="topbar__text">{{ Settings::tr('topbar_text') }}</p>
        <div class="row" style="gap:14px">
            @if (Settings::get('contact_phone'))
                <a href="tel:{{ preg_replace('/\s+/', '', Settings::get('contact_phone')) }}" class="row ltr" style="gap:4px"><x-icon name="phone" size="14"/> {{ Settings::get('contact_phone') }}</a>
            @endif
            <a href="{{ route('contact') }}" class="row" style="gap:4px"><x-icon name="mail" size="14"/> {{ __('تواصل معنا') }}</a>
        </div>
    </div>
</div>
@endif

<header class="site-header{{ Settings::get('sticky_header') ? '' : ' is-static' }}">
    <div class="container">
        <a href="{{ route('home') }}" aria-label="{{ $siteName }} — {{ __('الرئيسية') }}"><x-logo/></a>

        <nav class="nav" aria-label="{{ __('التنقل الرئيسي') }}">
            <a class="nav__link {{ $is('courses.*', 'learn.*') }}" href="{{ route('courses.index') }}">{{ __('الدورات') }}</a>
            @if ($features['blog'] ?? false)
                <a class="nav__link {{ request()->routeIs('blog.*') && request()->route('slug') !== 'news' ? 'is-active' : '' }}" href="{{ route('blog.index') }}">{{ __('المدونة') }}</a>
            @endif
            <div class="dropdown">
                <button class="nav__toggle {{ $is('about', 'contact', 'privacy', 'pages.*') }}" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">{{ __('حول الأكاديمية') }} <x-icon name="chevron-down" size="16"/></button>
                <div class="dropdown__menu" role="menu">
                    <a class="dropdown__item" role="menuitem" href="{{ route('about') }}">{{ __('من نحن') }}</a>
                    @if ($features['blog'] ?? false)
                        <a class="dropdown__item" role="menuitem" href="{{ route('blog.category', 'news') }}">{{ __('أخبارنا') }}</a>
                    @endif
                    <a class="dropdown__item" role="menuitem" href="{{ route('contact') }}">{{ __('تواصل معنا') }}</a>
                    <a class="dropdown__item" role="menuitem" href="{{ route('privacy') }}">{{ __('سياسة الخصوصية') }}</a>
                </div>
            </div>
            @if ($features['library'] ?? false)
                <div class="dropdown">
                    <button class="nav__toggle {{ $is('library.*') }}" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">{{ __('المكتبة') }} <x-icon name="chevron-down" size="16"/></button>
                    <div class="dropdown__menu" role="menu">
                        <a class="dropdown__item" role="menuitem" href="{{ route('library.index') }}"><x-icon name="folder" size="18"/> {{ __('كل الموارد') }}</a>
                        @foreach ($libraryCats as $cat)
                            <a class="dropdown__item" role="menuitem" href="{{ route('library.index', ['category' => $cat->slug]) }}">{{ $cat->tr('name') }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($features['verification'] ?? false)
                <a class="nav__link {{ $is('verify.*') }}" href="{{ route('verify.index') }}">{{ __('التحقق من الشهادة') }}</a>
            @endif
            @if ($features['store'] ?? false)
                <a class="nav__link {{ $is('store.*') }}" href="{{ route('store.index') }}">{{ __('المتجر') }}</a>
            @endif
            @if ($features['tools'] ?? false)
                <a class="nav__link {{ $is('tools.*') }}" href="{{ route('tools.index') }}">{{ __('الأدوات') }}</a>
            @endif
            @foreach ($extraLinks as $link)
                <a class="nav__link" href="{{ $link['url'] }}">{{ $link['label'][$locale] ?? ($link['label']['ar'] ?? '') }}</a>
            @endforeach
        </nav>

        <div class="header-actions">
            <div class="dropdown hide-mobile">
                <button class="btn btn--outline btn--sm" data-dropdown-toggle aria-expanded="false" aria-haspopup="true" aria-label="{{ __('اللغة') }}">
                    <x-icon name="globe" size="16"/> {{ Locales::all()[$locale]['short'] }} <x-icon name="chevron-down" size="14"/>
                </button>
                <div class="dropdown__menu is-end" role="menu" style="min-width:160px">
                    @foreach (Locales::all() as $code => $meta)
                        <a class="dropdown__item" role="menuitem" lang="{{ $code }}" href="{{ route('locale.switch', $code) }}" @if ($code === $locale) aria-current="true" @endif>{{ $meta['name'] }}</a>
                    @endforeach
                </div>
            </div>
            @auth
                <div class="dropdown hide-mobile">
                    <button class="btn btn--outline btn--sm" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
                        <x-icon name="user" size="16"/> <span style="max-width:120px;overflow:hidden;text-overflow:ellipsis">{{ auth()->user()->name }}</span>
                    </button>
                    <div class="dropdown__menu is-end" role="menu">
                        <a class="dropdown__item" role="menuitem" href="{{ route('dashboard') }}"><x-icon name="home" size="18"/> {{ __('لوحة التعلم') }}</a>
                        <a class="dropdown__item" role="menuitem" href="{{ route('profile.edit') }}"><x-icon name="settings" size="18"/> {{ __('الملف الشخصي') }}</a>
                        @if (auth()->user()->isStaff())
                            <a class="dropdown__item" role="menuitem" href="{{ route('admin.dashboard') }}"><x-icon name="layers" size="18"/> {{ __('لوحة الإدارة') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown__item" role="menuitem" type="submit"><x-icon name="log-out" size="18"/> {{ __('تسجيل الخروج') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <a class="btn btn--outline btn--sm hide-mobile" href="{{ route('login') }}"><x-icon name="user" size="16"/> {{ __('دخول') }}</a>
                <a class="btn btn--amber btn--sm hide-mobile" href="{{ route('register') }}"><x-icon name="user-plus" size="16"/> {{ __('حساب جديد') }}</a>
            @endauth
            <button class="icon-btn nav-burger" type="button" data-nav-open aria-expanded="false" aria-controls="mobile-nav" aria-label="{{ __('فتح القائمة') }}"><x-icon name="menu"/></button>
        </div>
    </div>
</header>

{{-- Mobile navigation drawer --}}
<div class="mobile-nav" id="mobile-nav" role="dialog" aria-modal="true" aria-label="{{ __('القائمة') }}">
    <div class="mobile-nav__backdrop" data-nav-close></div>
    <div class="mobile-nav__panel">
        <div class="row row--between" style="margin-bottom:8px">
            <x-logo/>
            <button class="icon-btn" type="button" data-nav-close aria-label="{{ __('إغلاق القائمة') }}"><x-icon name="x"/></button>
        </div>
        <a href="{{ route('courses.index') }}">{{ __('الدورات') }}</a>
        @if ($features['blog'] ?? false)<a href="{{ route('blog.index') }}">{{ __('المدونة') }}</a>@endif
        <details>
            <summary>{{ __('حول الأكاديمية') }} <x-icon name="chevron-down" size="16"/></summary>
            <a href="{{ route('about') }}">{{ __('من نحن') }}</a>
            @if ($features['blog'] ?? false)<a href="{{ route('blog.category', 'news') }}">{{ __('أخبارنا') }}</a>@endif
            <a href="{{ route('contact') }}">{{ __('تواصل معنا') }}</a>
            <a href="{{ route('privacy') }}">{{ __('سياسة الخصوصية') }}</a>
        </details>
        @if ($features['library'] ?? false)
            <details>
                <summary>{{ __('المكتبة') }} <x-icon name="chevron-down" size="16"/></summary>
                <a href="{{ route('library.index') }}">{{ __('كل الموارد') }}</a>
                @foreach ($libraryCats as $cat)<a href="{{ route('library.index', ['category' => $cat->slug]) }}">{{ $cat->tr('name') }}</a>@endforeach
            </details>
        @endif
        @if ($features['verification'] ?? false)<a href="{{ route('verify.index') }}">{{ __('التحقق من الشهادة') }}</a>@endif
        @if ($features['store'] ?? false)<a href="{{ route('store.index') }}">{{ __('المتجر') }}</a>@endif
        @if ($features['tools'] ?? false)<a href="{{ route('tools.index') }}">{{ __('الأدوات') }}</a>@endif
        @foreach ($extraLinks as $link)<a href="{{ $link['url'] }}">{{ $link['label'][$locale] ?? ($link['label']['ar'] ?? '') }}</a>@endforeach
        <details>
            <summary><span class="row" style="gap:6px"><x-icon name="globe" size="18"/> {{ __('اللغة') }}: {{ Locales::all()[$locale]['name'] }}</span> <x-icon name="chevron-down" size="16"/></summary>
            @foreach (Locales::all() as $code => $meta)<a lang="{{ $code }}" href="{{ route('locale.switch', $code) }}">{{ $meta['name'] }}</a>@endforeach
        </details>
        <hr style="border:0;border-top:1px solid var(--border);width:100%">
        @auth
            <a href="{{ route('dashboard') }}">{{ __('لوحة التعلم') }}</a>
            <a href="{{ route('profile.edit') }}">{{ __('الملف الشخصي') }}</a>
            @if (auth()->user()->isStaff())<a href="{{ route('admin.dashboard') }}">{{ __('لوحة الإدارة') }}</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn--ghost btn--block" type="submit">{{ __('تسجيل الخروج') }}</button></form>
        @else
            <a class="btn btn--outline" href="{{ route('login') }}">{{ __('دخول') }}</a>
            <a class="btn btn--amber" href="{{ route('register') }}">{{ __('حساب جديد') }}</a>
        @endauth
    </div>
</div>

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
<footer class="site-footer">
    <div class="container">
        <div class="footer-cta">
            <div class="cta-band">
                <div>
                    <h2>{{ __('ابدأ رحلتك التعليمية اليوم — مجانًا') }}</h2>
                    <p>{{ __('دورات عملية، تقدّم محفوظ، وشهادة ورقية تصلك إلى عنوانك.') }}</p>
                </div>
                <a class="btn btn--white btn--lg" href="{{ route('courses.index') }}">{{ __('تصفّح الدورات') }} <x-icon name="arrow" size="18" style="transform:scaleX({{ $dir === 'rtl' ? -1 : 1 }})"/></a>
            </div>
        </div>
        <div class="footer-grid">
            <div>
                <x-logo light/>
                <p style="margin-top:14px;max-width:46ch">{{ Settings::tr('footer_about') }}</p>
                <ul>
                    @if (Settings::get('contact_email'))<li class="row" style="gap:8px"><x-icon name="mail" size="16"/> <a href="mailto:{{ Settings::get('contact_email') }}" class="ltr">{{ Settings::get('contact_email') }}</a></li>@endif
                    @if (Settings::get('contact_phone'))<li class="row" style="gap:8px"><x-icon name="phone" size="16"/> <a class="ltr" href="tel:{{ preg_replace('/\s+/', '', Settings::get('contact_phone')) }}">{{ Settings::get('contact_phone') }}</a></li>@endif
                    @if (Settings::tr('contact_address'))<li class="row" style="gap:8px"><x-icon name="map-pin" size="16"/> {{ Settings::tr('contact_address') }}</li>@endif
                </ul>
                @php $social = array_filter(Settings::get('social') ?: []); @endphp
                @if ($social)
                    <div class="socials">
                        @foreach ($social as $network => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><x-icon :name="$network === 'x' ? 'x-social' : $network" size="18"/></a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <h2>{{ __('مجالات الدورات') }}</h2>
                <ul>
                    @foreach (\App\Models\Category::ofType('course')->where('is_active', true)->limit(6)->get() as $cat)
                        <li><a href="{{ route('courses.index', ['category' => $cat->slug]) }}">{{ $cat->tr('name') }}</a></li>
                    @endforeach
                    <li><a href="{{ route('courses.index') }}" style="color:var(--amber)">{{ __('كل الدورات') }}</a></li>
                </ul>
            </div>
            <div>
                <h2>{{ __('روابط مفيدة') }}</h2>
                <ul>
                    <li><a href="{{ route('about') }}">{{ __('من نحن') }}</a></li>
                    <li><a href="{{ route('contact') }}">{{ __('تواصل معنا') }}</a></li>
                    @if ($features['verification'] ?? false)<li><a href="{{ route('verify.index') }}">{{ __('التحقق من الشهادة') }}</a></li>@endif
                    @foreach (\App\Models\Page::where('status', 'published')->where('show_in_footer', true)->get() as $p)
                        <li><a href="{{ route('pages.show', $p) }}">{{ $p->tr('title') }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2>{{ __('خدمات') }}</h2>
                <ul>
                    @if ($features['library'] ?? false)<li><a href="{{ route('library.index') }}">{{ __('المكتبة') }}</a></li>@endif
                    @if ($features['blog'] ?? false)<li><a href="{{ route('blog.index') }}">{{ __('المدونة') }}</a></li>@endif
                    @if ($features['tools'] ?? false)
                        @foreach (\App\Models\Tool::where('is_enabled', true)->orderBy('sort_order')->limit(4)->get() as $t)
                            <li><a href="{{ route('tools.show', $t) }}">{{ $t->tr('name') }}</a></li>
                        @endforeach
                    @endif
                    @if ($features['store'] ?? false)<li><a href="{{ route('store.index') }}">{{ __('المتجر') }}</a></li>@endif
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ $siteName }} — {{ __('جميع الحقوق محفوظة') }}</span>
            <a href="#main" class="btn btn--glass btn--sm"><x-icon name="arrow-up" size="16"/> {{ __('إلى الأعلى') }}</a>
        </div>
    </div>
</footer>
@endunless

@include('partials.whatsapp-float')
@include('partials.cookie-bar')
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
{!! Settings::get('body_code') !!}
</body>
</html>
