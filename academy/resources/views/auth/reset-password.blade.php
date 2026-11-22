@extends('layouts.app')
@section('title', __('تعيين كلمة مرور جديدة'))
@section('noindex', '1')
@section('content')
<div class="container">
    <form class="card form-card" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <h1>{{ __('تعيين كلمة مرور جديدة') }}</h1>
        @include('partials.form-errors')
        <div class="field"><label for="email">{{ __('البريد الإلكتروني') }}</label><input class="input input-ltr" id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
        <div class="field"><label for="password">{{ __('كلمة المرور الجديدة') }}</label><input class="input input-ltr" id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div class="field"><label for="password_confirmation">{{ __('تأكيد كلمة المرور') }}</label><input class="input input-ltr" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="btn btn--primary btn--block" type="submit">{{ __('حفظ كلمة المرور') }}</button>
    </form>
</div>
@endsection
