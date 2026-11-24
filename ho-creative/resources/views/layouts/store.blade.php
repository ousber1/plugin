<!doctype html>
@php($rtl = app()->getLocale() === 'ar')
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' | '.setting('brand.name') : setting_t('seo.title') }}</title>
<meta name="description" content="{{ trim($__env->yieldContent('description')) ?: setting_t('seo.description') }}">
<link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: url()->current() }}">
@foreach(config('hocreative.locales') as $code => $name)
<link rel="alternate" hreflang="{{ $code }}" href="{{ url()->current() }}?lang={{ $code }}">
@endforeach
<meta property="og:title" content="{{ trim($__env->yieldContent('title')) ?: setting_t('seo.title') }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="{{ $rtl ? 'ar_MA' : 'fr_MA' }}">
@hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css?v={{ filemtime(public_path('assets/app.css')) }}">
<style>:root{--ink:{{ setting('brand.primary_color', '#1B1F3B') }};--accent:{{ setting('brand.accent_color', '#E4007C') }}}</style>
@stack('head')
</head>
<body>
<a class="skip" href="#main">{{ __('Aller au contenu') }}</a>
<div class="topbar"><div class="container">
  <span>{{ setting_t('brand.tagline') }} · {{ setting_t('hours.text') }}</span>
  <span class="flex">
    @if(setting('contact.phone'))<a href="tel:{{ setting('contact.phone') }}">@icon('phone') {{ setting('contact.phone') }}</a>@endif
    <a href="{{ route('quote.request') }}">@icon('quote') {{ __('Devis sur mesure') }}</a>
    @foreach(config('hocreative.locales') as $code => $name)
      @if($code !== app()->getLocale())<a href="{{ route('locale', $code) }}" lang="{{ $code }}">@icon('globe') {{ $name }}</a>@endif
    @endforeach
  </span>
</div></div>
<header class="site-header">
  <div class="container bar">
    <a class="logo" href="{{ route('home') }}">@include('partials.logo')<span>{{ setting('brand.name') }}<small>{{ __('Imprimerie en ligne') }}</small></span></a>
    <form class="search" action="{{ route('search') }}" role="search">
      <label for="q" class="sr-only">{{ __('Rechercher un produit') }}</label>
      <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Cartes de visite, flyers, bâches…') }}">
      <button type="submit" aria-label="{{ __('Rechercher') }}">@icon('search')</button>
    </form>
    <nav class="header-actions" aria-label="{{ __('Compte') }}">
      @auth
        @if(auth()->user()->is_staff)<a class="icon-btn" href="{{ route('admin.dashboard') }}">@icon('settings') <span class="lbl">{{ __('Back-office') }}</span></a>@endif
        <a class="icon-btn" href="{{ route('account.dashboard') }}">@icon('user') <span class="lbl">{{ __('Mon compte') }}</span></a>
      @else
        <a class="icon-btn" href="{{ route('login') }}">@icon('user') <span class="lbl">{{ __('Connexion') }}</span></a>
      @endauth
      <a class="icon-btn" href="{{ route('cart') }}">@icon('cart') <span class="lbl">{{ __('Panier') }}</span> @if(($cartCount ?? 0) > 0)<span class="badge-count" aria-label="{{ __('articles') }}">{{ $cartCount }}</span>@endif</a>
    </nav>
  </div>
  <nav class="catnav" aria-label="{{ __('Catégories') }}"><div class="container"><ul>
    @forelse(($menu['header'] ?? collect()) as $m)
      <li><a href="{{ $m->url }}" @class(['active' => request()->is(ltrim($m->url, '/'))])>{{ tr($m->label) }}</a></li>
    @empty
      @foreach(\App\Models\Category::published()->whereNull('parent_id')->orderBy('sort')->limit(12)->get() as $c)
        <li><a href="{{ route('category', $c->slug) }}">{{ $c->t('name') }}</a></li>
      @endforeach
    @endforelse
  </ul></div></nav>
</header>
<main id="main">
  @if(session('ok') || session('err') || session('warn') || $errors->any())<div class="container" style="padding-top:16px">@include('partials.flash')</div>@endif
  @yield('content')
</main>
<footer class="site-footer">
  <div class="container">
    <div class="cols">
      <div>
        <a class="logo" href="{{ route('home') }}" style="color:#fff">@include('partials.logo')<span>{{ setting('brand.name') }}</span></a>
        <p class="small" style="margin-top:14px;max-width:340px">{{ setting_t('brand.tagline') }}. {{ __('Impression numérique, offset et grand format, avec BAT en ligne et livraison partout au Maroc.') }}</p>
        <p class="small">{{ setting('company.address') }}</p>
        <div class="flex" style="margin-top:12px">
          <span class="pill" style="background:rgba(255,255,255,.08);color:#fff">@icon('lock') {{ __('Fichiers privés') }}</span>
          <span class="pill" style="background:rgba(255,255,255,.08);color:#fff">@icon('shield') {{ __('BAT avant impression') }}</span>
        </div>
      </div>
      <div><h3>{{ __('Produits') }}</h3><ul>
        @foreach(\App\Models\Category::published()->whereNull('parent_id')->orderBy('sort')->limit(6)->get() as $c)<li><a href="{{ route('category', $c->slug) }}">{{ $c->t('name') }}</a></li>@endforeach
      </ul></div>
      <div><h3>{{ __('Aide') }}</h3><ul>
        @foreach(($menu['footer'] ?? collect()) as $m)<li><a href="{{ $m->url }}">{{ tr($m->label) }}</a></li>@endforeach
        <li><a href="{{ route('quote.request') }}">{{ __('Demander un devis') }}</a></li>
        <li><a href="{{ route('templates') }}">{{ __('Modèles personnalisables') }}</a></li>
      </ul></div>
      <div><h3>{{ __('Contact') }}</h3><ul>
        @if(setting('contact.phone'))<li><a href="tel:{{ setting('contact.phone') }}">{{ setting('contact.phone') }}</a></li>@endif
        @if(setting('contact.email'))<li><a href="mailto:{{ setting('contact.email') }}">{{ setting('contact.email') }}</a></li>@endif
        @if(setting('contact.whatsapp'))<li><a href="https://wa.me/{{ setting('contact.whatsapp') }}" rel="noopener">WhatsApp</a></li>@endif
        <li>{{ setting_t('hours.text') }}</li>
      </ul></div>
    </div>
    <hr style="border-color:#2B2F5E;margin-top:30px">
    <p class="small">© {{ date('Y') }} {{ setting('company.legal_name') }} · {{ __('Prix affichés en dirhams (MAD) hors taxes, TVA indiquée au panier.') }}</p>
  </div>
</footer>
@if(setting('contact.whatsapp'))
<a class="wa-float" href="https://wa.me/{{ setting('contact.whatsapp') }}?text={{ urlencode(__('Bonjour HO CREATIVE, j\'ai une question.')) }}" rel="noopener" target="_blank">@icon('chat') WhatsApp</a>
@endif
<script src="/assets/app.js?v={{ filemtime(public_path('assets/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
