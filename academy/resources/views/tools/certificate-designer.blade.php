@extends('layouts.app')
@section('title', $tool->tr('name'))
@section('description', $tool->tr('description'))
@section('content')
@include('tools._head')
<div class="container section--tight">
    <div class="alert alert--warning overlap no-print"><x-icon name="info"/> <span>{{ __('هذه الأداة تصمّم شهادات عامة لفعالياتك وورشاتك. لا تُسجَّل في نظام التحقق ولا تُعد شهادة رسمية صادرة عن الأكاديمية.') }}</span></div>
    <div class="tool-shell">
        <form class="card card--pad no-print" id="cd-form" onsubmit="return false">
            <div class="form-grid">
                <div class="field"><label for="cd-tpl">{{ __('القالب') }}</label><select class="select" id="cd-tpl" name="tpl"><option value="classic">{{ __('كلاسيكي') }}</option><option value="modern">{{ __('عصري') }}</option><option value="minimal">{{ __('بسيط') }}</option></select></div>
                <div class="field"><label for="cd-or">{{ __('الاتجاه') }}</label><select class="select" id="cd-or" name="orientation"><option value="landscape">{{ __('أفقي') }}</option><option value="portrait">{{ __('عمودي') }}</option></select></div>
                <div class="field"><label for="cd-c1">{{ __('اللون الأساسي') }}</label><input class="input" type="color" id="cd-c1" name="c1" value="#1e1550"></div>
                <div class="field"><label for="cd-c2">{{ __('لون التمييز') }}</label><input class="input" type="color" id="cd-c2" name="c2" value="#d99a0b"></div>
            </div>
            <div class="field"><label for="cd-title">{{ __('العنوان') }}</label><input class="input" id="cd-title" name="title" value="Certificat de participation" dir="auto"></div>
            <div class="field"><label for="cd-intro">{{ __('نص التقديم') }}</label><input class="input" id="cd-intro" name="intro" value="Ce certificat est décerné à" dir="auto"></div>
            <div class="field"><label for="cd-names">{{ __('أسماء المشاركين (اسم في كل سطر)') }}</label><textarea class="textarea" id="cd-names" name="names" dir="auto">Salma El Idrissi
Youssef Amrani</textarea></div>
            <div class="field"><label for="cd-body">{{ __('النص') }}</label><textarea class="textarea" id="cd-body" name="body" style="min-height:70px" dir="auto">pour sa participation à l’atelier « Initiation au design graphique ».</textarea></div>
            <div class="form-grid">
                <div class="field"><label for="cd-org">{{ __('الجهة') }}</label><input class="input" id="cd-org" name="org" value="Club Créatif" dir="auto"></div>
                <div class="field"><label for="cd-date">{{ __('التاريخ') }}</label><input class="input" id="cd-date" name="date" dir="auto"></div>
                <div class="field"><label for="cd-sign">{{ __('اسم الموقّع') }}</label><input class="input" id="cd-sign" name="signer" dir="auto"></div>
            </div>
            <button class="btn btn--primary" type="button" onclick="window.print()"><x-icon name="download" size="18"/> {{ __('طباعة الكل / حفظ PDF') }}</button>
        </form>
        <div id="cd-out" class="printable stack"></div>
    </div>
</div>
@endsection
@push('head')
<style>@media print { #cd-out .cert-preview { page-break-after: always; box-shadow: none; margin: 0; border-radius: 0; } }</style>
@endpush
@push('scripts')
<script>
(function () {
    var form = document.getElementById('cd-form'), out = document.getElementById('cd-out');
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
    var isRtl = function (s) { return /[؀-ۿ]/.test(s || ''); };
    function card(d, name) {
        var frame = { classic: 'border:14px double ' + d.c1 + ';outline:3px solid ' + d.c2 + ';outline-offset:-26px', modern: 'border-top:22px solid ' + d.c1 + ';border-bottom:22px solid ' + d.c2, minimal: 'border:2px solid ' + d.c1 }[d.tpl];
        return '<div class="cert-preview ' + (d.orientation === 'portrait' ? 'is-portrait' : '') + '" dir="' + (isRtl(d.title + name) ? 'rtl' : 'ltr') + '" style="' + frame + '">'
            + '<div style="font-size:clamp(18px,3.4vw,34px);font-weight:800;color:' + d.c1 + ';letter-spacing:.04em">' + esc(d.title) + '</div>'
            + '<div style="width:80px;height:4px;background:' + d.c2 + ';margin:12px auto 18px;border-radius:4px"></div>'
            + '<div style="color:#555">' + esc(d.intro) + '</div>'
            + '<div style="font-size:clamp(20px,4vw,40px);font-weight:800;margin:10px 0;color:#111">' + esc(name) + '</div>'
            + '<div style="color:#444;max-width:80%">' + esc(d.body) + '</div>'
            + '<div style="display:flex;justify-content:space-between;width:80%;margin-top:auto;padding-top:20px;font-size:14px;color:#333"><span>' + esc(d.org) + '<br>' + esc(d.date) + '</span><span style="border-top:1px solid #999;padding-top:4px;min-width:140px">' + esc(d.signer) + '</span></div></div>';
    }
    function render() {
        var d = Object.fromEntries(new FormData(form));
        var names = d.names.split('\n').map(function (n) { return n.trim(); }).filter(Boolean);
        out.innerHTML = (names.length ? names : ['—']).map(function (n) { return card(d, n); }).join('');
    }
    form.elements.date.value = new Date().toLocaleDateString('fr-MA');
    form.addEventListener('input', render); render();
})();
</script>
@endpush
