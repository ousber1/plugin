<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>محرّر الصفحات — {{ $label }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
</head>
<body class="pb">
<header class="pb-top">
    <div class="row" style="gap:6px;flex-wrap:nowrap">
        <a class="btn btn--ghost btn--sm" href="{{ $target === 'home' ? route('admin.dashboard') : route('admin.pages.index') }}" aria-label="رجوع"><x-icon name="chevron-end" size="18"/></a>
        <div class="pb-title"><strong>{{ $label }}</strong>
            <small id="pb-state">{{ $live ? 'منشورة بالمحرّر' : 'تعرض التصميم الافتراضي حاليًا — احفظ لنشر هذا التصميم' }}</small></div>
    </div>
    <div class="pb-tools">
        <div class="pb-seg" role="group" aria-label="لغة المحتوى">
            <button type="button" data-lang="ar" aria-pressed="true">AR</button><button type="button" data-lang="fr" aria-pressed="false">FR</button><button type="button" data-lang="en" aria-pressed="false">EN</button>
        </div>
        <div class="pb-seg" role="group" aria-label="حجم المعاينة">
            <button type="button" data-device="desktop" aria-pressed="true" title="حاسوب">🖥</button><button type="button" data-device="tablet" aria-pressed="false" title="لوحي">▭</button><button type="button" data-device="mobile" aria-pressed="false" title="هاتف">📱</button>
        </div>
        <button type="button" class="icon-btn" id="pb-undo" title="تراجع (Ctrl+Z)" aria-label="تراجع">↶</button>
        <button type="button" class="icon-btn" id="pb-redo" title="إعادة (Ctrl+Y)" aria-label="إعادة">↷</button>
        <a class="btn btn--ghost btn--sm" href="{{ $viewUrl }}" target="_blank"><x-icon name="eye" size="16"/> عرض</a>
        @if ($live)
            <form method="POST" action="{{ route('admin.builder.disable', $target) }}" onsubmit="return confirm('العودة إلى التصميم الافتراضي؟ يبقى هذا التصميم محفوظًا.')">@csrf<button class="btn btn--ghost btn--sm" type="submit">التصميم الافتراضي</button></form>
        @endif
        <button type="button" class="btn btn--amber btn--sm" id="pb-save"><x-icon name="check" size="16"/> حفظ ونشر</button>
    </div>
</header>
<div class="pb-body">
    <aside class="pb-panel" id="pb-panel" aria-label="لوحة المحرّر"></aside>
    <main class="pb-canvas">
        <div class="pb-frame" id="pb-frame-wrap"><iframe name="pb-preview" id="pb-preview" title="معاينة الصفحة"></iframe></div>
    </main>
</div>
<form id="pb-preview-form" method="POST" action="{{ route('admin.builder.preview', $target) }}" target="pb-preview" hidden>
    @csrf<input type="hidden" name="blocks" id="pb-preview-blocks"><input type="hidden" name="lang" id="pb-preview-lang" value="ar">
</form>
<div class="pb-toast" id="pb-toast" role="status" aria-live="polite" hidden></div>
<script>
window.BUILDER = {!! json_encode([
    'blocks' => $blocks, 'defs' => $defs, 'styleFields' => $styleFields, 'icons' => $icons, 'categories' => $categories,
    'saveUrl' => route('admin.builder.save', $target), 'uploadUrl' => route('admin.builder.upload'), 'mediaBase' => asset('storage').'/',
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!};
</script>
<script src="{{ asset('js/builder.js') }}?v={{ filemtime(public_path('js/builder.js')) }}"></script>
</body>
</html>
