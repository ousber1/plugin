@extends('layouts.app')
@section('title', __('تواصل معنا'))
@section('description', __('راسل فريق الأكاديمية لأي سؤال حول الدورات أو الشهادات.'))
@section('content')
@php use App\Support\Settings; $wa = Settings::whatsappLink(__('السلام عليكم، لدي سؤال حول الأكاديمية.')); @endphp
<section class="page-hero"><div class="container">
    <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li>{{ __('تواصل معنا') }}</li></ol>
    <h1>{{ __('تواصل معنا') }}</h1><p>{{ __('نسعد بأسئلتك واقتراحاتك. نرد عادة خلال يومي عمل.') }}</p></div></section>
<div class="container section--tight">
    <div class="dash-grid overlap">
        <form class="card card--pad" method="POST" action="{{ route('contact.store') }}">
            @csrf
            @include('partials.form-errors')
            <div class="hp" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="form-grid">
                <div class="field"><label for="name">{{ __('الاسم') }} <span class="req">*</span></label><input class="input" id="name" name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="120" dir="auto"></div>
                <div class="field"><label for="email">{{ __('البريد الإلكتروني') }} <span class="req">*</span></label><input class="input input-ltr" id="email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required></div>
                <div class="field"><label for="phone">{{ __('الهاتف (اختياري)') }}</label><input class="input input-ltr" id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel"></div>
                <div class="field"><label for="subject">{{ __('الموضوع') }}</label><input class="input" id="subject" name="subject" value="{{ old('subject') }}" maxlength="190" dir="auto"></div>
            </div>
            <div class="field"><label for="body">{{ __('رسالتك') }} <span class="req">*</span></label><textarea class="textarea" id="body" name="body" required minlength="10" maxlength="5000" dir="auto">{{ old('body') }}</textarea></div>
            <button class="btn btn--primary" type="submit">{{ __('إرسال') }}</button>
        </form>
        <div class="stack">
            @if ($wa)<a class="btn btn--whatsapp btn--block btn--lg" href="{{ $wa }}" target="_blank" rel="noopener"><x-icon name="whatsapp"/> {{ __('راسلنا عبر واتساب') }}</a>@endif
            <div class="card card--pad">
                <ul class="list-plain">
                    @if (Settings::get('contact_email'))<li class="row"><x-icon name="mail"/> <a class="ltr" href="mailto:{{ Settings::get('contact_email') }}">{{ Settings::get('contact_email') }}</a></li>@endif
                    @if (Settings::get('contact_phone'))<li class="row"><x-icon name="phone"/> <span class="ltr">{{ Settings::get('contact_phone') }}</span></li>@endif
                    @if (Settings::tr('contact_address'))<li class="row"><x-icon name="map-pin"/> {{ Settings::tr('contact_address') }}</li>@endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
