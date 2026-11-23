@extends('layouts.app')
@section('title', __('السلة'))
@section('noindex', '1')
@section('content')
<section class="store-hero store-hero--sm"><div class="container store-hero__in">
    <h1>{{ __('سلة المشتريات') }}</h1>
    @include('store._steps', ['step' => 1])
</div></section>
<div class="container section--tight">
    @if ($items->isEmpty())
        <div class="card empty overlap"><span class="empty__icon"><x-icon name="store" size="28"/></span><p>{{ __('سلتك فارغة.') }}</p>
            <a class="btn btn--primary" href="{{ route('store.index') }}">{{ __('تصفّح المنتجات') }}</a></div>
    @else
        <div class="checkout-grid overlap">
            <div class="stack">
                @include('store._summary', ['editable' => true])
            </div>
            <div class="card card--pad stack">
                <div class="row row--between"><h2 class="card__title" style="margin:0">{{ __('جاهز للدفع؟') }}</h2>@include('store._currency')</div>
                <p class="muted small">{{ __('المنتجات رقمية: بعد تأكيد الدفع تجد ملفاتك ومفاتيح الترخيص في «حسابي في المتجر».') }}</p>
                <a class="btn btn--primary btn--lg btn--block" href="{{ route('store.checkout') }}">{{ __('متابعة إلى الدفع') }} <x-icon name="arrow" size="18" style="transform:scaleX({{ $dir === 'rtl' ? -1 : 1 }})"/></a>
                <a class="btn btn--ghost btn--block" href="{{ route('store.index') }}">{{ __('متابعة التسوق') }}</a>
            </div>
        </div>
    @endif
</div>
@endsection
