<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>{{ \App\Support\Settings::siteName() }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}">@include('partials.theme')</head>
<body><main class="maint"><div>
    <x-logo light/>
    <h1 style="margin-top:22px">{{ __('الموقع قيد الصيانة') }}</h1>
    <p style="font-size:1.1rem;max-width:52ch">{{ $message }}</p>
    @include('partials.whatsapp-float')
</div></main></body></html>
