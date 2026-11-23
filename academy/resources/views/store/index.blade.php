@extends('layouts.app')
@section('title', $active ? $active->tr('name').' — '.\App\Support\Settings::tr('store_title') : \App\Support\Settings::tr('store_title'))
@section('description', \App\Support\Settings::tr('store_subtitle'))
@section('canonical', $active ? route('store.index', ['category' => $active->slug]) : route('store.index'))
@if ($q !== '')@section('noindex', '1')@endif
@section('content')
@php use App\Support\Settings; use App\Support\Shop; @endphp
<section class="store-hero">
    <div class="container">
        <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li>{{ __('المتجر') }}</li></ol>
        <div class="store-hero__in">
            <span class="hero__badge"><x-icon name="store" size="14"/> {{ __('متجر :site', ['site' => $siteName]) }}</span>
            <h1>{{ Settings::tr('store_title') }}</h1>
            <p>{{ Settings::tr('store_subtitle') }}</p>
            <form class="search-bar" action="{{ route('store.index') }}" method="GET" role="search">
                @if ($active)<input type="hidden" name="category" value="{{ $active->slug }}">@endif
                <x-icon name="search"/><label for="sq" class="sr-only">{{ __('ابحث في المتجر') }}</label>
                <input id="sq" type="search" name="q" value="{{ $q }}" placeholder="{{ __('ابحث عن قالب، إضافة، تطبيق أو كتاب…') }}" dir="auto">
                <button class="btn btn--primary" type="submit">{{ __('بحث') }}</button>
            </form>
            <div class="hero__actions" style="justify-content:center">
                <a class="btn btn--amber" href="#products"><x-icon name="layers" size="18"/> {{ __('تصفّح المنتجات') }}</a>
                <a class="btn btn--glass" href="{{ route('store.account') }}"><x-icon name="user" size="18"/> {{ __('حسابي في المتجر') }}</a>
                @if (Shop::cartCount())<a class="btn btn--glass" href="{{ route('store.cart') }}"><x-icon name="store" size="18"/> {{ __('السلة') }} ({{ Shop::cartCount() }})</a>@endif
            </div>
            <div class="store-stats">
                <div><strong>{{ $stats['count'] }}</strong><span>{{ __('منتج رقمي') }}</span></div>
                @if ($stats['min'])<div><strong>{{ Shop::money($stats['min']) }}</strong><span>{{ __('أسعار تبدأ من') }}</span></div>@endif
                @if ($stats['discount'])<div><strong>{{ $stats['discount'] }}%</strong><span>{{ __('خصم يصل إلى') }}</span></div>@endif
                <div><strong><x-icon name="download" size="20"/></strong><span>{{ __('تحميل فوري وآمن') }}</span></div>
            </div>
        </div>
    </div>
</section>

<div class="container" id="products">
    <div class="card store-toolbar">
        <nav class="store-cats" aria-label="{{ __('التصنيفات') }}">
            <a href="{{ route('store.index', array_filter(['q' => $q, 'sort' => $sort !== 'newest' ? $sort : null])) }}" class="{{ ! $active ? 'is-active' : '' }}" @if (! $active) aria-current="true" @endif><x-icon name="layers" size="16"/> {{ __('الكل') }}</a>
            @foreach ($categories as $cat)
                <a href="{{ route('store.index', array_filter(['category' => $cat->slug, 'q' => $q, 'sort' => $sort !== 'newest' ? $sort : null])) }}" class="{{ $active?->id === $cat->id ? 'is-active' : '' }}" @if ($active?->id === $cat->id) aria-current="true" @endif>{{ $cat->tr('name') }} <span class="chip__count">{{ $cat->products_count }}</span></a>
            @endforeach
        </nav>
        <div class="row" style="gap:10px">
            <form method="GET" action="{{ route('store.index') }}" data-autosubmit>
                @if ($active)<input type="hidden" name="category" value="{{ $active->slug }}">@endif
                @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
                <label class="sr-only" for="sort">{{ __('الترتيب') }}</label>
                <select class="select select--sm" id="sort" name="sort">
                    @foreach (['newest' => __('الأحدث'), 'popular' => __('الأكثر مبيعًا'), 'price_asc' => __('السعر: من الأقل'), 'price_desc' => __('السعر: من الأعلى'), 'title' => __('الاسم')] as $k => $l)
                        <option value="{{ $k }}" @selected($sort === $k)>{{ $l }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn--sm">{{ __('تطبيق') }}</button></noscript>
            </form>
            @include('store._currency')
        </div>
    </div>
    <p class="muted small" style="margin:14px 0">{{ trans_choice('{0} لا توجد منتجات|{1} منتج واحد|{2} منتجان|[3,10] :count منتجات|[11,*] :count منتجًا', $products->total(), ['count' => $products->total()]) }}@if ($q !== '') — {{ __('نتائج البحث عن «:q»', ['q' => $q]) }}@endif</p>
    @if ($products->isEmpty())
        <div class="card empty"><span class="empty__icon"><x-icon name="store" size="28"/></span><p>{{ __('لا توجد منتجات مطابقة.') }}</p></div>
    @else
        <div class="pgrid">@foreach ($products as $p)@include('store._card', ['p' => $p])@endforeach</div>
        {{ $products->links() }}
    @endif

    <div class="store-trust">
        @foreach ([['download', __('تحميل فوري'), __('تصلك روابط التحميل مباشرة بعد تأكيد الدفع، وتجدها دائمًا في «حسابي في المتجر».')], ['check-circle', __('تحديثات مجانية'), __('كل تحديث للمنتج يصلك من نفس الصفحة دون أي تكلفة إضافية خلال مدة الترخيص.')], ['shield', __('دفع آمن'), __('بياناتك محمية بالكامل، ولا نخزّن أي معلومات بنكية.')], ['whatsapp', __('دعم فني'), __('فريقنا يساعدك في التثبيت والاستخدام عبر واتساب.')]] as [$icon, $t, $d])
            <div class="card card--pad"><span class="kpi__icon"><x-icon :name="$icon" size="18"/></span><h3>{{ $t }}</h3><p class="small muted">{{ $d }}</p></div>
        @endforeach
    </div>
</div>
@endsection
