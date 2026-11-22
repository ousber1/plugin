@extends('layouts.app')
@section('title', $tool->tr('name'))
@section('description', $tool->tr('description'))
@section('content')
@include('tools._head')
<div class="container section--tight">
    <div class="tool-shell overlap">
        <form class="card card--pad no-print" id="cv-form" onsubmit="return false">
            <div class="form-grid">
                <div class="field"><label for="cv-lang">{{ __('لغة السيرة') }}</label><select class="select" id="cv-lang" name="lang"><option value="fr">Français</option><option value="en">English</option><option value="ar">العربية</option></select></div>
                <div class="field"><label for="cv-color">{{ __('اللون') }}</label><input class="input" type="color" id="cv-color" name="color" value="#3a2a9e"></div>
            </div>
            <div class="field"><label for="cv-name">{{ __('الاسم الكامل') }}</label><input class="input" id="cv-name" name="name" dir="auto"></div>
            <div class="field"><label for="cv-title">{{ __('المسمى المهني') }}</label><input class="input" id="cv-title" name="title" dir="auto"></div>
            <div class="form-grid">
                <div class="field"><label for="cv-email">{{ __('البريد') }}</label><input class="input input-ltr" id="cv-email" name="email"></div>
                <div class="field"><label for="cv-phone">{{ __('الهاتف') }}</label><input class="input input-ltr" id="cv-phone" name="phone"></div>
            </div>
            <div class="field"><label for="cv-city">{{ __('المدينة') }}</label><input class="input" id="cv-city" name="city" dir="auto"></div>
            <div class="field"><label for="cv-summary">{{ __('نبذة') }}</label><textarea class="textarea" id="cv-summary" name="summary" dir="auto"></textarea></div>
            <div class="field"><label for="cv-exp">{{ __('الخبرات (سطر لكل خبرة: الفترة | المنصب | الجهة)') }}</label><textarea class="textarea" id="cv-exp" name="experience" dir="auto"></textarea></div>
            <div class="field"><label for="cv-edu">{{ __('التكوين (سطر لكل شهادة: السنة | الشهادة | المؤسسة)') }}</label><textarea class="textarea" id="cv-edu" name="education" dir="auto"></textarea></div>
            <div class="field"><label for="cv-skills">{{ __('المهارات (مفصولة بفواصل)') }}</label><input class="input" id="cv-skills" name="skills" dir="auto"></div>
            <div class="field"><label for="cv-langs">{{ __('اللغات (مفصولة بفواصل)') }}</label><input class="input" id="cv-langs" name="languages" dir="auto"></div>
            <div class="row"><button class="btn btn--primary" type="button" onclick="window.print()"><x-icon name="download" size="18"/> {{ __('طباعة / حفظ PDF') }}</button>
                <button class="btn btn--ghost" type="button" id="cv-clear">{{ __('مسح') }}</button></div>
        </form>
        <div class="paper printable" id="cv-out" aria-live="polite"></div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var form = document.getElementById('cv-form'), out = document.getElementById('cv-out'), KEY = 'tool:cv';
    var L = { fr: ['Profil', 'Expérience', 'Formation', 'Compétences', 'Langues'], en: ['Profile', 'Experience', 'Education', 'Skills', 'Languages'], ar: ['نبذة', 'الخبرات', 'التكوين', 'المهارات', 'اللغات'] };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
    function rows(text) { return (text || '').split('\n').filter(Boolean).map(function (l) { var p = l.split('|').map(function (x) { return x.trim(); }); return '<div style="display:flex;gap:16px;margin-bottom:8px"><span style="min-width:110px;color:#666">' + esc(p[0]) + '</span><span><strong>' + esc(p[1]) + '</strong>' + (p[2] ? '<br><span style="color:#555">' + esc(p[2]) + '</span>' : '') + '</span></div>'; }).join(''); }
    function render() {
        var d = Object.fromEntries(new FormData(form)); var t = L[d.lang] || L.fr; var c = d.color;
        out.setAttribute('dir', d.lang === 'ar' ? 'rtl' : 'ltr'); out.lang = d.lang;
        var h = function (x) { return '<h3 style="color:' + c + ';border-bottom:2px solid ' + c + ';padding-bottom:4px;margin:22px 0 10px;font-size:15px;text-transform:uppercase;letter-spacing:.05em">' + x + '</h3>'; };
        var list = function (s) { return (s || '').split(',').filter(function (x) { return x.trim(); }).map(function (x) { return '<span style="display:inline-block;background:#f2f0fd;border-radius:6px;padding:2px 10px;margin:3px">' + esc(x.trim()) + '</span>'; }).join(''); };
        out.innerHTML = '<div style="border-inline-start:6px solid ' + c + ';padding-inline-start:16px"><h2 style="margin:0;font-size:28px;color:#111">' + (esc(d.name) || '—') + '</h2><p style="margin:4px 0;color:' + c + ';font-weight:700">' + esc(d.title) + '</p><p style="margin:0;color:#555;font-size:13px">' + [d.email, d.phone, d.city].filter(Boolean).map(esc).join(' · ') + '</p></div>'
            + (d.summary ? h(t[0]) + '<p style="margin:0">' + esc(d.summary) + '</p>' : '') + (d.experience ? h(t[1]) + rows(d.experience) : '') + (d.education ? h(t[2]) + rows(d.education) : '')
            + (d.skills ? h(t[3]) + list(d.skills) : '') + (d.languages ? h(t[4]) + list(d.languages) : '');
        try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) {}
    }
    try { var saved = JSON.parse(localStorage.getItem(KEY) || '{}'); Object.keys(saved).forEach(function (k) { if (form.elements[k]) form.elements[k].value = saved[k]; }); } catch (e) {}
    form.addEventListener('input', render);
    document.getElementById('cv-clear').onclick = function () { form.reset(); try { localStorage.removeItem(KEY); } catch (e) {} render(); };
    render();
})();
</script>
@endpush
