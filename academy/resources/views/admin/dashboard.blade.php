@extends('layouts.admin')
@section('title', 'الرئيسية')
@section('content')
<div class="page-head"><h1>مرحبًا، {{ auth()->user()->name }}</h1>
    @if (auth()->user()->isAdmin())<a class="btn btn--primary" href="{{ route('admin.courses.create') }}"><x-icon name="plus" size="18"/> دورة جديدة</a>@endif</div>
<div class="kpis">
    <div class="kpi"><strong>{{ $kpis['courses'] }}</strong><span>دورة ({{ $kpis['published'] }} منشورة)</span></div>
    <div class="kpi"><strong>{{ $kpis['enrollments'] }}</strong><span>تسجيل في الدورات</span></div>
    <div class="kpi"><strong>{{ $kpis['completions'] }}</strong><span>إتمام للدورات</span></div>
    <div class="kpi"><strong>{{ $activity }}</strong><span>درس أُكمل هذا الأسبوع</span></div>
    @isset($kpis['students'])<div class="kpi"><strong>{{ $kpis['students'] }}</strong><span>طالب</span></div>@endisset
    @isset($kpis['pending_certs'])<div class="kpi"><strong>{{ $kpis['pending_certs'] }}</strong><span><a href="{{ route('admin.certificate-requests.index', ['status' => 'pending_contact']) }}">طلب شهادة بانتظار التواصل</a></span></div>
    <div class="kpi"><strong>{{ $kpis['in_progress_certs'] }}</strong><span>شهادة قيد المعالجة</span></div>@endisset
</div>
<div class="grid grid--2">
    <section class="card card--pad"><h2 class="card__title">آخر التسجيلات</h2>
        <ul class="list-plain small">
            @forelse ($recentEnrollments as $e)
                <li class="row row--between"><span><strong>{{ $e->user?->name }}</strong> — {{ $e->course?->tr('title') }}</span><span class="muted">{{ $e->created_at->diffForHumans() }}</span></li>
            @empty<li class="muted">لا توجد تسجيلات بعد.</li>@endforelse
        </ul>
    </section>
    @if (auth()->user()->isAdmin())
    <section class="card card--pad"><h2 class="card__title">آخر طلبات الشهادات</h2>
        <ul class="list-plain small">
            @forelse ($recentRequests as $r)
                <li class="row row--between"><a href="{{ route('admin.certificate-requests.show', $r) }}"><span class="ltr">{{ $r->reference }}</span> — {{ $r->user?->name }}</a><span class="badge badge--{{ $r->status->color() }}">{{ $r->status->label() }}</span></li>
            @empty<li class="muted">لا توجد طلبات بعد.</li>@endforelse
        </ul>
    </section>
    @endif
</div>
@endsection
