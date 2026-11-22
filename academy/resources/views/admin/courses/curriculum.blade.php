@extends('layouts.admin')
@section('title', 'محتوى الدورة')
@section('content')
<div class="page-head"><h1 dir="auto">{{ $course->tr('title') }}</h1>
    <div class="row"><x-admin.status :status="$course->status"/>
        <form method="POST" action="{{ route('admin.courses.publish', $course) }}">@csrf<button class="btn {{ $course->status === 'published' ? 'btn--outline' : 'btn--amber' }}" type="submit">{{ $course->status === 'published' ? 'إلغاء النشر' : 'نشر الدورة' }}</button></form></div></div>
@include('admin.courses._nav')

@foreach ($course->sections as $section)
    <section class="curr-section" aria-labelledby="sec-{{ $section->id }}">
        <div class="curr-section__head">
            <strong id="sec-{{ $section->id }}" style="flex:1" dir="auto">{{ $loop->iteration }}. {{ $section->tr('title') }}</strong>
            <form class="inline-form" method="POST" action="{{ route('admin.sections.move', $section) }}">@csrf<input type="hidden" name="direction" value="up"><button class="icon-btn" type="submit" aria-label="نقل القسم للأعلى" @disabled($loop->first)><x-icon name="arrow-up" size="16"/></button></form>
            <form class="inline-form" method="POST" action="{{ route('admin.sections.move', $section) }}">@csrf<input type="hidden" name="direction" value="down"><button class="icon-btn" type="submit" aria-label="نقل القسم للأسفل" @disabled($loop->last)><x-icon name="arrow-down" size="16"/></button></form>
            <a class="btn btn--primary btn--sm" href="{{ route('admin.lessons.create', $section) }}"><x-icon name="plus" size="16"/> درس</a>
            <details style="position:relative"><summary class="btn btn--ghost btn--sm">تعديل القسم</summary>
                <div class="card card--pad" style="position:absolute;inset-inline-end:0;z-index:5;width:min(90vw,420px)">
                    <form method="POST" action="{{ route('admin.sections.update', $section) }}">@csrf @method('PUT')
                        <x-admin.tr name="title" label="عنوان القسم" :value="$section->title" required/>
                        <button class="btn btn--primary btn--sm" type="submit">حفظ</button></form>
                    <form method="POST" action="{{ route('admin.sections.destroy', $section) }}" data-confirm="حذف القسم؟" style="margin-top:8px">@csrf @method('DELETE')<button class="btn btn--danger btn--sm" type="submit">حذف القسم</button></form>
                </div></details>
        </div>
        @forelse ($section->lessons as $lesson)
            <div class="curr-lesson">
                <x-icon :name="$lesson->typeIcon()" size="18"/>
                <a href="{{ route($lesson->type === 'quiz' ? 'admin.quizzes.edit' : 'admin.lessons.edit', $lesson) }}" style="flex:1;font-weight:600" dir="auto">{{ $lesson->tr('title') }}</a>
                <span class="badge badge--gray">{{ $lesson->typeLabel() }}</span>
                @if ($lesson->type === 'video')
                    @if ($lesson->video_path)<span class="badge badge--green">فيديو ✓ {{ $lesson->durationLabel() }}</span>@else<span class="badge badge--red">بدون فيديو</span>@endif
                @endif
                @if ($lesson->type === 'quiz')<span class="badge">{{ $lesson->quiz?->questions_count ?? 0 }} سؤال</span>@endif
                @if ($lesson->is_preview)<span class="badge badge--blue">معاينة عامة</span>@endif
                @unless ($lesson->is_required)<span class="badge badge--gray">اختياري</span>@endunless
                <x-admin.status :status="$lesson->status"/>
                <form class="inline-form" method="POST" action="{{ route('admin.lessons.move', $lesson) }}">@csrf<input type="hidden" name="direction" value="up"><button class="icon-btn" type="submit" aria-label="نقل الدرس للأعلى" @disabled($loop->first)><x-icon name="arrow-up" size="16"/></button></form>
                <form class="inline-form" method="POST" action="{{ route('admin.lessons.move', $lesson) }}">@csrf<input type="hidden" name="direction" value="down"><button class="icon-btn" type="submit" aria-label="نقل الدرس للأسفل" @disabled($loop->last)><x-icon name="arrow-down" size="16"/></button></form>
            </div>
        @empty
            <p class="muted small" style="padding:12px 14px;margin:0">لا توجد دروس في هذا القسم.</p>
        @endforelse
    </section>
@endforeach

<form class="card card--pad" method="POST" action="{{ route('admin.sections.store', $course) }}" style="max-width:640px">
    @csrf
    <h2 class="card__title">إضافة قسم</h2>
    <x-admin.tr name="title" label="عنوان القسم" :value="[]" required/>
    <button class="btn btn--primary" type="submit"><x-icon name="plus" size="16"/> إضافة القسم</button>
</form>
@endsection
