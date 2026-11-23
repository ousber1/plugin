@php
    $q = \App\Models\Course::published()->withCardData();
    if ($c['source'] === 'featured') { $q->where('is_featured', true)->orderBy('featured_order'); }
    elseif ($c['source'] === 'popular') { $q->withCount('enrollments')->orderByDesc('enrollments_count'); }
    elseif ($c['source'] === 'category') { $q->whereHas('category', fn ($w) => $w->where('slug', $c['category'])); }
    $list = $q->latest('published_at')->limit($c['count'])->get();
@endphp
@if ($list->isNotEmpty() || ($editing ?? false))
    <div class="section__head">
        <div>@if ($t('title'))<h2 dir="auto">{{ $t('title') }}</h2>@endif @if ($t('subtitle'))<p dir="auto">{{ $t('subtitle') }}</p>@endif</div>
        @if ($t('btn_label'))<a class="btn btn--outline" href="{{ route('courses.index', $c['source'] === 'category' && $c['category'] ? ['category' => $c['category']] : []) }}">{{ $t('btn_label') }}</a>@endif
    </div>
    @if ($list->isEmpty())<p class="muted">لا توجد دورات مطابقة حاليًا (تظهر هذه الرسالة في المعاينة فقط).</p>@endif
    <div class="grid grid--4">@foreach ($list as $course) @include('partials.course-card') @endforeach</div>
@endif
