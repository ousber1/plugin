@extends('layouts.app')
@section('title', __('تسجيل الدخول'))
@section('noindex', '1')
@section('content')
<div class="container">
    <form class="card form-card" method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <h1>{{ __('تسجيل الدخول') }}</h1>
        <p class="muted">{{ __('مرحبًا بعودتك! تابع تعلمك من حيث توقفت.') }}</p>
        @if (session('status'))<div class="alert alert--success">{{ session('status') }}</div>@endif
        <div class="field">
            <label for="email">{{ __('البريد الإلكتروني') }}</label>
            <input class="input input-ltr" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-err" @enderror>
            @error('email')<p class="error" id="email-err">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password">{{ __('كلمة المرور') }}</label>
            <input class="input input-ltr" id="password" type="password" name="password" required autocomplete="current-password">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="row row--between field">
            <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('تذكّرني') }}</label>
            <a href="{{ route('password.request') }}" class="small">{{ __('نسيت كلمة المرور؟') }}</a>
        </div>
        <button class="btn btn--primary btn--block btn--lg" type="submit">{{ __('دخول') }}</button>
        <p class="small" style="text-align:center;margin:16px 0 0">{{ __('ليس لديك حساب؟') }} <a href="{{ route('register') }}">{{ __('أنشئ حسابًا مجانيًا') }}</a></p>
    </form>
</div>
@endsection
