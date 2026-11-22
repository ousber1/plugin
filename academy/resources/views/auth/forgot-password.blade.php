@extends('layouts.app')
@section('title', __('استعادة كلمة المرور'))
@section('noindex', '1')
@section('content')
<div class="container">
    <form class="card form-card" method="POST" action="{{ route('password.email') }}">
        @csrf
        <h1>{{ __('استعادة كلمة المرور') }}</h1>
        <p class="muted">{{ __('أدخل بريدك الإلكتروني وسنرسل لك رابطًا لتعيين كلمة مرور جديدة.') }}</p>
        @if (session('status'))<div class="alert alert--success" role="status">{{ session('status') }}</div>@endif
        <div class="field"><label for="email">{{ __('البريد الإلكتروني') }}</label>
            <input class="input input-ltr" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            @error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <button class="btn btn--primary btn--block" type="submit">{{ __('إرسال الرابط') }}</button>
        <p class="small" style="text-align:center;margin:16px 0 0"><a href="{{ route('login') }}">{{ __('العودة لتسجيل الدخول') }}</a></p>
    </form>
</div>
@endsection
