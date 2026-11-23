{{-- Site header: top bar, main navigation, actions and mobile drawer (Admin › الهيدر والتذييل). --}}
@php
    use App\Support\Settings;
    use App\Support\Locales;
    $libraryCats = ($features['library'] ?? false) ? \App\Models\Category::ofType('resource')->where('is_active', true)->get() : collect();
    $extraLinks = collect(Settings::get('nav_extra_links') ?: [])->filter(fn ($l) => ! empty($l['url']));
    $hasCenters = Settings::feature('centers') && \App\Models\Center::hasPublic();
    $is = fn (...$patterns) => request()->routeIs(...$patterns) ? 'is-active' : '';
    $cta = \App\Support\SiteChrome::headerCta();
    $showAuth = Settings::get('header_show_auth');
    $showLang = Settings::get('header_show_lang');
    $phone = Settings::get('contact_phone');
@endphp
@if (Settings::get('topbar_enabled'))
<div class="topbar">
    <div class="container">
        <p class="topbar__text">{{ Settings::tr('topbar_text') }}</p>
        <div class="topbar__links">
            @if ($phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"><x-icon name="phone" size="14"/> <bdi class="ltr">{{ $phone }}</bdi></a>
            @endif
            @if ($hasCenters)<a href="{{ route('centers.index') }}" class="hide-sm"><x-icon name="map-pin" size="14"/> {{ __('مراكزنا') }}</a>@endif
            <a href="{{ route('contact') }}"><x-icon name="mail" size="14"/> {{ __('تواصل معنا') }}</a>
        </div>
    </div>
</div>
@endif

<header class="site-header{{ Settings::get('sticky_header') ? '' : ' is-static' }}">
    <div class="container site-header__bar">
        <a class="site-header__brand" href="{{ route('home') }}" aria-label="{{ $siteName }} — {{ __('الرئيسية') }}"><x-logo :light="in_array(Settings::get('header_style'), ['dark', 'brand'], true)"/></a>

        <nav class="nav" aria-label="{{ __('التنقل الرئيسي') }}">
            <a class="nav__link nav__link--home {{ $is('home') }}" href="{{ route('home') }}">{{ __('الرئيسية') }}</a>
            <a class="nav__link {{ $is('courses.*', 'learn.*') }}" href="{{ route('courses.index') }}">{{ __('الدورات') }}</a>
            @if ($hasCenters)
                <a class="nav__link {{ $is('centers.*') }}" href="{{ route('centers.index') }}">{{ __('مراكزنا') }}</a>
            @endif
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
            @if ($features['tools'] ?? false)
                <a class="nav__link {{ $is('tools.*') }}" href="{{ route('tools.index') }}">{{ __('الأدوات') }}</a>
            @endif
            @if ($features['blog'] ?? false)
                <a class="nav__link {{ $is('blog.*') }}" href="{{ route('blog.index') }}">{{ __('المدونة') }}</a>
            @endif
            @foreach ($extraLinks as $link)
                <a class="nav__link" href="{{ $link['url'] }}">{{ $link['label'][$locale] ?? ($link['label']['ar'] ?? '') }}</a>
            @endforeach
            <div class="dropdown">
                <button class="nav__toggle {{ $is('about', 'contact', 'privacy', 'pages.*', 'verify.*', 'store.*') }}" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">{{ __('المزيد') }} <x-icon name="chevron-down" size="16"/></button>
                <div class="dropdown__menu" role="menu">
                    <a class="dropdown__item" role="menuitem" href="{{ route('about') }}"><x-icon name="info" size="18"/> {{ __('من نحن') }}</a>
                    @if ($features['verification'] ?? false)
                        <a class="dropdown__item" role="menuitem" href="{{ route('verify.index') }}"><x-icon name="shield" size="18"/> {{ __('التحقق من الشهادة') }}</a>
                    @endif
                    @if ($features['blog'] ?? false)
                        <a class="dropdown__item" role="menuitem" href="{{ route('blog.category', 'news') }}"><x-icon name="file-text" size="18"/> {{ __('أخبارنا') }}</a>
                    @endif
                    @if ($features['store'] ?? false)
                        <a class="dropdown__item" role="menuitem" href="{{ route('store.index') }}"><x-icon name="store" size="18"/> {{ __('المتجر') }}</a>
                    @endif
                    <a class="dropdown__item" role="menuitem" href="{{ route('contact') }}"><x-icon name="mail" size="18"/> {{ __('تواصل معنا') }}</a>
                    <a class="dropdown__item" role="menuitem" href="{{ route('privacy') }}"><x-icon name="lock" size="18"/> {{ __('سياسة الخصوصية') }}</a>
                </div>
            </div>
        </nav>

        <div class="header-actions">
            @if ($showLang)
                <div class="dropdown hide-mobile">
                    <button class="icon-btn icon-btn--lang" data-dropdown-toggle aria-expanded="false" aria-haspopup="true" aria-label="{{ __('اللغة') }}: {{ Locales::all()[$locale]['name'] }}">
                        <x-icon name="globe" size="18"/><span>{{ Locales::all()[$locale]['short'] }}</span>
                    </button>
                    <div class="dropdown__menu is-end" role="menu" style="min-width:160px">
                        @foreach (Locales::all() as $code => $meta)
                            <a class="dropdown__item" role="menuitem" lang="{{ $code }}" href="{{ route('locale.switch', $code) }}" @if ($code === $locale) aria-current="true" @endif>{{ $meta['name'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            @auth
                <div class="dropdown hide-mobile">
                    <button class="icon-btn icon-btn--user" data-dropdown-toggle aria-expanded="false" aria-haspopup="true" aria-label="{{ __('حسابي') }}: {{ auth()->user()->name }}">
                        <span class="avatar-dot" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    </button>
                    <div class="dropdown__menu is-end" role="menu">
                        <div class="dropdown__head">{{ auth()->user()->name }}</div>
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
            @elseif ($showAuth)
                <a class="btn btn--ghost btn--sm hide-mobile header-login" href="{{ route('login') }}"><x-icon name="user" size="16"/> {{ __('دخول') }}</a>
                @unless ($cta)
                    <a class="btn btn--amber btn--sm hide-mobile" href="{{ route('register') }}">{{ __('حساب جديد') }}</a>
                @endunless
            @endauth
            @if ($cta)
                <a class="btn btn--sm header-cta {{ $cta['whatsapp'] ? 'btn--whatsapp' : 'btn--amber' }}" href="{{ $cta['url'] }}" @if ($cta['external']) target="_blank" rel="noopener" @endif aria-label="{{ $cta['label'] }}">
                    <x-icon :name="$cta['whatsapp'] ? 'whatsapp' : 'award'" size="18"/><span class="header-cta__text">{{ $cta['label'] }}</span>
                </a>
            @endif
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
        @if ($cta)
            <a class="btn {{ $cta['whatsapp'] ? 'btn--whatsapp' : 'btn--amber' }} btn--block" href="{{ $cta['url'] }}" @if ($cta['external']) target="_blank" rel="noopener" @endif><x-icon :name="$cta['whatsapp'] ? 'whatsapp' : 'award'" size="18"/> {{ $cta['label'] }}</a>
        @endif
        <a href="{{ route('home') }}">{{ __('الرئيسية') }}</a>
        <a href="{{ route('courses.index') }}">{{ __('الدورات') }}</a>
        @if ($hasCenters)<a href="{{ route('centers.index') }}">{{ __('مراكزنا') }}</a>@endif
        @if ($features['library'] ?? false)
            <details>
                <summary>{{ __('المكتبة') }} <x-icon name="chevron-down" size="16"/></summary>
                <a href="{{ route('library.index') }}">{{ __('كل الموارد') }}</a>
                @foreach ($libraryCats as $cat)<a href="{{ route('library.index', ['category' => $cat->slug]) }}">{{ $cat->tr('name') }}</a>@endforeach
            </details>
        @endif
        @if ($features['tools'] ?? false)<a href="{{ route('tools.index') }}">{{ __('الأدوات') }}</a>@endif
        @if ($features['blog'] ?? false)<a href="{{ route('blog.index') }}">{{ __('المدونة') }}</a>@endif
        @foreach ($extraLinks as $link)<a href="{{ $link['url'] }}">{{ $link['label'][$locale] ?? ($link['label']['ar'] ?? '') }}</a>@endforeach
        <details>
            <summary>{{ __('المزيد') }} <x-icon name="chevron-down" size="16"/></summary>
            <a href="{{ route('about') }}">{{ __('من نحن') }}</a>
            @if ($features['verification'] ?? false)<a href="{{ route('verify.index') }}">{{ __('التحقق من الشهادة') }}</a>@endif
            @if ($features['blog'] ?? false)<a href="{{ route('blog.category', 'news') }}">{{ __('أخبارنا') }}</a>@endif
            @if ($features['store'] ?? false)<a href="{{ route('store.index') }}">{{ __('المتجر') }}</a>@endif
            <a href="{{ route('contact') }}">{{ __('تواصل معنا') }}</a>
            <a href="{{ route('privacy') }}">{{ __('سياسة الخصوصية') }}</a>
        </details>
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
            <a class="btn btn--primary" href="{{ route('register') }}">{{ __('حساب جديد') }}</a>
        @endauth
    </div>
</div>
