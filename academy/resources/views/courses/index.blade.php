@extends('layouts.app')
@section('title', $activeCategory ? $activeCategory->tr('name') : __('جميع الدورات'))
@section('description', \App\Models\Course::anyPaid() ? __('تصفّح كل الدورات المجانية والمدفوعة: ابحث وصفِّ حسب المجال والمستوى والسعر.') : __('تصفّح كل الدورات المجانية: ابحث وصفِّ حسب المجال والمستوى.'))
@section('canonical', $activeCategory ? route('courses.index', ['category' => $activeCategory->slug]) : route('courses.index'))
@if (!empty($filters['q']))
    @section('noindex', '1')
@endif

@section('content')
<section class="page-hero">
    <div class="container">
        <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li>{{ __('الدورات') }}</li></ol>
        <div class="row row--between" style="align-items:end">
            <div>
                <h1>{{ $activeCategory ? $activeCategory->tr('name') : __('جميع الدورات') }}</h1>
                <p>{{ \App\Models\Course::anyPaid() ? __('دورات عملية مجانية ومدفوعة في البرمجة والتصميم واللغات والمهارات الرقمية. اختر مجالك وابدأ الآن.') : __('دورات عملية مجانية في البرمجة والتصميم واللغات والمهارات الرقمية. اختر مجالك وابدأ الآن.') }}</p>
            </div>
            <div class="card card--pad" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff;text-align:center;min-width:140px">
                <strong style="font-size:2rem;color:var(--amber);display:block;line-height:1">{{ $totalPublished }}</strong>{{ \App\Models\Course::anyPaid() ? __('دورة') : __('دورة مجانية') }}
            </div>
        </div>
    </div>
</section>

<div class="container">
    <form class="search-bar overlap" action="{{ route('courses.index') }}" method="GET" role="search" style="margin-inline:auto;max-width:760px">
        <x-icon name="search"/>
        <label for="q" class="sr-only">{{ __('ابحث عن دورة') }}</label>
        <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ابحث عن دورة، مهارة أو مجال…') }}" dir="auto">
        @foreach (['category', 'level', 'sort', 'price'] as $keep)
            @if (!empty($filters[$keep]))<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif
        @endforeach
        <button class="btn btn--primary" type="submit">{{ __('ابحث') }}</button>
    </form>

    <div class="section--tight">
        <nav class="chips" aria-label="{{ __('المجالات') }}">
            <a class="chip {{ !$activeCategory ? 'is-active' : '' }}" href="{{ route('courses.index', array_filter(['q' => $filters['q'] ?? null, 'level' => $filters['level'] ?? null, 'sort' => $filters['sort'] ?? null])) }}" @if(!$activeCategory) aria-current="page" @endif><x-icon name="layers" size="16"/> {{ __('الكل') }}</a>
            @foreach ($categories->where('courses_count', '>', 0) as $cat)
                <a class="chip {{ $activeCategory?->id === $cat->id ? 'is-active' : '' }}" href="{{ route('courses.index', array_filter(['category' => $cat->slug, 'q' => $filters['q'] ?? null, 'level' => $filters['level'] ?? null, 'sort' => $filters['sort'] ?? null])) }}" @if($activeCategory?->id === $cat->id) aria-current="page" @endif>
                    <x-icon :name="$cat->icon ?: 'layers'" size="16"/> {{ $cat->tr('name') }} <span class="chip__count">{{ $cat->courses_count }}</span>
                </a>
            @endforeach
        </nav>

        <form class="filters" method="GET" action="{{ route('courses.index') }}" data-autosubmit>
            @if (!empty($filters['q']))<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
            @if ($activeCategory)<input type="hidden" name="category" value="{{ $activeCategory->slug }}">@endif
            <div class="field">
                <label for="level">{{ __('المستوى') }}</label>
                <select class="select" id="level" name="level">
                    <option value="">{{ __('كل المستويات') }}</option>
                    @foreach (\App\Enums\Level::cases() as $lv)
                        <option value="{{ $lv->value }}" @selected(($filters['level'] ?? '') === $lv->value)>{{ $lv->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if (\App\Models\Course::anyPaid())
                <div class="field">
                    <label for="price">{{ __('السعر') }}</label>
                    <select class="select" id="price" name="price">
                        <option value="">{{ __('الكل') }}</option>
                        <option value="free" @selected(($filters['price'] ?? '') === 'free')>{{ __('مجانية') }}</option>
                        <option value="paid" @selected(($filters['price'] ?? '') === 'paid')>{{ __('مدفوعة') }}</option>
                    </select>
                </div>
            @endif
            <div class="field">
                <label for="sort">{{ __('الترتيب') }}</label>
                <select class="select" id="sort" name="sort">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>{{ __('الأحدث') }}</option>
                    <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>{{ __('الأكثر تسجيلًا') }}</option>
                    @if (\App\Models\Course::anyPaid())<option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('السعر: من الأقل') }}</option><option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('السعر: من الأعلى') }}</option>@endif
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>{{ __('الأقدم') }}</option>
                    <option value="title" @selected(($filters['sort'] ?? '') === 'title')>{{ __('أبجديًا') }}</option>
                </select>
            </div>
            <div class="row">
                <noscript><button class="btn btn--primary" type="submit">{{ __('تطبيق') }}</button></noscript>
                @if (array_filter($filters))<a class="btn btn--ghost" href="{{ route('courses.index') }}">{{ __('مسح الفلاتر') }}</a>@endif
            </div>
            <p class="muted small" style="margin:0 0 10px;margin-inline-start:auto" aria-live="polite"><x-icon name="list" size="16"/> {{ __(':count نتيجة', ['count' => $courses->total()]) }}</p>
        </form>

        @if ($courses->isEmpty())
            <div class="card empty">
                <span class="empty__icon"><x-icon name="search" size="28"/></span>
                <h2 style="font-size:1.25rem">{{ __('لا توجد دورات مطابقة') }}</h2>
                <p>{{ __('جرّب كلمات بحث أخرى أو أزل بعض الفلاتر.') }}</p>
                <a class="btn btn--primary" href="{{ route('courses.index') }}">{{ __('عرض كل الدورات') }}</a>
            </div>
        @else
            <div class="grid grid--4">@foreach ($courses as $course) @include('partials.course-card') @endforeach</div>
            {{ $courses->links() }}
        @endif
    </div>
</div>
@endsection
