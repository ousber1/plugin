@extends('layouts.admin')
@section('title', 'تحرير الاختبار')
@section('content')
<div class="page-head"><div><a class="small" href="{{ route('admin.courses.curriculum', $course) }}">← {{ $course->tr('title') }}</a><h1 dir="auto">{{ $lesson->tr('title') }}</h1></div>
    <div class="row"><x-admin.status :status="$lesson->status"/><a class="btn btn--outline btn--sm" href="{{ route('admin.lessons.edit', $lesson) }}">إعدادات الدرس</a></div></div>
@include('admin.courses._nav')

@php
    $questions = old('questions') ?? ($generated
        ? array_merge($quiz->questions->map(fn ($q) => ['question' => $q->question, 'options' => implode("\n", $q->options), 'correct' => implode(',', array_map(fn ($i) => $i + 1, $q->correct)), 'explanation' => $q->explanation])->all(),
            array_map(fn ($q) => ['question' => $q['question'], 'options' => implode("\n", $q['options']), 'correct' => implode(',', array_map(fn ($i) => $i + 1, $q['correct'])), 'explanation' => $q['explanation']], $generated))
        : $quiz->questions->map(fn ($q) => ['question' => $q->question, 'options' => implode("\n", $q->options), 'correct' => implode(',', array_map(fn ($i) => $i + 1, $q->correct)), 'explanation' => $q->explanation])->all());
@endphp

<div class="dash-grid">
    <form method="POST" action="{{ route('admin.quizzes.update', $lesson) }}" data-repeater>
        @csrf @method('PUT')
        @if ($generated)<div class="alert alert--warning"><x-icon name="sparkles"/> الأسئلة المولّدة أُضيفت في الأسفل ولم تُحفظ بعد. راجعها ثم اضغط «حفظ الاختبار».</div>@endif
        <div class="card card--pad field"><label for="pass_score">نسبة النجاح المطلوبة (%)</label><input class="input" style="max-width:160px" type="number" min="1" max="100" id="pass_score" name="pass_score" value="{{ old('pass_score', $quiz->pass_score) }}" required></div>
        <div data-list>
            @foreach ($questions as $i => $q)
                @include('admin.quizzes._question', ['i' => $i, 'q' => $q])
            @endforeach
        </div>
        <template>@include('admin.quizzes._question', ['i' => '__INDEX__', 'q' => ['question' => '', 'options' => '', 'correct' => '1', 'explanation' => '']])</template>
        <div class="row">
            <button class="btn btn--outline" type="button" data-add><x-icon name="plus" size="16"/> إضافة سؤال</button>
            <button class="btn btn--primary btn--lg" type="submit">حفظ الاختبار</button>
        </div>
    </form>

    <aside class="stack">
        <section class="card card--pad">
            <h2 class="card__title"><x-icon name="sparkles"/> توليد أسئلة بالذكاء الاصطناعي (اختياري)</h2>
            @if (! $aiEnabled)
                <p class="small muted">غير مفعّل. يمكن للمدير إضافة مفتاح API من <a href="{{ auth()->user()->isAdmin() ? route('admin.settings.edit', 'ai') : '#' }}">الإعدادات</a>. يمكنك دائمًا كتابة الأسئلة يدويًا.</p>
            @else
                <form method="POST" action="{{ route('admin.quizzes.generate', $lesson) }}">
                    @csrf
                    <div class="field"><label for="source_text">الصق نص الدرس</label><textarea class="textarea" id="source_text" name="source_text" rows="8" required dir="auto" maxlength="{{ config('academy.ai.max_input_chars') }}">{{ old('source_text') }}</textarea></div>
                    <div class="form-grid">
                        <div class="field"><label for="count">عدد الأسئلة</label><input class="input" type="number" min="1" max="15" id="count" name="count" value="{{ old('count', 5) }}"></div>
                        <div class="field"><label for="language">اللغة</label><select class="select" id="language" name="language"><option value="ar">العربية</option><option value="fr">Français</option><option value="en">English</option></select></div>
                    </div>
                    <div class="alert alert--warning small"><x-icon name="info"/> <span>سيُرسَل النص الملصق إلى مزوّد الذكاء الاصطناعي (Anthropic) لتوليد الأسئلة. لا تلصق بيانات شخصية. الأسئلة لا تُحفظ إلا بعد مراجعتك.</span></div>
                    <label class="check small field"><input type="checkbox" name="consent" value="1" required> أوافق على إرسال هذا النص إلى المزوّد</label>
                    <button class="btn btn--amber btn--block" type="submit">توليد الأسئلة</button>
                </form>
            @endif
        </section>
        <div class="card card--pad small muted">
            <strong>طريقة الكتابة:</strong> اكتب كل خيار في سطر مستقل، ثم أدخل رقم الإجابة الصحيحة (1 للخيار الأول). للأسئلة متعددة الإجابات افصل الأرقام بفاصلة: 1,3.
        </div>
    </aside>
</div>
@endsection
