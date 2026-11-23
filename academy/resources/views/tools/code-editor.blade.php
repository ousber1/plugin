@extends('layouts.app')
@section('title', $tool->tr('name'))
@section('description', $tool->tr('description'))
@section('content')
@include('tools._head')
<div class="container section--tight">
    <div class="alert alert--info overlap"><x-icon name="shield"/> <span>{{ __('المعاينة تعمل داخل إطار معزول (sandbox) بدون أي وصول إلى حسابك أو ملفات تعريف الارتباط أو هذه الصفحة.') }}</span></div>
    <div class="row" style="margin-bottom:12px">
        <button class="btn btn--primary btn--sm" type="button" id="run"><x-icon name="play" size="16"/> {{ __('تشغيل') }}</button>
        <label class="check small"><input type="checkbox" id="auto" checked> {{ __('تشغيل تلقائي') }}</label>
        <select class="select" id="template" style="width:auto;min-height:36px" aria-label="{{ __('نماذج جاهزة') }}">
            <option value="">{{ __('نماذج جاهزة…') }}</option><option value="hello">{{ __('صفحة ترحيب') }}</option><option value="counter">{{ __('عدّاد تفاعلي') }}</option><option value="card">{{ __('بطاقة CSS') }}</option>
        </select>
        <button class="btn btn--outline btn--sm" type="button" id="download"><x-icon name="download" size="16"/> {{ __('تحميل HTML') }}</button>
        <button class="btn btn--ghost btn--sm" type="button" id="reset">{{ __('مسح') }}</button>
    </div>
    <div class="editor-grid">
        <div class="field"><label for="html">HTML</label><textarea class="textarea code-area" id="html" spellcheck="false"></textarea></div>
        <div class="field"><label for="css">CSS</label><textarea class="textarea code-area" id="css" spellcheck="false"></textarea></div>
        <div class="field"><label for="js">JavaScript</label><textarea class="textarea code-area" id="js" spellcheck="false"></textarea></div>
    </div>
    <h2 style="font-size:1.1rem">{{ __('المعاينة') }}</h2>
    {{-- sandbox without allow-same-origin => opaque origin: no cookies, storage, or parent access --}}
    <iframe id="preview" class="preview-frame" sandbox="allow-scripts allow-modals" referrerpolicy="no-referrer" title="{{ __('معاينة الكود') }}"></iframe>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var KEY = 'tool:code-editor';
    var templates = {
        hello: { html: '<h1>مرحبًا بالعالم 👋</h1>\n<p>عدّل الكود وشاهد النتيجة مباشرة.</p>', css: 'body{font-family:system-ui;padding:24px;background:#fbf8f1;color:#1e1550}\nh1{color:#3a2a9e}', js: "console.log('Hello!');" },
        counter: { html: '<button id="b">اضغط: <span id="n">0</span></button>', css: 'button{font-size:22px;padding:12px 24px;border-radius:12px;border:0;background:#f5b82e}', js: "var n=0;document.getElementById('b').onclick=function(){document.getElementById('n').textContent=++n;};" },
        card: { html: '<div class="card"><h2>Card</h2><p>CSS grid & shadows</p></div>', css: 'body{display:grid;place-items:center;min-height:90vh;background:#1e1550}\n.card{background:#fff;padding:32px;border-radius:20px;box-shadow:0 20px 40px #0006}', js: '' }
    };
    function load() { try { return JSON.parse(localStorage.getItem(KEY)) || templates.hello; } catch (e) { return templates.hello; } }
    function fill(d) { $('html').value = d.html; $('css').value = d.css; $('js').value = d.js; run(); }
    function doc() {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; script-src \'unsafe-inline\'; style-src \'unsafe-inline\'; img-src data: https:; font-src data: https:">'
            + '<style>' + $('css').value + '</style></head><body>' + $('html').value
            + '<script>' + $('js').value.replace(/<\/script/gi, '<\\/script') + '<\/script></body></html>';
    }
    function run() {
        $('preview').srcdoc = doc();
        try { localStorage.setItem(KEY, JSON.stringify({ html: $('html').value, css: $('css').value, js: $('js').value })); } catch (e) {}
    }
    var t; ['html', 'css', 'js'].forEach(function (id) {
        $(id).addEventListener('input', function () { if ($('auto').checked) { clearTimeout(t); t = setTimeout(run, 500); } });
        $(id).addEventListener('keydown', function (e) { if (e.key === 'Tab') { e.preventDefault(); var s = this.selectionStart; this.setRangeText('  ', s, this.selectionEnd, 'end'); } });
    });
    $('run').onclick = run;
    $('template').onchange = function () { if (this.value) fill(templates[this.value]); this.value = ''; };
    $('reset').onclick = function () { fill({ html: '', css: '', js: '' }); };
    $('download').onclick = function () { var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([doc()], { type: 'text/html' })); a.download = 'index.html'; a.click(); };
    fill(load());
})();
</script>
@endpush
