@extends('layouts.app')
@section('title', $center->tr('name').' — '.$center->tr('city'))
@section('description', \Illuminate\Support\Str::limit(strip_tags($center->tr('description')) ?: __('مركز التكوين في :city: العنوان وأوقات العمل والدورات الحضورية.', ['city' => $center->tr('city')]), 300))
@section('canonical', route('centers.show', $center))
@if ($center->image_path)@section('og_image', $center->imageUrl())@endif
@push('head')
<script type="application/ld+json">{!! json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'EducationalOrganization',
    'name' => $center->tr('name'), 'url' => route('centers.show', $center),
    'telephone' => $center->phone, 'email' => $center->email, 'image' => $center->imageUrl(),
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => $center->tr('address'), 'addressLocality' => $center->tr('city'), 'addressCountry' => 'MA'],
    'geo' => $center->latitude ? ['@type' => 'GeoCoordinates', 'latitude' => $center->latitude, 'longitude' => $center->longitude] : null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
@php $wa = $center->whatsappLink(__('السلام عليكم، أريد معلومات عن مركز :name.', ['name' => $center->tr('name', 'ar')])); @endphp
<section class="page-hero">
    <div class="container">
        <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li><a href="{{ route('centers.index') }}">{{ __('مراكزنا') }}</a></li><li>{{ $center->tr('city') }}</li></ol>
        <span class="hero__badge"><x-icon name="map-pin" size="14"/> {{ $center->tr('city') }}</span>
        <h1>{{ $center->tr('name') }}</h1>
        @if ($center->tr('address'))<p>{{ $center->tr('address') }}</p>@endif
    </div>
</section>
<div class="container section--tight">
    <div class="dash-grid overlap">
        <div class="stack">
            @if ($center->image_path)<img class="center-hero-img" src="{{ $center->imageUrl() }}" alt="{{ $center->tr('name') }}">@endif
            @if ($center->tr('description'))
                <div class="card card--pad prose">{!! \App\Support\Html::clean($center->tr('description')) !!}</div>
            @endif
            <div class="card center-map">
                <iframe title="{{ __('موقع المركز على الخريطة') }}" src="{{ $center->mapEmbed() }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
            @if ($courses->isNotEmpty())
                <h2>{{ __('الدورات المتوفرة في هذا المركز') }}</h2>
                <div class="grid grid--3">@foreach ($courses as $course)@include('partials.course-card', ['course' => $course])@endforeach</div>
            @endif
        </div>
        <aside class="stack">
            <div class="card card--pad">
                <h2 class="card__title">{{ __('معلومات التواصل') }}</h2>
                <ul class="list-plain center-info">
                    @if ($center->tr('address'))<li><x-icon name="map-pin"/> <span>{{ $center->tr('address') }}، {{ $center->tr('city') }}</span></li>@endif
                    @if ($center->tr('hours'))<li><x-icon name="clock"/> <span>{{ $center->tr('hours') }}</span></li>@endif
                    @if ($center->phone)<li><x-icon name="phone"/> <a class="ltr" href="tel:{{ preg_replace('/\s+/', '', $center->phone) }}">{{ $center->phone }}</a></li>@endif
                    @if ($center->email)<li><x-icon name="mail"/> <a class="ltr" href="mailto:{{ $center->email }}">{{ $center->email }}</a></li>@endif
                </ul>
                <div class="stack" style="gap:8px;margin-top:14px">
                    @if ($wa)<a class="btn btn--whatsapp btn--block" href="{{ $wa }}" target="_blank" rel="noopener"><x-icon name="whatsapp"/> {{ __('راسل المركز عبر واتساب') }}</a>@endif
                    @if ($center->phone)<a class="btn btn--outline btn--block" href="tel:{{ preg_replace('/\s+/', '', $center->phone) }}"><x-icon name="phone"/> {{ __('اتصل بالمركز') }}</a>@endif
                    <a class="btn btn--ghost btn--block" href="{{ $center->mapLink() }}" target="_blank" rel="noopener"><x-icon name="map-pin"/> {{ __('الاتجاهات على الخريطة') }}</a>
                </div>
            </div>
            @if ($others->isNotEmpty())
                <div class="card card--pad">
                    <h2 class="card__title">{{ __('مراكز أخرى') }}</h2>
                    <ul class="list-plain">
                        @foreach ($others as $o)<li><a href="{{ route('centers.show', $o) }}">{{ $o->tr('name') }}</a> <span class="muted small">— {{ $o->tr('city') }}</span></li>@endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection
