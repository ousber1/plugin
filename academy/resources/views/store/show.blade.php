@extends('layouts.app')
@section('title', $product->tr('title'))
@section('description', \App\Support\Html::excerpt($product->tr('description')))
@section('content')
<section class="page-hero"><div class="container"><ol class="breadcrumbs"><li><a href="{{ route('store.index') }}">{{ __('المتجر') }}</a></li></ol><h1 dir="auto">{{ $product->tr('title') }}</h1></div></section>
<div class="container section--tight">
    <div class="dash-grid overlap">
        <div class="card card--pad"><img src="{{ $product->coverUrl() }}" alt="" style="border-radius:var(--radius);margin-bottom:16px" width="640" height="360"><div class="prose" dir="auto">{!! $product->tr('description') !!}</div></div>
        <div class="card card--pad stack">
            <strong style="font-size:1.6rem;color:var(--indigo-700)" class="ltr">{{ $product->priceLabel() }}</strong>
            <p class="small muted">{{ __('الدفع عبر تحويل بنكي يدوي. بعد تأكيد التحويل من طرف الإدارة يصبح التحميل متاحًا في حسابك.') }}</p>
            @auth
                <form method="POST" action="{{ route('store.checkout', $product) }}">@csrf
                    <label class="check small field"><input type="checkbox" name="accept" value="1" required> {{ __('أوافق على شروط البيع والدفع بالتحويل البنكي') }}</label>
                    @error('accept')<p class="error">{{ $message }}</p>@enderror
                    <button class="btn btn--primary btn--block" type="submit">{{ __('اطلب المنتج') }}</button>
                </form>
            @else
                <a class="btn btn--primary btn--block" href="{{ route('login', ['intended' => route('store.show', $product)]) }}">{{ __('سجّل الدخول للطلب') }}</a>
            @endauth
        </div>
    </div>
</div>
@endsection
