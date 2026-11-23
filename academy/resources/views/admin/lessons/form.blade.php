@extends('layouts.admin')
@section('title', $lesson->exists ? 'تعديل درس' : 'درس جديد')
@section('content')
<div class="page-head">
    <div><a class="small" href="{{ route('admin.courses.curriculum', $course) }}">← {{ $course->tr('title') }}</a>
        <h1 dir="auto">{{ $lesson->exists ? $lesson->tr('title') : 'درس جديد في «'.$section->tr('title').'»' }}</h1></div>
    @if ($lesson->exists)<div class="row"><x-admin.status :status="$lesson->status"/>
        <a class="btn btn--outline btn--sm" href="{{ route('learn.show', [$course, $lesson]) }}" target="_blank"><x-icon name="eye" size="16"/> معاينة كطالب</a></div>@endif
</div>
@include('admin.courses._nav')

<div class="dash-grid">
    <div class="stack">
        @if ($lesson->exists && in_array($lesson->type, ['video', 'text', 'exercise']))
            <section class="card card--pad" aria-labelledby="video-h">
                <h2 class="card__title" id="video-h"><x-icon name="play"/> فيديو الدرس</h2>
                @if ($lesson->video_path)
                    <div class="video-frame" style="margin-bottom:12px"><video controls preload="metadata" src="{{ route('media.video', $lesson) }}" aria-label="معاينة فيديو الدرس"></video></div>
                    <p class="small muted">الحجم: {{ number_format($lesson->video_size / 1048576, 1) }} م.ب @if ($lesson->durationLabel()) — المدة: <span class="ltr">{{ $lesson->durationLabel() }}</span>@endif — التخزين: {{ $lesson->video_disk }} (خاص)</p>
                @endif
                <div class="uploader" id="video-uploader"
                     data-start-url="{{ route('admin.uploads.start', $lesson) }}"
                     data-max-bytes="{{ $maxBytes }}" data-chunk-bytes="{{ $chunkBytes }}">
                    <x-icon name="upload" size="32"/>
                    <p style="margin:8px 0"><strong>{{ $lesson->video_path ? 'استبدال الفيديو' : 'ارفع فيديو MP4 من حاسوبك' }}</strong><br>
                        <span class="small muted">اسحب الملف هنا أو اختره. الحد الأقصى {{ config('academy.video.max_mb') }} م.ب. يُفضَّل H.264 + AAC. الفيديو الحالي لا يُستبدل إلا بعد نجاح الحفظ.</span></p>
                    <label class="btn btn--primary" for="video-file">اختيار ملف</label>
                    <input class="sr-only" type="file" id="video-file" accept="video/mp4,.mp4">
                    <div class="progress" style="margin-top:14px" aria-hidden="true"><div class="progress__bar" style="width:0%"></div></div>
                    <p data-status class="small muted" role="status" aria-live="polite" style="margin:8px 0 0"></p>
                    <button class="btn btn--ghost btn--sm" type="button" data-cancel hidden>إلغاء الرفع</button>
                </div>
                @if ($lesson->video_path)
                    <form method="POST" action="{{ route('admin.lessons.video.destroy', $lesson) }}" data-confirm="حذف فيديو هذا الدرس؟" style="margin-top:10px">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm" type="submit"><x-icon name="trash" size="16"/> حذف الفيديو</button></form>
                @endif
            </section>
        @endif

        <form class="card card--pad" method="POST" action="{{ $lesson->exists ? route('admin.lessons.update', $lesson) : route('admin.lessons.store', $section) }}">
            @csrf @if ($lesson->exists) @method('PUT') @endif
            <x-admin.tr name="title" label="عنوان الدرس" :value="$lesson->title" required :max="190"/>
            <div class="form-grid">
                <div class="field"><label for="type">نوع الدرس</label>
                    <select class="select" id="type" name="type">@foreach (['video' => 'فيديو', 'text' => 'درس نصي', 'quiz' => 'اختبار', 'exercise' => 'تمرين قابل للتحميل'] as $v => $l)<option value="{{ $v }}" @selected(old('type', $lesson->type) === $v)>{{ $l }}</option>@endforeach</select></div>
                @if ($lesson->exists)
                    <div class="field"><label for="section_id">القسم</label><select class="select" id="section_id" name="section_id">@foreach ($course->sections as $s)<option value="{{ $s->id }}" @selected($lesson->section_id === $s->id)>{{ $s->tr('title') }}</option>@endforeach</select></div>
                @endif
                <div class="field"><label for="duration_minutes">المدة (دقائق : ثوانٍ)</label>
                    <div class="row" style="flex-wrap:nowrap"><input class="input ltr" type="number" min="0" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes', $lesson->duration_seconds ? intdiv($lesson->duration_seconds, 60) : '') }}" aria-label="دقائق">
                        <input class="input ltr" type="number" min="0" max="59" name="duration_extra_seconds" value="{{ old('duration_extra_seconds', $lesson->duration_seconds ? $lesson->duration_seconds % 60 : '') }}" aria-label="ثوانٍ"></div>
                    <p class="hint">تُستخرج تلقائيًا من الفيديو عند رفعه.</p></div>
            </div>
            <x-admin.tr name="body" label="محتوى نصي / تعليمات (اختياري)" :value="$lesson->body" type="textarea" rich :rows="8"/>
            <label class="check field"><input type="checkbox" name="is_preview" value="1" @checked(old('is_preview', $lesson->is_preview))> <span><strong>معاينة عامة مجانية</strong> — يمكن لأي زائر مشاهدة هذا الدرس دون تسجيل.</span></label>
            <label class="check field"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $lesson->is_required))> درس مطلوب (يُحتسب في التقدّم وأهلية الشهادة)</label>
            <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status" style="max-width:240px"><option value="draft" @selected(old('status', $lesson->status) === 'draft')>مسودة</option><option value="published" @selected(old('status', $lesson->status) === 'published')>منشور</option></select></div>
            <button class="btn btn--primary btn--lg" type="submit">{{ $lesson->exists ? 'حفظ الدرس' : 'إنشاء الدرس' }}</button>
            @if ($lesson->exists && $lesson->type === 'quiz')<a class="btn btn--amber btn--lg" href="{{ route('admin.quizzes.edit', $lesson) }}">تحرير أسئلة الاختبار</a>@endif
        </form>
    </div>

    @if ($lesson->exists)
    <div class="stack">
        <section class="card card--pad">
            <h2 class="card__title"><x-icon name="file-text"/> ملفات مرفقة (محمية)</h2>
            <ul class="list-plain small">
                @forelse ($lesson->attachments as $a)
                    <li class="row row--between"><a href="{{ route('media.attachment', $a) }}">{{ $a->title }}</a>
                        <form method="POST" action="{{ route('admin.attachments.destroy', $a) }}" data-confirm="حذف الملف؟">@csrf @method('DELETE')<button class="icon-btn" type="submit" aria-label="حذف {{ $a->title }}"><x-icon name="trash" size="16"/></button></form></li>
                @empty<li class="muted">لا توجد ملفات.</li>@endforelse
            </ul>
            <form method="POST" action="{{ route('admin.lessons.attachments.store', $lesson) }}" enctype="multipart/form-data" style="margin-top:12px">
                @csrf
                <div class="field"><label for="att-title">اسم الملف</label><input class="input" id="att-title" name="title" dir="auto"></div>
                <div class="field"><label for="att-file">الملف</label><input class="input" id="att-file" type="file" name="file" required>
                    <p class="hint">الحد {{ config('academy.attachments.max_mb') }} م.ب: {{ str_replace(',', ', ', config('academy.attachments.mimes')) }}</p></div>
                <button class="btn btn--outline btn--sm" type="submit"><x-icon name="upload" size="16"/> رفع</button>
            </form>
        </section>
        <form method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" data-confirm="حذف الدرس نهائيًا مع ملفاته وتقدّم الطلاب فيه؟">@csrf @method('DELETE')
            <button class="btn btn--danger btn--sm" type="submit"><x-icon name="trash" size="16"/> حذف الدرس</button></form>
    </div>
    @endif
</div>
@endsection
