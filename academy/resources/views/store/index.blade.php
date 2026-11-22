@extends('layouts.app')
@section('title', __('المتجر'))
@section('description', __('منتجات رقمية قابلة للتحميل.'))
@section('content')
<section class="page-hero"><div class="container"><h1>{{ __('المتجر') }}</h1><p>{{ __('منتجات رقمية قابلة للتحميل. الدفع بالتحويل البنكي بالدرهم. هذا المتجر مستقل تمامًا عن الدورات المجانية.') }}</p></div></section>
<div class="container section--tight">
    @if ($products->isEmpty())
        <div class="card empty overlap"><span class="empty__icon"><x-icon name="store" size="28"/></span><p>{{ __('لا توجد منتجات حاليًا.') }}</p></div>
    @else
        <div class="grid grid--3 overlap">
            @foreach ($products as $p)
                <article class="card post-card">
                    <img src="{{ $p->coverUrl() }}" alt="" loading="lazy" width="640" height="360">
                    <div class="post-card__body">
                        <h2 style="font-size:1.05rem;margin:0"><a href="{{ route('store.show', $p) }}" dir="auto">{{ $p->tr('title') }}</a></h2>
                        <strong style="color:var(--indigo-700)" class="ltr">{{ $p->priceLabel() }}</strong>
                    </div>
                </article>
            @endforeach
        </div>
        {{ $products->links() }}
    @endif
</div>
@endsection
