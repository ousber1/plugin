@extends('layouts.app')
@section('title', __('تأكيد البريد الإلكتروني'))
@section('noindex', '1')
@section('content')
<div class="container">
    <div class="card form-card" style="text-align:center">
        <span class="empty__icon"><x-icon name="mail" size="28"/></span>
        <h1>{{ __('أكّد بريدك الإلكتروني') }}</h1>
        <p class="muted">{{ __('أرسلنا رابط تأكيد إلى :email. افتح الرسالة واضغط على الرابط.', ['email' => auth()->user()->email]) }}</p>
        @if (session('status'))<div class="alert alert--success" role="status">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn btn--primary" type="submit">{{ __('أعد إرسال الرابط') }}</button></form>
    </div>
</div>
@endsection
