@extends('layouts.app')
@php
    $title = $course->tr('title');
    $minutes = intdiv($course->durationSeconds(), 60);
    $rating = $course->approved_reviews_count ? $course->averageRating() : null;
    $resume = $state['resumeLesson'];
    $outcomes = $course->trList('outcomes');
    $prereq = $course->trList('prerequisites');
    $audience = $course->trList('audience');
    $durationLabel = $minutes >= 60 ? __(':h س :m د', ['h' => intdiv($minutes, 60), 'm' => $minutes % 60]) : ($minutes ? __(':m دقيقة', ['m' => $minutes]) : null);
@endphp
@section('title', $course->tr('seo_title') ?: $title)
@section('description', $course->tr('seo_description') ?: ($course->tr('subtitle') ?: \App\Support\Html::excerpt($course->tr('description'))))
@section('canonical', route('courses.show', $course))
@section('og_image', $course->thumbnail_path ? $course->thumbnailUrl() : asset('images/og-default.png'))
@section('body_class', 'has-mobile-cta')
@unless ($course->isPublished())
    @section('noindex', '1')
@endunless

@php
    $mainAction = function ($extra = '') use ($course, $state, $resume) {
        if ($state['enrolled']) {
            return '<a class="btn btn--amber btn--block btn--lg '.$extra.'" href="'.e($resume ? route('learn.show', [$course, $resume]) : route('learn.show', $course)).'">'.e(__('تابع التعلم')).'</a>';
        }
        if (auth()->check()) {
            return '<form method="POST" action="'.e(route('courses.enroll', $course)).'" style="flex:1">'.csrf_field().'<button class="btn btn--amber btn--block btn--lg '.$extra.'" type="submit">'.e(__('سجّل مجانًا')).'</button></form>';
        }

        return '<a class="btn btn--amber btn--block btn--lg '.$extra.'" href="'.e(route('register', ['intended' => route('courses.show', $course)])).'">'.e(__('سجّل مجانًا')).'</a>';
    };
@endphp

@section('content')
@unless ($course->isPublished())
    <div class="alert alert--warning" style="border-radius:0;margin:0"><x-icon name="eye"/> {{ __('معاينة: هذه الدورة غير منشورة ولا يراها الزوار.') }}</div>
@endunless
<section class="course-hero">
    <div class="container">
        <div class="course-layout">
            <div>
                <ol class="breadcrumbs">
                    <li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li>
                    <li><a href="{{ route('courses.index') }}">{{ __('الدورات') }}</a></li>
                    @if ($course->category)<li><a href="{{ route('courses.index', ['category' => $course->category->slug]) }}">{{ $course->category->tr('name') }}</a></li>@endif
                </ol>
                <div class="row" style="margin-bottom:12px">
                    <span class="badge badge--free">{{ __('مجانية') }}</span>
                    @if ($course->category)<span class="badge badge--indigo">{{ $course->category->tr('name') }}</span>@endif
                    <span class="badge badge--gray"><x-icon name="level" size="14"/> {{ $course->level->label() }}</span>
                    @if ($rating)<span class="badge badge--amber"><x-icon name="star" size="14"/> {{ $rating }} ({{ $course->approved_reviews_count }})</span>@endif
                </div>
                <h1 dir="auto">{{ $title }}</h1>
                @if ($course->tr('subtitle'))<p dir="auto" style="font-size:1.08rem">{{ $course->tr('subtitle') }}</p>@endif
                <div class="row small" style="color:#dcd7ff;gap:18px">
                    <span class="row" style="gap:6px"><x-icon name="book" size="16"/> {{ __(':count درس', ['count' => $course->lessonCount()]) }}</span>
                    @if ($durationLabel)<span class="row" style="gap:6px"><x-icon name="clock" size="16"/> {{ $durationLabel }}</span>@endif
                    <span class="row" style="gap:6px"><x-icon name="users" size="16"/> {{ __(':count طالب', ['count' => $course->enrollments_count]) }}</span>
                    <span class="row" style="gap:6px"><x-icon name="calendar" size="16"/> {{ __('آخر تحديث :date', ['date' => $course->updated_at->translatedFormat('j F Y')]) }}</span>
                </div>
                <div class="row row--between" style="margin-top:22px;padding-top:18px;border-top:1px solid rgba(255,255,255,.15)">
                    @if ($course->instructor)
                        <div class="row">
                            @if ($course->instructor->avatarUrl())<img class="avatar" src="{{ $course->instructor->avatarUrl() }}" alt="" width="44" height="44">@else<span class="avatar">{{ $course->instructor->initials() }}</span>@endif
                            <span class="small"><span style="color:#cfc9ff">{{ __('المدرّس') }}</span><br><strong>{{ $course->instructor->name }}</strong></span>
                        </div>
                    @endif
                    <div class="row">
                        @auth
                            <form method="POST" action="{{ route('courses.wishlist', $course) }}">@csrf
                                <button class="btn btn--glass btn--sm" type="submit" aria-pressed="{{ $state['wishlisted'] ? 'true' : 'false' }}"><x-icon name="heart" size="16" style="{{ $state['wishlisted'] ? 'fill:#fb923c;stroke:#fb923c' : '' }}"/> {{ $state['wishlisted'] ? __('في المفضلة') : __('أضف للمفضلة') }}</button>
                            </form>
                        @else
                            <a class="btn btn--glass btn--sm" href="{{ route('login', ['intended' => route('courses.show', $course)]) }}"><x-icon name="heart" size="16"/> {{ __('أضف للمفضلة') }}</a>
                        @endauth
                        <button class="btn btn--glass btn--sm" type="button" data-share="{{ route('courses.show', $course) }}" data-title="{{ $title }}" data-copied="{{ __('تم نسخ الرابط') }}"><x-icon name="share" size="16"/> {{ __('مشاركة') }}</button>
                    </div>
                </div>
            </div>
            <div aria-hidden="true"></div>
        </div>
    </div>
</section>

<div class="container">
    <div class="course-layout">
        <div>
            <div class="quick-facts">
                <div class="quick-fact"><span class="icon-wrap"><x-icon name="book"/></span><div><strong>{{ $course->lessonCount() }}</strong><span>{{ __('درس') }}</span></div></div>
                @if ($durationLabel)<div class="quick-fact"><span class="icon-wrap"><x-icon name="clock"/></span><div><strong>{{ $durationLabel }}</strong><span>{{ __('مدة الفيديو') }}</span></div></div>@endif
                <div class="quick-fact"><span class="icon-wrap"><x-icon name="level"/></span><div><strong>{{ $course->level->label() }}</strong><span>{{ __('المستوى') }}</span></div></div>
                <div class="quick-fact"><span class="icon-wrap"><x-icon name="award"/></span><div><strong>{{ __('ورقية') }}</strong><span>{{ __('الشهادة') }}</span></div></div>
            </div>

            <nav class="tabs" aria-label="{{ __('أقسام الصفحة') }}">
                <a href="#about" class="is-active">{{ __('عن الدورة') }}</a>
                <a href="#curriculum">{{ __('محتوى الدورة') }}</a>
                <a href="#reviews">{{ __('التقييمات') }}</a>
            </nav>

            <section id="about" class="section--tight" style="padding-top:0">
                <h2>{{ __('عن الدورة') }}</h2>
                <div class="prose" dir="auto">{!! $course->tr('description') !!}</div>
            </section>

            @if ($outcomes)
                <section class="section--tight">
                    <h2>{{ __('ماذا سوف تتعلم؟') }}</h2>
                    <ul class="checklist">@foreach ($outcomes as $o)<li><x-icon name="check-circle" size="18"/> <span dir="auto">{{ $o }}</span></li>@endforeach</ul>
                </section>
            @endif

            <section id="curriculum" class="section--tight">
                <div class="row row--between"><h2>{{ __('محتوى الدورة') }}</h2>
                    <span class="muted small">{{ __(':s أقسام • :l درس', ['s' => $course->sections->filter(fn ($s) => $s->lessons->isNotEmpty())->count(), 'l' => $course->lessonCount()]) }}</span></div>
                <div class="curriculum">
                    @foreach ($course->sections as $i => $section)
                        @continue($section->lessons->isEmpty())
                        <details @if ($i === 0) open @endif>
                            <summary><span dir="auto">{{ $section->tr('title') }}</span> <span class="row small muted" style="gap:8px">{{ __(':count درس', ['count' => $section->lessons->count()]) }} <x-icon name="chevron-down" class="chev" size="18"/></span></summary>
                            @foreach ($section->lessons as $lesson)
                                @php $done = in_array($lesson->id, $state['completed']); $open = $state['enrolled'] || $lesson->is_preview; @endphp
                                <div class="lesson-row {{ $done ? 'is-done' : '' }}">
                                    <x-icon :name="$done ? 'check-circle' : $lesson->typeIcon()" class="state" size="18"/>
                                    <span class="lesson-row__title" dir="auto">
                                        @if ($open)<a href="{{ route('learn.show', [$course, $lesson]) }}">{{ $lesson->tr('title') }}</a>@else{{ $lesson->tr('title') }}@endif
                                        <span class="sr-only">— {{ $lesson->typeLabel() }}</span>
                                    </span>
                                    @if ($lesson->is_preview && ! $state['enrolled'])<span class="badge badge--green">{{ __('معاينة مجانية') }}</span>@endif
                                    @if ($lesson->durationLabel())<span class="muted small ltr">{{ $lesson->durationLabel() }}</span>@endif
                                    @unless ($open)<x-icon name="lock" size="16" aria-label="{{ __('يتطلب التسجيل') }}"/>@endunless
                                </div>
                            @endforeach
                        </details>
                    @endforeach
                </div>
            </section>

            <section class="section--tight grid grid--3">
                @if ($prereq)
                    <div class="card card--pad"><h3 class="card__title">{{ __('المتطلبات') }}</h3><ul class="list-plain small">@foreach ($prereq as $p)<li dir="auto">{{ $p }}</li>@endforeach</ul></div>
                @endif
                @if ($audience)
                    <div class="card card--pad"><h3 class="card__title">{{ __('لمن هذه الدورة؟') }}</h3><ul class="list-plain small">@foreach ($audience as $a)<li dir="auto">{{ $a }}</li>@endforeach</ul></div>
                @endif
                @if ($course->instructor)
                    <div class="card card--pad"><h3 class="card__title">{{ __('دورة بواسطة') }}</h3>
                        <div class="row">@if ($course->instructor->avatarUrl())<img class="avatar" src="{{ $course->instructor->avatarUrl() }}" alt="" width="44" height="44">@else<span class="avatar">{{ $course->instructor->initials() }}</span>@endif
                            <span><strong>{{ $course->instructor->name }}</strong>@if ($course->instructor->headline)<br><span class="muted small">{{ $course->instructor->headline }}</span>@endif</span></div>
                        @if ($course->instructor->bio)<p class="small muted" style="margin-top:10px" dir="auto">{{ $course->instructor->bio }}</p>@endif
                    </div>
                @endif
            </section>

            <section id="reviews" class="section--tight">
                <h2>{{ __('التقييمات') }}</h2>
                @if ($reviews->isEmpty())
                    <p class="muted">{{ __('لا توجد تقييمات معتمدة بعد.') }}</p>
                @else
                    <p class="rating" style="font-size:1.1rem"><x-icon name="star" size="20"/> {{ $rating }} / 5 <span class="muted small">— {{ __(':count تقييم', ['count' => $course->approved_reviews_count]) }}</span></p>
                    <div class="card card--pad">
                        @foreach ($reviews as $r)
                            <div class="review">
                                <div class="row row--between"><strong>{{ $r->user->name }}</strong>
                                    <span class="stars" aria-label="{{ __('التقييم :r من 5', ['r' => $r->rating]) }}">@for ($i = 1; $i <= 5; $i++)<x-icon name="star" size="14" class="{{ $i <= $r->rating ? 'on' : '' }}"/>@endfor</span></div>
                                @if ($r->body)<p class="small" style="margin:6px 0 0" dir="auto">{{ $r->body }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($state['enrolled'])
                    @php $mine = $state['myReview']; @endphp
                    <form class="card card--pad" style="margin-top:16px" method="POST" action="{{ route('courses.review', $course) }}">
                        @csrf
                        <h3 class="card__title">{{ $mine ? __('عدّل مراجعتك') : __('شاركنا رأيك في الدورة') }}</h3>
                        @if ($mine && ! $mine->is_approved)<p class="small muted">{{ __('مراجعتك بانتظار الاعتماد.') }}</p>@endif
                        <fieldset class="field" style="border:0;padding:0;margin:0 0 12px">
                            <legend class="label">{{ __('التقييم') }} <span class="req">*</span></legend>
                            <div class="row stars" data-stars style="gap:2px">
                                @for ($i = 1; $i <= 5; $i++)
                                    <input class="sr-only" type="radio" id="rate{{ $i }}" name="rating" value="{{ $i }}" @checked(old('rating', $mine?->rating) == $i) required>
                                    <label for="rate{{ $i }}" data-v="{{ $i }}" style="cursor:pointer"><x-icon name="star" size="28"/><span class="sr-only">{{ __(':n من 5', ['n' => $i]) }}</span></label>
                                @endfor
                            </div>
                            @error('rating')<p class="error">{{ $message }}</p>@enderror
                        </fieldset>
                        <div class="field"><label for="review-body">{{ __('تعليقك (اختياري)') }}</label>
                            <textarea class="textarea" id="review-body" name="body" maxlength="2000" dir="auto">{{ old('body', $mine?->body) }}</textarea></div>
                        <button class="btn btn--primary" type="submit">{{ __('إرسال المراجعة') }}</button>
                    </form>
                @endif
            </section>
        </div>

        <aside class="course-aside" aria-label="{{ __('التسجيل في الدورة') }}">
            <div class="card">
                <div class="course-aside__media"><img src="{{ $course->thumbnailUrl() }}" alt="{{ __('صورة دورة :title', ['title' => $title]) }}" width="640" height="360"></div>
                <div class="card--pad stack">
                    <div class="row row--between"><span class="badge badge--free" style="font-size:1rem">{{ __('مجانية') }}</span>
                        @if ($state['enrolled'])<span class="badge badge--green"><x-icon name="check" size="14"/> {{ __('أنت مسجّل') }}</span>@endif</div>
                    @if ($state['progress'])
                        <div>
                            <div class="row row--between small"><span>{{ __('تقدّمك') }}</span><strong>{{ $state['progress']['percent'] }}%</strong></div>
                            <div class="progress" role="progressbar" aria-valuenow="{{ $state['progress']['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('تقدّمك') }}"><div class="progress__bar" style="width:{{ $state['progress']['percent'] }}%"></div></div>
                        </div>
                    @endif
                    {!! $mainAction() !!}
                    @guest<p class="small muted" style="text-align:center;margin:0">{{ __('لديك حساب؟') }} <a href="{{ route('login', ['intended' => route('courses.show', $course)]) }}">{{ __('سجّل الدخول') }}</a></p>@endguest
                    <ul class="facts">
                        <li><x-icon name="level" size="18"/> {{ $course->level->label() }}</li>
                        <li><x-icon name="book" size="18"/> {{ __(':count درس', ['count' => $course->lessonCount()]) }}</li>
                        @if ($durationLabel)<li><x-icon name="clock" size="18"/> {{ $durationLabel }}</li>@endif
                        <li><x-icon name="globe" size="18"/> {{ \App\Support\Locales::all()[$course->language]['name'] ?? $course->language }}</li>
                        @if ($course->requires_quiz_pass)<li><x-icon name="check-square" size="18"/> {{ __('يتطلب النجاح في الاختبارات') }}</li>@endif
                    </ul>
                    @include('partials.certificate-box')
                </div>
            </div>
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="section">
            <div class="section__head"><div><span class="eyebrow">{{ __('اقتراحات') }}</span><h2>{{ __('دورات قد تهمك') }}</h2></div>
                <a class="btn btn--outline" href="{{ route('courses.index') }}">{{ __('عرض الكل') }}</a></div>
            <div class="grid grid--3">@foreach ($related as $c) @include('partials.course-card', ['course' => $c]) @endforeach</div>
        </section>
    @endif
</div>

<div class="mobile-cta">
    <span class="badge badge--free">{{ __('مجانية') }}</span>
    {!! $mainAction() !!}
</div>
@endsection
