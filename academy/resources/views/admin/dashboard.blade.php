@extends('layouts.admin')
@section('title', 'الرئيسية')
@section('content')
@php
    $u = auth()->user();
    $kpiCards = [
        ['k' => 'enrollments', 'label' => 'تسجيلات جديدة', 'icon' => 'book', 'total' => 'إجمالي التسجيلات: '.number_format($courses['enrollments'])],
        ['k' => 'students', 'label' => 'طلاب جدد', 'icon' => 'users', 'total' => isset($totals['students']) ? 'إجمالي الطلاب: '.number_format($totals['students']) : null],
        ['k' => 'lessons_done', 'label' => 'دروس أُكملت', 'icon' => 'check-circle', 'total' => null],
        ['k' => 'completions', 'label' => 'دورات أُتمّت', 'icon' => 'award', 'total' => 'معدل الإتمام: '.$completionRate.'%'],
        ['k' => 'certificates', 'label' => 'طلبات شهادات', 'icon' => 'truck', 'total' => isset($totals['certificates']) ? 'إجمالي الطلبات: '.number_format($totals['certificates']) : null],
    ];
@endphp
<div class="dash-hero">
    <div>
        <p class="muted small" style="margin:0">{{ now()->translatedFormat('l j F Y') }}</p>
        <h1 style="margin:2px 0 0">مرحبًا، {{ $u->name }} 👋</h1>
        <p class="muted" style="margin:4px 0 0">{{ $u->isAdmin() ? 'نظرة عامة على الأكاديمية خلال آخر 30 يومًا.' : 'نظرة عامة على دوراتك خلال آخر 30 يومًا.' }}</p>
    </div>
    <div class="row">
        @if ($u->isAdmin())
            <a class="btn btn--primary" href="{{ route('admin.courses.create') }}"><x-icon name="plus" size="18"/> دورة جديدة</a>
            <a class="btn btn--outline" href="{{ route('admin.builder.edit', 'home') }}"><x-icon name="layers" size="18"/> تصميم الرئيسية</a>
            <a class="btn btn--ghost" href="{{ route('admin.posts.create') }}"><x-icon name="file-text" size="18"/> مقال</a>
        @else
            <a class="btn btn--primary" href="{{ route('admin.courses.index') }}"><x-icon name="book" size="18"/> دوراتي</a>
        @endif
    </div>
</div>

<div class="kpis kpis--rich">
    @foreach ($kpiCards as $card)
        @continue(! isset($kpis[$card['k']]))
        @php $t = $kpis[$card['k']]; @endphp
        <div class="kpi">
            <div class="row row--between"><span class="kpi__icon"><x-icon :name="$card['icon']" size="18"/></span>
                @if ($t['delta'] !== null)
                    <span class="kpi__delta {{ $t['delta'] >= 0 ? 'is-up' : 'is-down' }}" title="مقارنة بالـ30 يومًا السابقة ({{ $t['previous'] }})">{{ $t['delta'] >= 0 ? '▲' : '▼' }} {{ abs($t['delta']) }}%</span>
                @else
                    <span class="kpi__delta" title="لا توجد بيانات للفترة السابقة">جديد</span>
                @endif
            </div>
            <strong>{{ number_format($t['value']) }}</strong>
            <span>{{ $card['label'] }} <small class="muted">· 30 يومًا</small></span>
            @if ($card['total'])<small class="kpi__foot">{{ $card['total'] }}</small>@endif
        </div>
    @endforeach
</div>

@if ($todo && collect($todo)->sum('n') > 0)
    <div class="todo">
        @foreach ($todo as $item)
            @continue($item['n'] === 0)
            <a class="todo__item" href="{{ $item['url'] }}"><x-icon :name="$item['icon']" size="18"/> <strong>{{ $item['n'] }}</strong> {{ $item['label'] }}</a>
        @endforeach
    </div>
@endif

<div class="dash-cols">
    <section class="card card--pad">
        <div class="row row--between"><h2 class="card__title" style="margin:0">النشاط اليومي</h2><span class="muted small">آخر 30 يومًا</span></div>
        <x-admin.line-chart :days="$days" :series="$series" title="النشاط اليومي خلال آخر 30 يومًا"/>
    </section>
    <section class="card card--pad">
        <h2 class="card__title">المحتوى</h2>
        <div class="minis">
            <div><strong>{{ $courses['published'] }}<small>/{{ $courses['total'] }}</small></strong><span>دورات منشورة</span></div>
            <div><strong>{{ $courses['lessons'] }}</strong><span>درس منشور</span></div>
            <div><strong>{{ $courses['hours'] }}</strong><span>ساعة فيديو</span></div>
            <div><strong>{{ $completionRate }}%</strong><span>معدل الإتمام</span></div>
        </div>
        <div class="progress" style="margin-top:14px" role="progressbar" aria-valuenow="{{ $completionRate }}" aria-valuemin="0" aria-valuemax="100" aria-label="معدل إتمام الدورات"><div class="progress__bar" style="width:{{ $completionRate }}%"></div></div>
        <p class="small muted" style="margin:6px 0 0">نسبة التسجيلات التي أكمل أصحابها كل الدروس المطلوبة.</p>
    </section>
</div>

<div class="dash-cols dash-cols--even">
    <section class="card card--pad">
        <div class="row row--between"><h2 class="card__title" style="margin:0">الدورات الأكثر تسجيلًا</h2><a class="small" href="{{ route('admin.courses.index') }}">كل الدورات</a></div>
        <x-admin.bar-chart :rows="$topCourses" unit="تسجيل" empty="لا توجد تسجيلات بعد."/>
    </section>
    @if ($u->isAdmin())
        <section class="card card--pad">
            <div class="row row--between"><h2 class="card__title" style="margin:0">طلبات الشهادات حسب الحالة</h2><a class="small" href="{{ route('admin.certificate-requests.index') }}">كل الطلبات</a></div>
            <x-admin.bar-chart :rows="$certStatus" unit="طلب" empty="لا توجد طلبات شهادات بعد."/>
        </section>
    @endif
</div>

<div class="dash-cols dash-cols--even">
    <section class="card card--pad">
        <h2 class="card__title">آخر النشاطات</h2>
        <ul class="feed">
            @forelse ($activity as $a)
                <li><span class="feed__icon"><x-icon :name="$a['icon']" size="16"/></span>
                    <span class="feed__text">@if ($a['url'])<a href="{{ $a['url'] }}">{{ $a['text'] }}</a>@else{{ $a['text'] }}@endif</span>
                    <time class="muted small" datetime="{{ $a['at']->toIso8601String() }}">{{ $a['at']->diffForHumans() }}</time></li>
            @empty
                <li class="muted">لا توجد نشاطات بعد.</li>
            @endforelse
        </ul>
    </section>
    @if ($health)
        <section class="card card--pad">
            <h2 class="card__title">صحة الموقع</h2>
            <ul class="health">
                @foreach ($health as $h)
                    <li class="{{ $h['ok'] ? 'is-ok' : (! empty($h['optional']) ? 'is-optional' : 'is-warn') }}">
                        <span class="health__dot" aria-hidden="true">{{ ! empty($h['info']) ? 'ℹ' : ($h['ok'] ? '✓' : (! empty($h['optional']) ? '○' : '!')) }}</span>
                        <span><span class="sr-only">{{ $h['ok'] ? 'سليم:' : 'يحتاج انتباه:' }}</span>{{ $h['label'] }}
                            @if (! $h['ok'] && ! empty($h['hint']))<br><small class="muted">{{ $h['hint'] }}</small>@endif</span>
                        @if (! $h['ok'] && ! empty($h['url']))<a class="btn btn--ghost btn--sm" href="{{ $h['url'] }}">إصلاح</a>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
