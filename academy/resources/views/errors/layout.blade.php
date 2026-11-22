@extends('layouts.app')
@section('noindex', '1')
@section('content')
<div class="container"><div class="card empty form-card">
    <span class="empty__icon"><x-icon :name="trim($__env->yieldContent('icon', 'alert'))" size="28"/></span>
    <h1 style="font-size:1.5rem">@yield('heading')</h1>
    <p>@yield('message')</p>
    <a class="btn btn--primary" href="{{ route('home') }}">{{ __('العودة إلى الرئيسية') }}</a>
</div></div>
@endsection
