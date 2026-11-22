@extends('layouts.app')
@section('title', __('التحقق من الشهادة'))
@section('description', __('تحقّق من صحة شهادة صادرة عن الأكاديمية بإدخال رمز التحقق المطبوع عليها.'))
@section('content')
<section class="page-hero page-hero--center"><div class="container">
    <span class="eyebrow" style="background:rgba(255,255,255,.12);color:#fff"><x-icon name="shield" size="14"/> {{ __('التحقق من الشهادة') }}</span>
    <h1>{{ __('هل هذه الشهادة صحيحة؟') }}</h1>
    <p>{{ __('أدخل رمز التحقق المطبوع على الشهادة، أو امسح رمز QR الموجود عليها.') }}</p>
    <form class="search-bar" style="margin:22px auto 0" method="GET" action="{{ route('verify.index') }}">
        <x-icon name="qr"/><label for="code" class="sr-only">{{ __('رمز التحقق') }}</label>
        <input id="code" name="code" required maxlength="40" class="ltr" placeholder="NB-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false">
        <button class="btn btn--primary" type="submit">{{ __('تحقّق') }}</button>
    </form>
    @error('code')<p class="error" style="color:#fff">{{ $message }}</p>@enderror
</div></section>
@endsection
