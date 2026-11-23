@extends('layouts.app')
@section('title', __('إنشاء حساب'))
@section('noindex', '1')
@section('content')
<div class="container">
    <form class="card form-card" method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <h1>{{ __('أنشئ حسابك المجاني') }}</h1>
        <p class="muted">{{ __('سجّل في أي دورة مجانًا وتابع تقدّمك.') }}</p>
        @include('partials.form-errors')
        <div class="field"><label for="name">{{ __('الاسم الكامل') }} <span class="req">*</span></label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" dir="auto" @error('name') aria-invalid="true" @enderror></div>
        <div class="field"><label for="email">{{ __('البريد الإلكتروني') }} <span class="req">*</span></label>
            <input class="input input-ltr" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" @error('email') aria-invalid="true" @enderror></div>
        <div class="field"><label for="phone">{{ __('الهاتف (اختياري)') }}</label>
            <input class="input input-ltr" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" placeholder="+212 6…"></div>
        @include('partials.center-select', ['selected' => null])
        <div class="field"><label for="password">{{ __('كلمة المرور') }} <span class="req">*</span></label>
            <input class="input input-ltr" id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="pw-hint" @error('password') aria-invalid="true" @enderror>
            <p class="hint" id="pw-hint">{{ __('8 أحرف على الأقل.') }}</p></div>
        <div class="field"><label for="password_confirmation">{{ __('تأكيد كلمة المرور') }} <span class="req">*</span></label>
            <input class="input input-ltr" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <div class="field"><label class="check"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> <span>{{ __('أوافق على') }} <a href="{{ route('privacy') }}" target="_blank">{{ __('سياسة الخصوصية') }}</a></span></label>
            @error('terms')<p class="error">{{ $message }}</p>@enderror</div>
        <button class="btn btn--amber btn--block btn--lg" type="submit">{{ __('إنشاء الحساب') }}</button>
        <p class="small" style="text-align:center;margin:16px 0 0">{{ __('لديك حساب؟') }} <a href="{{ route('login') }}">{{ __('سجّل الدخول') }}</a></p>
    </form>
</div>
@endsection
