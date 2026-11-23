@extends('layouts.admin')
@section('title', 'الإعدادات')
@section('content')
@php
    $sectionLabels = ['hero' => 'الواجهة والبحث', 'stats' => 'الإحصائيات (من قاعدة البيانات)', 'categories' => 'المجالات', 'featured' => 'الدورات المميزة', 'how' => 'كيف تعمل الأكاديمية', 'recent' => 'أحدث الدورات', 'benefits' => 'المزايا والاعتماد', 'testimonials' => 'آراء الطلاب المعتمدة', 'articles' => 'أحدث المقالات', 'partners' => 'شعارات الشركاء', 'cta' => 'الدعوة الختامية'];
@endphp
<div class="page-head"><h1>الإعدادات</h1></div>
<nav class="tabs">@foreach ($groups as $k => $label)<a href="{{ route('admin.settings.edit', $k) }}" class="{{ $group === $k ? 'is-active' : '' }}">{{ $label }}</a>@endforeach</nav>

<form class="card card--pad" style="max-width:900px" method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @switch($group)
        @case('general')
            <x-admin.tr name="site_name" label="اسم الأكاديمية" :value="$s['site_name']" required :max="80"/>
            <x-admin.tr name="tagline" label="الشعار النصي" :value="$s['tagline']"/>
            <x-admin.tr name="topbar_text" label="نص الشريط العلوي" :value="$s['topbar_text']"/>
            <x-admin.tr name="footer_about" label="نبذة التذييل" :value="$s['footer_about']" type="textarea" :rows="3"/>
            <x-admin.tr name="seo_description" label="الوصف الافتراضي لمحركات البحث" :value="$s['seo_description']" type="textarea" :rows="2"/>
            <x-admin.tr name="contact_address" label="العنوان" :value="$s['contact_address']"/>
            <div class="form-grid">
                <div class="field"><label for="contact_email">البريد العام</label><input class="input ltr" type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $s['contact_email']) }}"></div>
                <div class="field"><label for="contact_phone">الهاتف العام</label><input class="input ltr" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $s['contact_phone']) }}"></div>
                <div class="field"><label for="logo">الشعار (PNG/WebP)</label><input class="input" type="file" id="logo" name="logo" accept="image/png,image/webp,image/jpeg">
                    @if ($s['logo_path'])<label class="check small"><input type="checkbox" name="remove_logo" value="1"> استعمال الشعار الافتراضي</label>@endif</div>
                <div class="field"><label for="favicon">أيقونة المتصفح (PNG مربع)</label><input class="input" type="file" id="favicon" name="favicon" accept="image/png"></div>
            </div>
            @break
        @case('whatsapp')
            <div class="field"><label for="whatsapp_number">رقم واتساب الأكاديمية (بالصيغة الدولية)</label><input class="input ltr" id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $s['whatsapp_number']) }}" placeholder="212600000000">
                <p class="hint">بدون + أو مسافات. يُستعمل في زر «اطلب شهادتك عبر واتساب» والزر العائم.</p></div>
            <div class="field"><label for="whatsapp_template">نص رسالة طلب الشهادة</label><textarea class="textarea" id="whatsapp_template" name="whatsapp_template" rows="4" dir="rtl" required>{{ old('whatsapp_template', $s['whatsapp_template']) }}</textarea>
                <p class="hint">استعمل {course} لاسم الدورة و {reference} لرقم الطلب. لا تضف العنوان؛ يُؤكَّد داخل المحادثة.</p></div>
            <label class="check field"><input type="checkbox" name="certificate_requires_completion" value="1" @checked($s['certificate_requires_completion'] ?? false)> <span>اشتراط إكمال الدورة (الدروس المطلوبة والاختبارات إن وُجدت) قبل طلب الشهادة<br><small class="muted">عند إلغاء التفعيل يستطيع الطالب طلب الشهادة عبر واتساب في أي وقت، حتى قبل بدء الدورة. يظهر تقدّمه لفريقك في صفحة الطلب.</small></span></label>
            <label class="check field"><input type="checkbox" name="require_verified_email_for_certificate" value="1" @checked($s['require_verified_email_for_certificate'])> اشتراط تأكيد البريد الإلكتروني قبل طلب الشهادة (فعّله فقط بعد ضبط إعدادات البريد MAIL_* في ملف .env)</label>
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">البيانات الظاهرة في صفحة التحقق العامة</legend>
                @foreach (['holder_name' => 'اسم الحاصل على الشهادة', 'course_title' => 'عنوان الدورة', 'issued_on' => 'تاريخ الإصدار'] as $k => $l)
                    <label class="check small"><input type="checkbox" name="verify_public_fields[]" value="{{ $k }}" @checked(in_array($k, $s['verify_public_fields'] ?? []))> {{ $l }}</label>
                @endforeach
                <p class="hint">رمز التحقق والحالة يظهران دائمًا. بيانات الشحن والملاحظات لا تظهر أبدًا.</p>
            </fieldset>
            @break
        @case('features')
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">الوحدات الظاهرة (تختفي من القائمة والتذييل وخريطة الموقع عند تعطيلها)</legend>
                @foreach (['blog' => 'المدونة والأخبار', 'library' => 'المكتبة', 'tools' => 'الأدوات', 'verification' => 'التحقق من الشهادة', 'store' => 'المتجر الرقمي (اختياري، معطّل افتراضيًا)'] as $k => $l)
                    <label class="check"><input type="checkbox" name="features[{{ $k }}]" value="1" @checked($s['features'][$k] ?? false)> {{ $l }}</label>
                @endforeach
            </fieldset>
            <div data-repeater class="field">
                <div class="label">روابط إضافية في القائمة الرئيسية</div>
                <div data-list>
                    @foreach (old('links', $s['nav_extra_links'] ?? []) as $i => $link)
                        @include('admin.settings._link', ['i' => $i, 'link' => $link])
                    @endforeach
                </div>
                <template>@include('admin.settings._link', ['i' => '__INDEX__', 'link' => ['url' => '', 'label' => []]])</template>
                <button class="btn btn--outline btn--sm" type="button" data-add><x-icon name="plus" size="16"/> إضافة رابط</button>
            </div>
            @break
        @case('home')
            <p class="muted small">فعّل الأقسام ورتّبها (الرقم الأصغر يظهر أولًا). الأقسام الفارغة تختفي تلقائيًا.</p>
            <div class="table-wrap" style="margin-bottom:16px"><table class="table"><thead><tr><th>القسم</th><th>مفعّل</th><th>الترتيب</th></tr></thead><tbody>
                @foreach (\App\Support\Settings::homeSections() as $i => $sec)
                    <tr><td>{{ $sectionLabels[$sec['key']] ?? $sec['key'] }}<input type="hidden" name="sections[{{ $i }}][key]" value="{{ $sec['key'] }}"></td>
                        <td><input type="checkbox" name="sections[{{ $i }}][enabled]" value="1" @checked($sec['enabled']) aria-label="تفعيل {{ $sectionLabels[$sec['key']] ?? '' }}"></td>
                        <td><input class="input" type="number" name="sections[{{ $i }}][order]" value="{{ $i + 1 }}" style="max-width:90px" aria-label="ترتيب {{ $sectionLabels[$sec['key']] ?? '' }}"></td></tr>
                @endforeach
            </tbody></table></div>
            <label class="check field"><input type="checkbox" name="accreditation_enabled" value="1" @checked($s['accreditation_enabled'])> إظهار نص الاعتماد</label>
            <x-admin.tr name="accreditation_text" label="نص الاعتماد (اكتب فقط اعتمادات حقيقية وموثّقة)" :value="$s['accreditation_text']" type="textarea" :rows="2"/>
            @break
        @case('social')
            <p class="muted small">الروابط الفارغة لا تظهر في الموقع.</p>
            @foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'x' => 'X'] as $k => $l)
                <div class="field"><label for="s-{{ $k }}">{{ $l }}</label><input class="input ltr" type="url" id="s-{{ $k }}" name="social[{{ $k }}]" value="{{ old('social.'.$k, $s['social'][$k] ?? '') }}" placeholder="https://"></div>
            @endforeach
            @break
        @case('store')
            <p class="muted small">تُعرض هذه المعلومات للمشتري بعد إنشاء طلب في المتجر الرقمي فقط (الدورات والشهادات لا تتطلب أي دفع عبر الموقع).</p>
            <div class="form-grid">
                <div class="field"><label for="bank_name">اسم البنك</label><input class="input" id="bank_name" name="bank_name" value="{{ old('bank_name', $s['bank_name']) }}"></div>
                <div class="field"><label for="bank_account_holder">صاحب الحساب</label><input class="input" id="bank_account_holder" name="bank_account_holder" value="{{ old('bank_account_holder', $s['bank_account_holder']) }}"></div>
                <div class="field"><label for="bank_rib">RIB (24 رقمًا)</label><input class="input ltr" id="bank_rib" name="bank_rib" value="{{ old('bank_rib', $s['bank_rib']) }}"></div>
            </div>
            <div class="field"><label for="bank_instructions">تعليمات إضافية</label><textarea class="textarea" id="bank_instructions" name="bank_instructions" rows="3">{{ old('bank_instructions', $s['bank_instructions']) }}</textarea></div>
            @break
        @case('ai')
            <div class="alert alert--info"><x-icon name="info"/> <span>مولّد الاختبارات بالذكاء الاصطناعي اختياري. عند تفعيله، يُرسَل النص الذي يلصقه المشرف إلى Anthropic لتوليد أسئلة قابلة للتعديل. يُخزَّن المفتاح مشفّرًا. النموذج: <span class="ltr">{{ config('academy.ai.model') }}</span>.</span></div>
            <p>الحالة: @if (app(\App\Services\QuizGenerator::class)->enabled())<span class="badge badge--green">مفعّل</span>@else<span class="badge badge--gray">غير مفعّل</span>@endif</p>
            <div class="field"><label for="ai_api_key">مفتاح Anthropic API</label><input class="input ltr" type="password" id="ai_api_key" name="ai_api_key" autocomplete="off" placeholder="{{ \App\Support\Settings::get('ai_api_key') ? '•••••••• (محفوظ — اتركه فارغًا للإبقاء عليه)' : 'sk-ant-…' }}"></div>
            @if (\App\Support\Settings::get('ai_api_key'))<label class="check field"><input type="checkbox" name="remove_ai_key" value="1"> حذف المفتاح وتعطيل المولّد</label>@endif
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">المولّد العام في صفحة الأدوات</legend>
                <label class="check field"><input type="checkbox" name="ai_public_tool" value="1" @checked($s['ai_public_tool'] ?? true)> إتاحة «مولّد الاختبارات بالذكاء الاصطناعي» للمستخدمين المسجّلين في صفحة الأدوات</label>
                <div class="field"><label for="ai_public_daily_limit">الحد اليومي لكل مستخدم (عدد مرات التوليد)</label><input class="input" type="number" min="1" max="500" id="ai_public_daily_limit" name="ai_public_daily_limit" value="{{ old('ai_public_daily_limit', $s['ai_public_daily_limit'] ?? 10) }}" style="max-width:160px" required>
                    <p class="hint">كل عملية توليد تُحتسب على حسابك لدى Anthropic. الحد يحمي ميزانيتك. يقبل المولّد النصوص وملفات PDF وWord وترجمات الفيديو (SRT/VTT).</p></div>
            </fieldset>
            @break
    @endswitch
    <button class="btn btn--primary btn--lg" type="submit">حفظ الإعدادات</button>
</form>
@endsection
