@extends('layouts.app')
@section('title', __('لوحة التعلم'))
@section('noindex', '1')

@section('content')
<section class="page-hero">
    <div class="container">
        <h1>{{ __('مرحبًا، :name', ['name' => auth()->user()->name]) }}</h1>
        <p>{{ __('تابع دوراتك، وشاهد تقدّمك، واطلب شهاداتك الورقية من هنا.') }}</p>
    </div>
</section>
<div class="container section--tight">
    @unless (auth()->user()->hasVerifiedEmail())
        <div class="alert alert--warning overlap" role="status">
            <x-icon name="mail"/>
            <div>{{ __('لم تؤكّد بريدك الإلكتروني بعد. التأكيد ضروري لطلب الشهادة الورقية.') }}
                <form method="POST" action="{{ route('verification.send') }}" style="display:inline">@csrf <button class="btn btn--sm btn--outline" type="submit">{{ __('أعد إرسال الرابط') }}</button></form>
            </div>
        </div>
    @endunless

    <div class="dash-grid">
        <div class="stack">
            <h2 style="font-size:1.3rem">{{ __('دوراتي') }}</h2>
            @forelse ($rows as $row)
                @php $c = $row['course']; $p = $row['progress']; $e = $row['enrollment']; @endphp
                <article class="card enrolled">
                    <a href="{{ route('courses.show', $c) }}" tabindex="-1" aria-hidden="true"><img src="{{ $c->thumbnailUrl() }}" alt="" width="280" height="158" loading="lazy"></a>
                    <div class="stack" style="--gap:8px">
                        <div class="row row--between">
                            <h3 style="margin:0;font-size:1.05rem"><a href="{{ route('courses.show', $c) }}" dir="auto">{{ $c->tr('title') }}</a></h3>
                            @if ($e->completed_at)<span class="badge badge--green">{{ __('مكتملة') }}</span>@endif
                        </div>
                        <div>
                            <div class="row row--between small"><span>{{ __(':done من :total دروس', ['done' => $p['completed'], 'total' => $p['total']]) }}</span><strong>{{ $p['percent'] }}%</strong></div>
                            <div class="progress" role="progressbar" aria-valuenow="{{ $p['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('تقدّم :c', ['c' => $c->tr('title')]) }}"><div class="progress__bar" style="width:{{ $p['percent'] }}%"></div></div>
                        </div>
                        @if ($e->lastLesson)<p class="small muted" style="margin:0">{{ __('آخر درس:') }} <span dir="auto">{{ $e->lastLesson->tr('title') }}</span></p>@endif
                        <div class="row">
                            <a class="btn btn--primary btn--sm" href="{{ $e->lastLesson ? route('learn.show', [$c, $e->lastLesson]) : route('learn.show', $c) }}">{{ __('تابع التعلم') }}</a>
                            @if ($row['eligibility']['eligible'] && ! $requests->firstWhere('course_id', $c->id))
                                <form method="POST" action="{{ route('certificates.request', $c) }}" target="_blank">@csrf
                                    <button class="btn btn--whatsapp btn--sm" type="submit"><x-icon name="whatsapp" size="16"/> {{ __('اطلب شهادتك عبر واتساب') }}</button>
                                </form>
                            @elseif (! $row['eligibility']['eligible'] && $p['percent'] === 100)
                                <span class="small muted">{{ implode(' ', $row['eligibility']['missing']) }}</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="card empty">
                    <span class="empty__icon"><x-icon name="book" size="28"/></span>
                    <h3>{{ __('لم تسجّل في أي دورة بعد') }}</h3>
                    <p>{{ __('كل الدورات مجانية — اختر دورتك الأولى الآن.') }}</p>
                    <a class="btn btn--primary" href="{{ route('courses.index') }}">{{ __('تصفّح الدورات') }}</a>
                </div>
            @endforelse

            @if ($wishlist->isNotEmpty())
                <h2 style="font-size:1.3rem;margin-top:28px">{{ __('قائمتي المفضلة') }}</h2>
                <div class="grid grid--3">@foreach ($wishlist as $course) @include('partials.course-card') @endforeach</div>
            @endif
        </div>

        <div class="stack">
            <section class="card card--pad" aria-labelledby="req-h">
                <h2 id="req-h" class="card__title row" style="gap:8px"><x-icon name="award"/> {{ __('طلبات الشهادات') }}</h2>
                @forelse ($requests as $r)
                    <div style="padding:12px 0;border-bottom:1px solid var(--border)">
                        <strong class="small" dir="auto">{{ $r->course?->tr('title') }}</strong>
                        <div class="row row--between small" style="margin-top:4px">
                            <span class="ltr muted">{{ $r->reference }}</span>
                            <span class="badge badge--{{ $r->status->color() }}">{{ $r->status->label() }}</span>
                        </div>
                        @if ($t = $r->visibleTracking())
                            <p class="small" style="margin:6px 0 0"><x-icon name="truck" size="14"/> {{ $t['carrier'] }} @if ($t['number'])— <span class="ltr">{{ $t['number'] }}</span>@endif</p>
                        @endif
                        @if ($r->status === \App\Enums\CertificateStatus::PendingContact && $r->course)
                            <form method="POST" action="{{ route('certificates.request', $r->course) }}" target="_blank" style="margin-top:6px">@csrf
                                <button class="btn btn--sm btn--whatsapp" type="submit"><x-icon name="whatsapp" size="14"/> {{ __('أرسل الطلب عبر واتساب') }}</button></form>
                        @endif
                    </div>
                @empty
                    <p class="small muted" style="margin:0">{{ __('أكمل دورة لطلب شهادتك الورقية.') }}</p>
                @endforelse
            </section>

            <section class="card card--pad" aria-labelledby="bm-h">
                <h2 id="bm-h" class="card__title row" style="gap:8px"><x-icon name="bookmark"/> {{ __('الإشارات المرجعية') }}</h2>
                @forelse ($bookmarks as $b)
                    <a class="side-lesson" style="padding-inline:0" href="{{ route('learn.show', [$b->lesson->course, $b->lesson]) }}"><x-icon :name="$b->lesson->typeIcon()" size="16"/> <span dir="auto">{{ $b->lesson->tr('title') }}</span></a>
                @empty
                    <p class="small muted" style="margin:0">{{ __('احفظ الدروس المهمة للرجوع إليها بسرعة.') }}</p>
                @endforelse
            </section>

            @if ($orders->isNotEmpty())
                <section class="card card--pad">
                    <h2 class="card__title row" style="gap:8px"><x-icon name="store"/> {{ __('طلبات المتجر') }}</h2>
                    @foreach ($orders as $o)
                        <a class="row row--between small" style="padding:8px 0" href="{{ route('store.order', $o) }}"><span dir="auto">{{ $o->product->tr('title') }}</span><span class="badge">{{ $o->statusLabel() }}</span></a>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
