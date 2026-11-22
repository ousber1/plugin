@extends('layouts.app')
@section('title', __('الأدوات'))
@section('description', __('أدوات عملية مجانية تعمل مباشرة في المتصفح: محرر أكواد، منشئ سيرة ذاتية، مولّد فواتير، مصمم شهادات، ومنشئ اختبارات.'))
@section('content')
@php $colors = ['code-editor' => '#4b37c4', 'cv-builder' => '#1e1550', 'invoice' => '#0f766e', 'certificate-designer' => '#a16207', 'quiz-builder' => '#c2410c']; $icons = ['code-editor' => 'code', 'cv-builder' => 'briefcase', 'invoice' => 'receipt', 'certificate-designer' => 'award', 'quiz-builder' => 'check-square']; @endphp
<section class="page-hero page-hero--center"><div class="container">
    <span class="eyebrow" style="background:rgba(255,255,255,.12);color:#fff"><x-icon name="sparkles" size="14"/> {{ __('مجانية بالكامل') }}</span>
    <h1>{{ __('الأدوات') }}</h1>
    <p>{{ __('أدوات عملية تساعدك في التعلم والعمل، تعمل مباشرة في المتصفح بدون تسجيل ولا تثبيت.') }}</p>
</div></section>
<div class="container">
    <div class="grid grid--3 overlap">
        @foreach ($tools as $tool)
            <article class="card tool-card">
                <div class="row row--between"><span class="tool-card__icon" style="background:{{ $colors[$tool->key] ?? '#4b37c4' }}"><x-icon :name="$icons[$tool->key] ?? 'sparkles'" size="26"/></span>@if ($tool->badge)<span class="badge">{{ $tool->badge }}</span>@endif</div>
                <h2 style="font-size:1.15rem;margin:0">{{ $tool->tr('name') }}</h2>
                <p>{{ $tool->tr('description') }}</p>
                <a href="{{ route('tools.show', $tool) }}" class="row" style="gap:6px;font-weight:700">{{ __('ابدأ الآن') }} <x-icon name="chevron-end" size="16"/></a>
            </article>
        @endforeach
        <article class="card tool-card" style="border-style:dashed;background:transparent">
            <span class="tool-card__icon" style="background:var(--indigo-50);color:var(--indigo-600)"><x-icon name="plus" size="26"/></span>
            <h2 style="font-size:1.15rem;margin:0">{{ __('أدوات جديدة قريبًا') }}</h2>
            <p>{{ __('نضيف أدوات جديدة باستمرار. لديك فكرة أداة تفيدك؟ راسلنا.') }}</p>
        </article>
    </div>

    <section class="section--tight">
        <div class="card card--pad dash-grid" style="align-items:center">
            <form method="POST" action="{{ route('tools.suggest') }}">
                @csrf
                @include('partials.form-errors')
                <div style="position:absolute;left:-9999px" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="field"><label for="idea">{{ __('فكرتك') }} <span class="req">*</span></label>
                    <textarea class="textarea" id="idea" name="idea" required minlength="10" maxlength="1500" dir="auto" placeholder="{{ __('مثال: أداة لتحويل الصور إلى PDF…') }}">{{ old('idea') }}</textarea></div>
                <div class="form-grid">
                    <div class="field"><label for="s-name">{{ __('الاسم (اختياري)') }}</label><input class="input" id="s-name" name="name" value="{{ old('name') }}" maxlength="120"></div>
                    <div class="field"><label for="s-email">{{ __('البريد الإلكتروني (اختياري، لنخبرك عند إضافتها)') }}</label><input class="input input-ltr" id="s-email" type="email" name="email" value="{{ old('email') }}"></div>
                </div>
                <button class="btn btn--primary" type="submit">{{ __('إرسال الفكرة') }}</button>
            </form>
            <div>
                <span class="feature__icon"><x-icon name="sparkles" size="26"/></span>
                <h2 style="font-size:1.3rem">{{ __('اقترح علينا أداة جديدة') }}</h2>
                <p class="muted">{{ __('عندك فكرة أداة تساعدك في الدراسة أو العمل؟ اكتبها هنا وقد نضيفها إلى قائمة الأدوات.') }}</p>
            </div>
        </div>
    </section>
    <div class="grid grid--3">
        <div class="card card--pad row"><x-icon name="lock"/> <div><strong>{{ __('بياناتك عندك') }}</strong><p class="small muted" style="margin:0">{{ __('كل شيء يُحفظ في متصفحك فقط، لا نرسل أي معلومة إلى الخادم.') }}</p></div></div>
        <div class="card card--pad row"><x-icon name="sparkles"/> <div><strong>{{ __('سريعة وبدون تثبيت') }}</strong><p class="small muted" style="margin:0">{{ __('تعمل على الحاسوب والهاتف مباشرة.') }}</p></div></div>
        <div class="card card--pad row"><x-icon name="check"/> <div><strong>{{ __('مجانية') }}</strong><p class="small muted" style="margin:0">{{ __('بدون حدود استعمال ولا علامة مائية.') }}</p></div></div>
    </div>
</div>
@endsection
