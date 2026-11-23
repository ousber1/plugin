@extends('layouts.admin')
@section('title', 'الهيدر والتذييل')
@section('content')
@php
    $cols = array_values($s['footer_columns'] ?? []);
    $locales = \App\Support\Locales::all();
@endphp
<div class="page-head"><h1>الهيدر والتذييل</h1>
    <div class="row"><a class="btn btn--ghost" href="{{ route('admin.settings.edit', 'appearance') }}"><x-icon name="sparkles" size="18"/> الثيمات والألوان</a>
        <a class="btn btn--outline" href="{{ route('home') }}" target="_blank"><x-icon name="eye" size="18"/> معاينة الموقع</a></div></div>
<p class="muted small">تحكّم في أعلى وأسفل كل صفحات الموقع. لتغيير لون الهيدر أو التذييل (فاتح/داكن/بلون الهوية) استعمل صفحة الثيمات.</p>

<form method="POST" action="{{ route('admin.layout.update') }}" class="layout-editor" enctype="multipart/form-data">
    @csrf @method('PUT')

    <section class="card card--pad">
        <h2 class="card__title"><x-icon name="layers" size="20"/> الهيدر (أعلى الصفحة)</h2>
        <div class="mock mock--header" aria-hidden="true"><span class="mock__logo"></span><span class="mock__nav"><i></i><i></i><i></i><i></i></span><span class="mock__cta">{{ \App\Support\Settings::tr('header_cta_label') ?: 'زر' }}</span></div>
        <fieldset class="field box">
            <legend class="label">زر الدعوة في الهيدر (مثل «اطلب شهادتك الآن»)</legend>
            <label class="check field"><input type="checkbox" name="header_cta_enabled" value="1" @checked($s['header_cta_enabled'])> إظهار الزر في الهيدر وفي قائمة الهاتف</label>
            <x-admin.tr name="header_cta_label" label="نص الزر" :value="$s['header_cta_label']" :max="40"/>
            <div class="form-grid">
                <div class="field"><label for="header_cta_action">عند الضغط</label>
                    <select class="select" id="header_cta_action" name="header_cta_action" data-toggle-target="cta">
                        <option value="whatsapp" @selected($s['header_cta_action'] === 'whatsapp')>فتح واتساب برسالة جاهزة</option>
                        <option value="url" @selected($s['header_cta_action'] === 'url')>فتح رابط</option>
                    </select></div>
                <div class="field" data-show-when="cta=url"><label for="header_cta_url">الرابط</label><input class="input ltr" id="header_cta_url" name="header_cta_url" value="{{ old('header_cta_url', $s['header_cta_url']) }}" placeholder="/courses أو https://"></div>
            </div>
            <div data-show-when="cta=whatsapp"><x-admin.tr name="header_cta_message" label="الرسالة الجاهزة في واتساب" :value="$s['header_cta_message']" type="textarea" :rows="2"/></div>
            <p class="hint">يُرسل إلى رقم واتساب الأكاديمية (الإعدادات › واتساب). نص الزر العائم يُعدّل من نفس الصفحة.</p>
        </fieldset>
        <div class="form-grid">
            <label class="check"><input type="checkbox" name="header_show_auth" value="1" @checked($s['header_show_auth'])> إظهار زر «دخول» للزوار</label>
            <label class="check"><input type="checkbox" name="header_show_lang" value="1" @checked($s['header_show_lang'])> إظهار اختيار اللغة</label>
            <label class="check"><input type="checkbox" name="sticky_header" value="1" @checked($s['sticky_header'])> تثبيت الهيدر عند التمرير</label>
            <label class="check"><input type="checkbox" name="topbar_enabled" value="1" @checked($s['topbar_enabled'])> إظهار الشريط العلوي الصغير</label>
        </div>
        <x-admin.tr name="topbar_text" label="نص الشريط العلوي" :value="$s['topbar_text']"/>
        <p class="hint">روابط القائمة تُضاف من <a href="{{ route('admin.settings.edit', 'features') }}">الإعدادات › الوحدات والقائمة</a>. صفحة «مراكزنا» تظهر تلقائيًا عند إضافة مركز.</p>
    </section>

    <section class="card card--pad">
        <h2 class="card__title"><x-icon name="layers" size="20"/> التذييل (أسفل الصفحة)</h2>
        <fieldset class="field box">
            <legend class="label">شريط الدعوة أعلى التذييل</legend>
            <label class="check field"><input type="checkbox" name="footer_cta_enabled" value="1" @checked($s['footer_cta_enabled'])> إظهار الشريط</label>
            <x-admin.tr name="footer_cta_title" label="العنوان" :value="$s['footer_cta_title']"/>
            <x-admin.tr name="footer_cta_text" label="النص" :value="$s['footer_cta_text']"/>
            <div class="form-grid">
                <x-admin.tr name="footer_cta_button" label="نص الزر" :value="$s['footer_cta_button']" :max="40"/>
                <div class="field"><label for="footer_cta_url">رابط الزر</label><input class="input ltr" id="footer_cta_url" name="footer_cta_url" value="{{ old('footer_cta_url', $s['footer_cta_url']) }}" placeholder="/courses"></div>
            </div>
        </fieldset>
        <fieldset class="field box"><legend class="label">شعار التذييل</legend>
            <div class="footer-logo-preview" style="background:var(--footer)">
                @if ($s['footer_logo_path'])<img src="{{ \App\Support\Media::url($s['footer_logo_path']) }}" alt="" style="height:{{ $s['footer_logo_height'] }}px">@else<x-logo light/>@endif
            </div>
            <label class="check field"><input type="checkbox" name="footer_show_logo" value="1" @checked($s['footer_show_logo'] ?? true)> إظهار الشعار في التذييل</label>
            <div class="form-grid">
                <div class="field"><label for="footer_logo">شعار خاص بالتذييل (PNG شفاف، يفضَّل بنسخة فاتحة)</label><input class="input" type="file" id="footer_logo" name="footer_logo" accept="image/png,image/webp,image/jpeg">
                    <p class="hint">إذا تُرك فارغًا يُستعمل شعار الموقع الرئيسي (الإعدادات › الهوية).</p>
                    @if ($s['footer_logo_path'])<label class="check small"><input type="checkbox" name="remove_footer_logo" value="1"> حذف شعار التذييل والعودة للشعار الرئيسي</label>@endif</div>
                <div class="field"><label for="footer_logo_height">ارتفاع الشعار (بكسل)</label><input class="input" type="number" min="24" max="140" id="footer_logo_height" name="footer_logo_height" value="{{ old('footer_logo_height', $s['footer_logo_height'] ?? 44) }}"></div>
            </div>
        </fieldset>
        <x-admin.tr name="footer_about" label="نبذة عن الأكاديمية (العمود الأول)" :value="$s['footer_about']" type="textarea" :rows="2"/>
        <div class="form-grid">
            <label class="check"><input type="checkbox" name="footer_show_contact" value="1" @checked($s['footer_show_contact'])> إظهار البريد والهاتف والعنوان</label>
            <label class="check"><input type="checkbox" name="footer_show_social" value="1" @checked($s['footer_show_social'])> إظهار أيقونات الشبكات الاجتماعية</label>
        </div>

        <h3 style="margin-top:18px">أعمدة التذييل (حتى 4)</h3>
        <div class="footer-cols-editor">
            @for ($c = 0; $c < 4; $c++)
                @php $col = $cols[$c] ?? ['type' => 'none', 'title' => [], 'links' => []]; @endphp
                <fieldset class="box footer-col-editor">
                    <legend class="label">العمود {{ $c + 1 }}</legend>
                    <div class="field"><label for="col-{{ $c }}-type">المحتوى</label>
                        <select class="select" id="col-{{ $c }}-type" name="columns[{{ $c }}][type]" data-toggle-target="col{{ $c }}">
                            <option value="none" @selected($col['type'] === 'none')>— بدون عمود —</option>
                            @foreach ($columnTypes as $k => $l)<option value="{{ $k }}" @selected($col['type'] === $k)>{{ $l }}</option>@endforeach
                        </select></div>
                    <div class="field"><span class="label">العنوان</span>
                        <div class="link-row">
                            @foreach ($locales as $code => $m)
                                <input class="input" name="columns[{{ $c }}][title][{{ $code }}]" value="{{ $col['title'][$code] ?? '' }}" dir="{{ $m['dir'] }}" maxlength="60" placeholder="{{ $m['short'] }}" aria-label="عنوان العمود {{ $c + 1 }} ({{ $m['name'] }})">
                            @endforeach
                        </div></div>
                    <div data-repeater data-show-when="col{{ $c }}=links">
                        <span class="label">الروابط</span>
                        <div data-list>
                            @foreach ($col['links'] ?? [] as $i => $link)@include('admin.layout._link', ['prefix' => "columns[$c][links]", 'i' => $i, 'link' => $link])@endforeach
                        </div>
                        <template>@include('admin.layout._link', ['prefix' => "columns[$c][links]", 'i' => '__INDEX__', 'link' => []])</template>
                        <button class="btn btn--outline btn--sm" type="button" data-add><x-icon name="plus" size="16"/> رابط</button>
                    </div>
                </fieldset>
            @endfor
        </div>

        <h3 style="margin-top:18px">الشريط السفلي</h3>
        <x-admin.tr name="footer_copyright" label="نص الحقوق" :value="$s['footer_copyright']" hint="استعمل {year} للسنة الحالية و {site} لاسم الأكاديمية."/>
        <div data-repeater class="field">
            <span class="label">روابط صغيرة (مثل: الشروط، الخصوصية)</span>
            <div data-list>
                @foreach ($s['footer_bottom_links'] ?? [] as $i => $link)@include('admin.layout._link', ['prefix' => 'bottom_links', 'i' => $i, 'link' => $link])@endforeach
            </div>
            <template>@include('admin.layout._link', ['prefix' => 'bottom_links', 'i' => '__INDEX__', 'link' => []])</template>
            <button class="btn btn--outline btn--sm" type="button" data-add><x-icon name="plus" size="16"/> رابط</button>
        </div>
        <label class="check field"><input type="checkbox" name="footer_show_back_top" value="1" @checked($s['footer_show_back_top'])> زر «إلى الأعلى»</label>
    </section>

    <div class="sticky-save"><button class="btn btn--primary btn--lg" type="submit">حفظ ونشر</button></div>
</form>
@endsection
