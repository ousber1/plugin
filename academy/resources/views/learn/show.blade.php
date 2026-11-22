@extends('layouts.app')
@section('title', $lesson->tr('title').' — '.$course->tr('title'))
@section('noindex', '1')
@section('hide_footer', '1')

@section('content')
@php $isDone = in_array($lesson->id, $completed); @endphp
<div class="player-layout">
    <div class="player-main">
        <ol class="breadcrumbs" style="color:var(--muted)">
            <li><a href="{{ route('courses.show', $course) }}" style="color:var(--indigo-700)">{{ $course->tr('title') }}</a></li>
            <li>{{ $lesson->section->tr('title') }}</li>
        </ol>
        @if (! $lesson->isPublished() || ! $course->isPublished())
            <div class="alert alert--warning"><x-icon name="eye"/> {{ __('معاينة للمشرفين: هذا الدرس أو الدورة غير منشور.') }}</div>
        @endif
        @if (! $enrolled && $lesson->is_preview && ! $canManage)
            <div class="alert alert--info"><x-icon name="unlock"/> <span>{{ __('هذا درس معاينة مجاني. سجّل في الدورة مجانًا لمتابعة باقي الدروس وحفظ تقدّمك.') }}</span></div>
        @endif

        <div class="row row--between" style="margin-bottom:14px">
            <h1 style="font-size:1.5rem;margin:0" dir="auto">{{ $lesson->tr('title') }}</h1>
            <span class="badge">{{ $lesson->typeLabel() }}</span>
        </div>

        @if ($lesson->type === 'video' || $lesson->hasVideo())
            @if ($lesson->hasVideo())
                <div class="video-frame">
                    <video controls preload="metadata" playsinline controlsList="nodownload" data-resume-key="{{ $lesson->id }}" oncontextmenu="return false" aria-label="{{ __('فيديو الدرس: :t', ['t' => $lesson->tr('title')]) }}">
                        <source src="{{ route('media.video', $lesson) }}" type="video/mp4">
                        {{ __('متصفحك لا يدعم تشغيل الفيديو.') }}
                    </video>
                </div>
                <p id="video-error" class="alert alert--error" hidden style="margin-top:12px"><x-icon name="alert"/> {{ __('تعذّر تشغيل الفيديو. تحقّق من اتصالك ثم أعد تحميل الصفحة.') }}</p>
            @else
                <div class="card empty"><span class="empty__icon"><x-icon name="play" size="28"/></span><p>{{ __('سيُضاف فيديو هذا الدرس قريبًا.') }}</p></div>
            @endif
        @endif

        @if ($lesson->tr('body'))
            <div class="card card--pad prose" style="margin-top:20px" dir="auto">{!! $lesson->tr('body') !!}</div>
        @endif

        @if ($lesson->type === 'quiz')
            <section style="margin-top:20px" aria-labelledby="quiz-h">
                <h2 id="quiz-h" style="font-size:1.2rem">{{ __('الاختبار') }}</h2>
                @if (! $lesson->quiz || $lesson->quiz->questions->isEmpty())
                    <p class="muted">{{ __('لم تُضف أسئلة لهذا الاختبار بعد.') }}</p>
                @elseif (! $enrolled)
                    <div class="alert alert--info"><x-icon name="lock"/> {{ __('سجّل في الدورة لأداء الاختبار.') }}</div>
                @else
                    @php $result = $quizState['last'] ?? null; $best = $quizState['best'] ?? null; @endphp
                    <p class="muted small">{{ __('نسبة النجاح المطلوبة: :p%', ['p' => $lesson->quiz->pass_score]) }}
                        @if ($best) — {{ __('أفضل نتيجة لك: :s%', ['s' => $best->score]) }} @if ($best->passed)<span class="badge badge--green">{{ __('ناجح') }}</span>@endif @endif</p>
                    @if ($result)
                        <div class="alert {{ $result['passed'] ? 'alert--success' : 'alert--error' }}" role="status">
                            <x-icon :name="$result['passed'] ? 'check-circle' : 'alert'"/> <span>{{ __('نتيجتك: :s%', ['s' => $result['score']]) }}</span>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('learn.quiz', [$course, $lesson]) }}">
                        @csrf
                        @foreach ($lesson->quiz->questions as $n => $q)
                            @php $rv = $result['review'][$q->id] ?? null; @endphp
                            <fieldset class="quiz-q">
                                <legend dir="auto">{{ $n + 1 }}. {{ $q->question }} @if ($q->isMultiple())<span class="muted small">({{ __('اختر كل الإجابات الصحيحة') }})</span>@endif</legend>
                                @foreach ($q->options as $i => $opt)
                                    @php
                                        $chosen = $rv && in_array($i, $rv['given']);
                                        $cls = $rv && $chosen ? ($rv['ok'] ? 'is-correct' : 'is-wrong') : '';
                                    @endphp
                                    <label class="quiz-opt {{ $cls }}">
                                        <input type="{{ $q->isMultiple() ? 'checkbox' : 'radio' }}" name="answers[{{ $q->id }}][]" value="{{ $i }}" @checked($chosen) @if (! $q->isMultiple()) required @endif>
                                        <span dir="auto">{{ $opt }}</span>
                                    </label>
                                @endforeach
                                @if ($rv && $q->explanation)<p class="small muted" style="margin:6px 0 0" dir="auto"><x-icon name="info" size="16"/> {{ $q->explanation }}</p>@endif
                            </fieldset>
                        @endforeach
                        <button class="btn btn--primary btn--lg" type="submit">{{ $best ? __('أعد المحاولة') : __('إرسال الإجابات') }}</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($lesson->attachments->isNotEmpty())
            <section class="card card--pad" style="margin-top:20px">
                <h2 style="font-size:1.1rem">{{ __('ملفات الدرس') }}</h2>
                <ul class="list-plain">
                    @foreach ($lesson->attachments as $file)
                        <li class="row row--between">
                            <span class="row"><x-icon name="file-text"/> <span dir="auto">{{ $file->title }}</span> <span class="muted small">({{ number_format($file->size / 1048576, 1) }} MB)</span></span>
                            <a class="btn btn--outline btn--sm" href="{{ route('media.attachment', $file) }}"><x-icon name="download" size="16"/> {{ __('تحميل') }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($enrolled)
            <div class="row" style="margin-top:20px">
                @if ($lesson->type !== 'quiz' || ! $lesson->quiz?->questions->count())
                    @if ($isDone)
                        <span class="badge badge--green" style="padding:10px 16px;font-size:.95rem"><x-icon name="check-circle" size="18"/> {{ __('درس مكتمل') }}</span>
                    @else
                        <form method="POST" action="{{ route('learn.complete', [$course, $lesson]) }}">@csrf
                            <button class="btn btn--amber btn--lg" type="submit"><x-icon name="check"/> {{ __('إكمال الدرس') }}</button>
                        </form>
                        <p id="complete-hint" class="small muted" hidden style="margin:0">{{ __('انتهى الفيديو — اضغط «إكمال الدرس» للمتابعة.') }}</p>
                    @endif
                @endif
                <form method="POST" action="{{ route('learn.bookmark', [$course, $lesson]) }}">@csrf
                    <button class="btn btn--outline" type="submit" aria-pressed="{{ $bookmarked ? 'true' : 'false' }}"><x-icon name="bookmark" size="18" style="{{ $bookmarked ? 'fill:currentColor' : '' }}"/> {{ $bookmarked ? __('محفوظ') : __('احفظ الدرس') }}</button>
                </form>
            </div>
        @elseif (! $canManage)
            <form method="POST" action="{{ route('courses.enroll', $course) }}" style="margin-top:20px">@csrf
                @auth<button class="btn btn--amber btn--lg" type="submit">{{ __('سجّل مجانًا') }}</button>
                @else<a class="btn btn--amber btn--lg" href="{{ route('register', ['intended' => route('courses.show', $course)]) }}">{{ __('سجّل مجانًا') }}</a>@endauth
            </form>
        @endif

        <nav class="player-nav" aria-label="{{ __('التنقل بين الدروس') }}">
            @if ($prev)<a class="btn btn--outline" href="{{ route('learn.show', [$course, $prev]) }}" rel="prev"><x-icon name="chevron-start" size="18"/> {{ __('الدرس السابق') }}</a>@else<span></span>@endif
            @if ($next)<a class="btn btn--primary" href="{{ route('learn.show', [$course, $next]) }}" rel="next">{{ __('الدرس التالي') }} <x-icon name="chevron-end" size="18"/></a>
            @else<a class="btn btn--primary" href="{{ route('courses.show', $course) }}#certificate">{{ __('العودة إلى صفحة الدورة') }}</a>@endif
        </nav>
    </div>

    <aside class="player-sidebar" aria-label="{{ __('محتوى الدورة') }}">
        <div class="player-sidebar__head">
            <a href="{{ route('courses.show', $course) }}" style="font-weight:800;color:var(--indigo-950)" dir="auto">{{ $course->tr('title') }}</a>
            @if ($progress)
                <div class="row row--between small" style="margin-top:10px"><span>{{ __(':done من :total دروس', ['done' => $progress['completed'], 'total' => $progress['total']]) }}</span><strong>{{ $progress['percent'] }}%</strong></div>
                <div class="progress" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('تقدّمك') }}"><div class="progress__bar" style="width:{{ $progress['percent'] }}%"></div></div>
            @endif
        </div>
        @foreach ($sections as $section)
            @continue($section->lessons->isEmpty())
            <div class="side-section" dir="auto">{{ $section->tr('title') }}</div>
            @foreach ($section->lessons as $l)
                @php
                    $done = in_array($l->id, $completed);
                    $current = $l->id === $lesson->id;
                    $open = $enrolled || $l->is_preview || $canManage;
                @endphp
                @if ($open)
                    <a class="side-lesson {{ $current ? 'is-current' : '' }} {{ $done ? 'is-done' : '' }}" href="{{ route('learn.show', [$course, $l]) }}" @if ($current) aria-current="page" @endif>
                        <x-icon :name="$done ? 'check-circle' : $l->typeIcon()" size="18"/> <span style="flex:1" dir="auto">{{ $l->tr('title') }}</span>
                        @if ($l->durationLabel())<span class="muted small ltr">{{ $l->durationLabel() }}</span>@endif
                        @if ($done)<span class="sr-only">({{ __('مكتمل') }})</span>@endif
                    </a>
                @else
                    <span class="side-lesson is-locked"><x-icon name="lock" size="18"/> <span style="flex:1" dir="auto">{{ $l->tr('title') }}</span><span class="sr-only">({{ __('يتطلب التسجيل') }})</span></span>
                @endif
            @endforeach
        @endforeach
    </aside>
</div>
@endsection
