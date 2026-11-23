@extends('layouts.app')
@section('title', $product->tr('seo_title') ?: $product->tr('title'))
@section('description', $product->tr('seo_description') ?: ($product->tr('subtitle') ?: \Illuminate\Support\Str::limit(strip_tags($product->tr('description')), 160)))
@section('canonical', route('store.show', $product))
@section('og_image', $product->coverUrl())
@push('head')
<script type="application/ld+json">{!! json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->tr('title'),
    'description' => \Illuminate\Support\Str::limit(strip_tags($product->tr('description')), 300), 'image' => $product->coverUrl(),
    'offers' => ['@type' => 'Offer', 'price' => $product->price('MAD') / 100, 'priceCurrency' => 'MAD', 'availability' => 'https://schema.org/InStock', 'url' => route('store.show', $product)],
    'aggregateRating' => $product->averageRating() ? ['@type' => 'AggregateRating', 'ratingValue' => $product->averageRating(), 'reviewCount' => $product->approved_reviews_count] : null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
@php
    use App\Support\Shop;
    $compare = $product->comparePrice(); $rating = $product->averageRating();
    $gallery = $product->galleryUrls(); $features = $product->trList('features'); $requirements = $product->trList('requirements');
    $changelog = $product->changelogEntries();
@endphp
<div class="store-crumbs">
    <div class="container row row--between">
        <ol class="breadcrumbs breadcrumbs--dark"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li><a href="{{ route('store.index') }}">{{ __('المتجر') }}</a></li>
            @if ($product->category)<li><a href="{{ route('store.index', ['category' => $product->category->slug]) }}">{{ $product->category->tr('name') }}</a></li>@endif
            <li>{{ $product->tr('title') }}</li></ol>
        <a class="btn btn--ghost btn--sm" href="{{ route('store.account') }}"><x-icon name="user" size="16"/> {{ __('حسابي في المتجر') }}</a>
    </div>
</div>

<div class="container section--tight">
    <header class="phead">
        <div class="pcard__tags">
            @if ($product->category)<a class="ptag" href="{{ route('store.index', ['category' => $product->category->slug]) }}">{{ $product->category->tr('name') }}</a>@endif
            @if ($product->isRecentlyUpdated())<span class="pbadge pbadge--green"><x-icon name="check" size="12"/> {{ __('محدث مؤخرًا') }}</span>@endif
            @if ($product->isNew())<span class="pbadge pbadge--dark">{{ __('جديد') }}</span>@endif
            @if ($product->billing !== 'once')<span class="ptag ptag--soft"><x-icon name="shield" size="12"/> {{ __('نسخة أصلية بمفتاح ترخيص') }}</span>@endif
        </div>
        <h1 dir="auto">{{ $product->tr('title') }}@if ($product->tr('subtitle')) — {{ $product->tr('subtitle') }}@endif</h1>
        <div class="phead__meta">
            <span>@include('store._stars', ['rating' => $rating]) {{ $rating ? $rating.' ('.$product->approved_reviews_count.')' : __('لا توجد تقييمات بعد') }}</span>
            @if ($product->version)<span><x-icon name="layers" size="14"/> {{ __('الإصدار') }} <strong class="ltr">{{ $product->version }}</strong></span>@endif
            @if ($product->updated_on)<span><x-icon name="calendar" size="14"/> {{ __('آخر تحديث') }} <strong>{{ $product->updated_on->translatedFormat('j F Y') }}</strong></span>@endif
        </div>
    </header>

    <div class="pgrid-layout">
        <div class="stack">
            <div class="card pgallery" data-gallery>
                <div class="pgallery__main">
                    <img src="{{ $product->coverUrl() }}" alt="{{ $product->tr('title') }}" data-gallery-main width="1280" height="720">
                    <button class="btn btn--glass btn--sm pgallery__zoom" type="button" data-gallery-zoom><x-icon name="search" size="16"/> {{ __('معاينة') }}</button>
                </div>
                @if ($gallery)
                    <div class="pgallery__thumbs">
                        <button type="button" class="is-active" data-gallery-thumb="{{ $product->coverUrl() }}" aria-label="{{ __('الصورة 1') }}"><img src="{{ $product->coverUrl() }}" alt=""></button>
                        @foreach ($gallery as $i => $g)<button type="button" data-gallery-thumb="{{ $g }}" aria-label="{{ __('الصورة :n', ['n' => $i + 2]) }}"><img src="{{ $g }}" alt="" loading="lazy"></button>@endforeach
                    </div>
                @endif
            </div>

            <div class="card ptabs" data-tabs>
                <div class="ptabs__list" role="tablist">
                    <button role="tab" aria-selected="true" aria-controls="tab-desc" id="t-desc">{{ __('الوصف') }}</button>
                    @if ($features || $requirements)<button role="tab" aria-selected="false" aria-controls="tab-get" id="t-get">{{ __('ما ستحصل عليه') }}</button>@endif
                    @if ($changelog)<button role="tab" aria-selected="false" aria-controls="tab-log" id="t-log">{{ __('سجل التحديثات') }}</button>@endif
                    <button role="tab" aria-selected="false" aria-controls="tab-rev" id="t-rev">{{ __('التقييمات') }} @if ($product->approved_reviews_count)({{ $product->approved_reviews_count }})@endif</button>
                </div>
                <div class="ptabs__panel prose" role="tabpanel" id="tab-desc" aria-labelledby="t-desc">{!! \App\Support\Html::clean($product->tr('description')) !!}</div>
                @if ($features || $requirements)
                    <div class="ptabs__panel" role="tabpanel" id="tab-get" aria-labelledby="t-get" hidden>
                        @if ($features)<h2 class="h3">{{ __('ما ستحصل عليه') }}</h2><ul class="checklist">@foreach ($features as $f)<li><x-icon name="check-circle" size="18"/> {{ $f }}</li>@endforeach</ul>@endif
                        @if ($requirements)<h2 class="h3" style="margin-top:18px">{{ __('المتطلبات') }}</h2><ul class="prose">@foreach ($requirements as $r)<li>{{ $r }}</li>@endforeach</ul>@endif
                    </div>
                @endif
                @if ($changelog)
                    <div class="ptabs__panel" role="tabpanel" id="tab-log" aria-labelledby="t-log" hidden>
                        <ol class="changelog">
                            @foreach ($changelog as $e)
                                <li><div class="row" style="gap:8px"><span class="pver ltr">v{{ $e['version'] }}</span>@if (! empty($e['date']))<span class="muted small">{{ $e['date'] }}</span>@endif</div>
                                    <p dir="auto">{!! nl2br(e($e['notes'] ?? '')) !!}</p></li>
                            @endforeach
                        </ol>
                    </div>
                @endif
                <div class="ptabs__panel" role="tabpanel" id="tab-rev" aria-labelledby="t-rev" hidden>
                    @forelse ($reviews as $r)
                        <div class="preview"><div class="row row--between"><strong>{{ $r->user?->name }}</strong>@include('store._stars', ['rating' => $r->rating])</div>
                            @if ($r->body)<p dir="auto">{{ $r->body }}</p>@endif<small class="muted">{{ $r->created_at->diffForHumans() }}</small></div>
                    @empty
                        <p class="muted">{{ __('لا توجد تقييمات بعد. التقييمات متاحة فقط لمن اشترى المنتج.') }}</p>
                    @endforelse
                    @if ($owned)
                        <form class="card card--pad" method="POST" action="{{ route('store.review', $product) }}" style="margin-top:14px">@csrf
                            <h3>{{ $myReview ? __('عدّل تقييمك') : __('قيّم المنتج') }}</h3>
                            <fieldset class="field" style="border:0;padding:0;margin:0 0 8px"><legend class="label">{{ __('التقييم') }}</legend>
                                <div class="row stars" data-stars style="gap:2px">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <input class="sr-only" type="radio" id="rv{{ $i }}" name="rating" value="{{ $i }}" @checked(($myReview->rating ?? 5) == $i) required>
                                        <label for="rv{{ $i }}" data-v="{{ $i }}" style="cursor:pointer"><x-icon name="star" size="26"/><span class="sr-only">{{ __(':n من 5', ['n' => $i]) }}</span></label>
                                    @endfor
                                </div></fieldset>
                            <textarea class="textarea" name="body" rows="3" maxlength="2000" dir="auto" placeholder="{{ __('رأيك في المنتج (اختياري)') }}">{{ $myReview->body ?? '' }}</textarea>
                            <button class="btn btn--primary btn--sm" type="submit" style="margin-top:8px">{{ __('إرسال') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <aside class="stack pside">
            <div class="card card--pad pbuy">
                <div class="row row--between"><strong><x-icon name="shield" size="16"/> {{ trans_choice('{1} ترخيص موقع واحد|{2} ترخيص موقعين|[3,10] ترخيص :count مواقع|[11,*] ترخيص :count موقعًا', $product->license_sites, ['count' => $product->license_sites]) }}</strong>@include('store._currency')</div>
                <div class="pbuy__price">
                    @if ($compare)<del>{{ Shop::money($compare) }}</del>@endif
                    <strong>{{ $product->priceLabel() }}</strong>@if ($product->billingLabel())<span>/ {{ $product->billingLabel() }}</span>@endif
                    @if ($product->billing !== 'once')<p class="small" style="color:var(--green);margin:4px 0 0">{{ __('اشتراك متجدد — يُجدَّد نفس المفتاح عند الدفع مرة أخرى.') }}</p>@endif
                </div>
                @if ($product->demo_url)
                    <a class="pdemo" href="{{ $product->demo_url }}" target="_blank" rel="noopener"><span class="pdemo__icon"><x-icon name="play" size="18"/></span><span><strong>{{ __('جرّب المنتج مباشرة') }}</strong><small>{{ __('تجربة تفاعلية مجانية قبل الشراء') }}</small></span><x-icon name="arrow" size="16" style="transform:scaleX({{ $dir === 'rtl' ? -1 : 1 }})"/></a>
                @endif
                <ul class="checklist checklist--sm">
                    @if ($product->billing !== 'once')<li><x-icon name="check-circle" size="16"/> {{ __('تحديثات ودعم طوال مدة الاشتراك') }}</li><li><x-icon name="check-circle" size="16"/> {{ __('تذكير قبل انتهاء الاشتراك') }}</li>@else<li><x-icon name="check-circle" size="16"/> {{ __('شراء مرة واحدة وتحديثات مجانية') }}</li>@endif
                    <li><x-icon name="check-circle" size="16"/> {{ trans_choice('{1} مفتاح ترخيص أصلي لموقع واحد|{2} مفتاح ترخيص أصلي لموقعين|[3,10] مفتاح ترخيص أصلي لـ :count مواقع|[11,*] مفتاح ترخيص أصلي لـ :count موقعًا', $product->license_sites, ['count' => $product->license_sites]) }}</li>
                    <li><x-icon name="check-circle" size="16"/> {{ __('تحميل فوري بعد تأكيد الدفع') }}</li>
                    <li><x-icon name="check-circle" size="16"/> {{ __('دعم في التثبيت والإعداد') }}</li>
                </ul>
                @if ($owned && $owned->isActive() && $product->billing === 'once')
                    <a class="btn btn--whatsapp btn--block btn--lg" href="{{ route('store.download', [$owned->order, $owned]) }}"><x-icon name="download" size="18"/> {{ __('تحميل المنتج') }}</a>
                @else
                    <form method="POST" action="{{ route('store.cart.add', $product) }}">@csrf<input type="hidden" name="buy_now" value="1">
                        <button class="btn btn--primary btn--block btn--lg" type="submit"><x-icon name="sparkles" size="18"/> {{ $owned ? __('جدّد الاشتراك') : __('اشترِ الآن') }}</button></form>
                    @if ($inCart)
                        <a class="btn btn--outline btn--block" href="{{ route('store.cart') }}"><x-icon name="store" size="18"/> {{ __('في السلة — إتمام الطلب') }}</a>
                    @else
                        <form method="POST" action="{{ route('store.cart.add', $product) }}">@csrf
                            <button class="btn btn--outline btn--block" type="submit"><x-icon name="store" size="18"/> {{ __('أضف إلى السلة') }}</button></form>
                    @endif
                    @if ($owned)<a class="btn btn--ghost btn--block btn--sm" href="{{ route('store.account') }}">{{ __('ملفاتك في «حسابي في المتجر»') }}</a>@endif
                @endif
                <p class="small muted" style="margin:0"><x-icon name="lock" size="14"/> {{ __('دفع آمن ومشفّر — الملفات وتحديثاتها تجدها دائمًا في «حسابي في المتجر».') }}</p>
            </div>

            <div class="card card--pad">
                <h2 class="card__title">{{ __('معلومات المنتج') }}</h2>
                <dl class="pinfo">
                    @if ($product->updated_on)<dt>{{ __('آخر تحديث') }}</dt><dd>{{ $product->updated_on->translatedFormat('j F Y') }}</dd>@endif
                    @if ($product->version)<dt>{{ __('الإصدار') }}</dt><dd class="ltr">{{ $product->version }}</dd>@endif
                    @if ($product->requires)<dt>{{ __('يتطلب') }}</dt><dd class="ltr">{{ $product->requires }}</dd>@endif
                    @if ($product->tested_up_to)<dt>{{ __('مختبر حتى') }}</dt><dd class="ltr">{{ $product->tested_up_to }}</dd>@endif
                    @if ($product->file_format)<dt>{{ __('الصيغة') }}</dt><dd>{{ $product->file_format }}</dd>@endif
                    @if ($product->files_count)<dt>{{ __('الملفات') }}</dt><dd>{{ $product->files_count }}</dd>@endif
                    @if ($product->category)<dt>{{ __('التصنيف') }}</dt><dd><a href="{{ route('store.index', ['category' => $product->category->slug]) }}">{{ $product->category->tr('name') }}</a></dd>@endif
                    @if ($s = $product->salesCount())<dt>{{ __('المبيعات') }}</dt><dd>{{ $s }}</dd>@endif
                </dl>
            </div>
            <div class="ptrust">
                <div><x-icon name="lock" size="18"/><strong>{{ __('دفع آمن') }}</strong><small>{{ __('معاملات مشفّرة') }}</small></div>
                <div><x-icon name="shield" size="18"/><strong>{{ __('ملفات موثوقة') }}</strong><small>{{ __('نظيفة وخالية من الفيروسات') }}</small></div>
                <div><x-icon name="check-circle" size="18"/><strong>{{ __('تحديثات منتظمة') }}</strong><small>{{ __('تصلك مجانًا') }}</small></div>
            </div>
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="section">
            <div class="section__head"><div><h2>{{ __('منتجات قد تعجبك') }}</h2></div><a class="btn btn--outline" href="{{ route('store.index') }}">{{ __('كل المنتجات') }}</a></div>
            <div class="pgrid">@foreach ($related as $p)@include('store._card', ['p' => $p])@endforeach</div>
        </section>
    @endif
</div>

<dialog class="plightbox" data-gallery-dialog aria-label="{{ __('معاينة الصورة') }}">
    <form method="dialog"><button class="icon-btn" aria-label="{{ __('إغلاق') }}"><x-icon name="x"/></button></form>
    <img src="" alt="{{ $product->tr('title') }}">
</dialog>
@endsection
