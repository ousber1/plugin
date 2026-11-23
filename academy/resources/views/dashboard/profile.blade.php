@extends('layouts.app')
@section('title', __('الملف الشخصي'))
@section('noindex', '1')
@section('content')
<section class="page-hero"><div class="container"><h1>{{ __('الملف الشخصي') }}</h1></div></section>
<div class="container section--tight">
    <div class="grid grid--2 overlap">
        <form class="card card--pad" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <h2 class="card__title">{{ __('بياناتي') }}</h2>
            @include('partials.form-errors')
            <div class="field"><label for="name">{{ __('الاسم الكامل') }}</label><input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" dir="auto"></div>
            <div class="field"><label for="email">{{ __('البريد الإلكتروني') }}</label><input class="input input-ltr" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                <p class="hint">{{ __('تغيير البريد يتطلب تأكيده من جديد.') }}</p></div>
            <div class="field"><label for="phone">{{ __('الهاتف (اختياري)') }}</label><input class="input input-ltr" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" inputmode="tel"></div>
            @include('partials.center-select', ['selected' => $user->center_id])
            <div class="field"><label for="locale">{{ __('لغة الواجهة') }}</label>
                <select class="select" id="locale" name="locale">@foreach (\App\Support\Locales::all() as $code => $m)<option value="{{ $code }}" @selected(($user->locale ?? app()->getLocale()) === $code)>{{ $m['name'] }}</option>@endforeach</select></div>
            <div class="field"><label for="avatar">{{ __('الصورة الشخصية') }}</label><input class="input" id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></div>
            <button class="btn btn--primary" type="submit">{{ __('حفظ') }}</button>
        </form>
        <form class="card card--pad" method="POST" action="{{ route('profile.password') }}">
            @csrf @method('PUT')
            <h2 class="card__title">{{ __('تغيير كلمة المرور') }}</h2>
            <div class="field"><label for="current_password">{{ __('كلمة المرور الحالية') }}</label><input class="input input-ltr" id="current_password" type="password" name="current_password" required autocomplete="current-password">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password">{{ __('كلمة المرور الجديدة') }}</label><input class="input input-ltr" id="password" type="password" name="password" required autocomplete="new-password" minlength="8">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password_confirmation">{{ __('تأكيد كلمة المرور') }}</label><input class="input input-ltr" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
            <button class="btn btn--primary" type="submit">{{ __('تغيير كلمة المرور') }}</button>
        </form>
    </div>
</div>
@endsection
