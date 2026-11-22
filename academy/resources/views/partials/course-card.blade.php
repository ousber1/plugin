@php
    $minutes = intdiv($course->durationSeconds(), 60);
    $rating = $course->approved_reviews_count ? $course->averageRating() : null;
@endphp
<article class="card course-card">
    <div class="course-card__media">
        <img src="{{ $course->thumbnailUrl() }}" alt="{{ __('صورة دورة :title', ['title' => $course->tr('title')]) }}" loading="lazy" width="640" height="360">
        @if ($course->published_at && $course->published_at->gt(now()->subDays(30)))
            <span class="badge badge--amber course-card__flag">{{ __('جديد') }}</span>
        @endif
    </div>
    <div class="course-card__body">
        <div class="row row--between">
            @if ($course->category)<span class="badge">{{ $course->category->tr('name') }}</span>@endif
            @if ($rating)
                <span class="rating" aria-label="{{ __('التقييم :r من 5', ['r' => $rating]) }}"><x-icon name="star" size="16"/> {{ $rating }} <span class="muted small">({{ $course->approved_reviews_count }})</span></span>
            @endif
        </div>
        <h3 class="course-card__title"><a href="{{ route('courses.show', $course) }}" dir="auto">{{ $course->tr('title') }}</a></h3>
        <div class="course-card__meta">
            <span><x-icon name="book" size="16"/> {{ __(':count درس', ['count' => $course->lessonCount()]) }}</span>
            @if ($minutes)
                <span><x-icon name="clock" size="16"/> {{ $minutes >= 60 ? __(':h س :m د', ['h' => intdiv($minutes, 60), 'm' => $minutes % 60]) : __(':m دقيقة', ['m' => $minutes]) }}</span>
            @endif
            <span><x-icon name="level" size="16"/> {{ $course->level->label() }}</span>
        </div>
    </div>
    <div class="course-card__foot">
        <span class="badge badge--free">{{ __('مجانية') }}</span>
        <span class="btn btn--outline btn--sm" aria-hidden="true">{{ __('عرض الدورة') }}</span>
    </div>
</article>
