@extends('layouts.admin')
@section('title', 'تحسين الموقع')
@section('content')
@php
    $human = fn ($b) => $b >= 1073741824 ? round($b / 1073741824, 2).' غ.ب' : ($b >= 1048576 ? round($b / 1048576, 1).' م.ب' : round($b / 1024).' ك.ب');
    $speedOk = collect($speed)->where('ok', true)->count();
@endphp
<div class="page-head"><h1>تحسين الموقع</h1>
    <a class="btn btn--ghost" href="{{ route('sitemap') }}" target="_blank"><x-icon name="list" size="18"/> خريطة الموقع</a></div>
<p class="muted small">أدوات لتسريع الموقع، ضغط الصور، تحسين الظهور في جوجل وتنظيف الملفات المؤقتة. كل الأدوات آمنة ولا تحذف أي محتوى.</p>

<div class="opt-scores">
    <div class="opt-score card card--pad">
        <div class="opt-ring" style="--p:{{ $score }}" role="img" aria-label="نتيجة SEO {{ $score }} من 100"><span>{{ $score }}</span></div>
        <div><strong>نتيجة SEO</strong><p class="small muted" style="margin:0">{{ collect($audit)->where('ok', true)->count() }} من {{ count($audit) }} فحصًا ناجحًا</p></div>
    </div>
    <div class="opt-score card card--pad">
        <div class="opt-ring" style="--p:{{ (int) round($speedOk * 100 / max(1, count($speed))) }}" role="img" aria-label="السرعة {{ $speedOk }} من {{ count($speed) }}"><span>{{ $speedOk }}/{{ count($speed) }}</span></div>
        <div><strong>إعدادات السرعة</strong><p class="small muted" style="margin:0">{{ $speedOk === count($speed) ? 'ممتاز!' : 'يمكن تحسين '.(count($speed) - $speedOk).' عناصر' }}</p></div>
    </div>
    <div class="opt-score card card--pad">
        <div class="opt-big"><x-icon name="layers" size="22"/></div>
        <div><strong>{{ $images['count'] }} صورة · {{ $human($images['bytes']) }}</strong><p class="small muted" style="margin:0">{{ $images['pending'] ? $images['pending'].' صورة قابلة للضغط' : 'كل الصور محسّنة' }}</p></div>
    </div>
</div>

<div class="opt-actions">
    @foreach ([
        ['cache', 'sparkles', 'تسريع الموقع', 'يحفظ الإعدادات والمسارات والقوالب في الذاكرة المؤقتة. اضغط بعد كل تحديث للملفات.', 'btn--primary'],
        ['images', 'layers', 'ضغط الصور', 'يصغّر الصور الكبيرة المرفوعة سابقًا دون تغيير روابطها.', 'btn--amber'],
        ['cleanup', 'trash', 'تنظيف الملفات المؤقتة', 'يحذف الرفوعات غير المكتملة والسجلات القديمة والجلسات المنتهية.', 'btn--outline'],
        ['clear', 'copy', 'مسح الذاكرة المؤقتة', 'استعمله إذا لم تظهر تعديلاتك في الموقع.', 'btn--ghost'],
    ] as [$action, $icon, $label, $desc, $cls])
        <form class="card card--pad opt-action" method="POST" action="{{ route('admin.optimize.run', $action) }}">@csrf
            <span class="kpi__icon"><x-icon :name="$icon" size="18"/></span>
            <div><strong>{{ $label }}</strong><p class="small muted">{{ $desc }}</p></div>
            <button class="btn {{ $cls }} btn--sm" type="submit" @if ($action === 'images' && ! $gd) disabled @endif>تشغيل</button>
        </form>
    @endforeach
</div>

<div class="dash-cols dash-cols--even">
    <section class="card card--pad">
        <h2 class="card__title">فحص SEO</h2>
        <ul class="health">
            @foreach ($audit as $a)
                <li class="{{ $a['ok'] ? 'is-ok' : 'is-warn' }}">
                    <span class="health__dot" aria-hidden="true">{{ $a['ok'] ? '✓' : '!' }}</span>
                    <span><span class="sr-only">{{ $a['ok'] ? 'سليم:' : 'يحتاج انتباه:' }}</span>{{ $a['label'] }} @if (! $a['ok'] && $a['bad'] > 1)<strong>({{ $a['bad'] }})</strong>@endif
                        @if (! $a['ok'])<br><small class="muted">{{ $a['hint'] }}</small>@endif</span>
                    @if (! $a['ok'] && $a['url'])<a class="btn btn--ghost btn--sm" href="{{ $a['url'] }}">إصلاح</a>@endif
                </li>
            @endforeach
        </ul>
    </section>
    <div>
        <section class="card card--pad">
            <h2 class="card__title">السرعة والخادم</h2>
            <ul class="health">
                @foreach ($speed as $h)
                    <li class="{{ $h['ok'] ? 'is-ok' : (! empty($h['optional']) ? 'is-optional' : 'is-warn') }}">
                        <span class="health__dot" aria-hidden="true">{{ $h['ok'] ? '✓' : (! empty($h['optional']) ? '○' : '!') }}</span>
                        <span>{{ $h['label'] }}@if (! $h['ok'])<br><small class="muted">{{ $h['hint'] }}</small>@endif</span>
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="card card--pad" style="margin-top:16px">
            <h2 class="card__title">المساحة المستعملة</h2>
            <x-admin.bar-chart :rows="[
                ['label' => 'الفيديوهات', 'value' => round($storage['videos'] / 1048576, 1)],
                ['label' => 'الصور والملفات العامة', 'value' => round($storage['public'] / 1048576, 1)],
                ['label' => 'الذاكرة المؤقتة', 'value' => round($storage['cache'] / 1048576, 1)],
                ['label' => 'السجلات', 'value' => round($storage['logs'] / 1048576, 1)],
            ]" unit="م.ب" empty="لا توجد ملفات."/>
        </section>
    </div>
</div>

<form class="card card--pad" style="margin-top:16px" method="POST" action="{{ route('admin.optimize.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <h2 class="card__title">إعدادات التحسين</h2>
    <div class="dash-cols dash-cols--even" style="margin:0">
        <fieldset class="box">
            <legend class="label">الصور والسرعة</legend>
            <label class="check field"><input type="checkbox" name="image_optimize" value="1" @checked($s['image_optimize']) @disabled(! $gd)> ضغط الصور تلقائيًا عند الرفع (تحويل إلى WebP)</label>
            <div class="form-grid">
                <div class="field"><label for="image_max_width">أقصى عرض للصورة (بكسل)</label><input class="input" type="number" min="800" max="4000" step="100" id="image_max_width" name="image_max_width" value="{{ $s['image_max_width'] }}"></div>
                <div class="field"><label for="image_quality">الجودة (50–95)</label><input class="input" type="number" min="50" max="95" id="image_quality" name="image_quality" value="{{ $s['image_quality'] }}"></div>
            </div>
            <label class="check field"><input type="checkbox" name="lazy_images" value="1" @checked($s['lazy_images'])> تحميل الصور عند الوصول إليها فقط (Lazy loading)</label>
            <label class="check field"><input type="checkbox" name="html_minify" value="1" @checked($s['html_minify'])> تصغير كود HTML للصفحات (أخف بنحو 10–20%)</label>
            @unless ($gd)<p class="hint">إضافة GD غير مفعّلة في PHP؛ ضغط الصور غير متاح حاليًا.</p>@endunless
        </fieldset>
        <fieldset class="box">
            <legend class="label">محركات البحث</legend>
            <label class="check field"><input type="checkbox" name="seo_title_suffix" value="1" @checked($s['seo_title_suffix'])> إضافة اسم الأكاديمية في نهاية عنوان كل صفحة</label>
            <label class="check field"><input type="checkbox" name="org_schema" value="1" @checked($s['org_schema'])> بيانات منظّمة للأكاديمية (Schema.org) لنتائج جوجل الغنية</label>
            <div class="field"><label for="google_verification">رمز Google Search Console</label><input class="input ltr" id="google_verification" name="google_verification" value="{{ old('google_verification', $s['google_verification']) }}" placeholder="abc123…">
                <p class="hint">من Search Console › إضافة موقع › وسم HTML: الصق قيمة content فقط.</p></div>
            <div class="field"><label for="bing_verification">رمز Bing Webmaster (اختياري)</label><input class="input ltr" id="bing_verification" name="bing_verification" value="{{ old('bing_verification', $s['bing_verification']) }}"></div>
            <div class="field"><label for="og_image">صورة المشاركة الافتراضية (1200×630)</label><input class="input" type="file" id="og_image" name="og_image" accept="image/png,image/jpeg,image/webp">
                @if ($s['og_image_path'])<img src="{{ \App\Support\Media::url($s['og_image_path']) }}" alt="" style="max-height:80px;border-radius:8px;margin-top:6px"><label class="check small"><input type="checkbox" name="remove_og_image" value="1"> حذف</label>@endif</div>
            <div class="field"><label for="robots_extra">قواعد إضافية لملف robots.txt</label><textarea class="textarea code-area" id="robots_extra" name="robots_extra" rows="3" dir="ltr" placeholder="Disallow: /p/old-page">{{ old('robots_extra', $s['robots_extra']) }}</textarea>
                <p class="hint">سطر لكل قاعدة: Disallow: /… أو Allow: /…</p></div>
        </fieldset>
    </div>
    <button class="btn btn--primary btn--lg" type="submit">حفظ</button>
</form>
@endsection
