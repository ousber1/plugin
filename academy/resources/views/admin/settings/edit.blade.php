@extends('layouts.admin')
@section('title', 'الإعدادات')
@section('content')
@php
    $sectionLabels = ['hero' => 'الواجهة والبحث', 'stats' => 'الإحصائيات (من قاعدة البيانات)', 'categories' => 'المجالات', 'featured' => 'الدورات المميزة', 'how' => 'كيف تعمل الأكاديمية', 'recent' => 'أحدث الدورات', 'benefits' => 'المزايا والاعتماد', 'accreditation' => 'الاعتماد (يُضبط من تبويب «الاعتماد»)', 'centers' => 'مراكز التكوين', 'testimonials' => 'آراء الطلاب المعتمدة', 'articles' => 'أحدث المقالات', 'partners' => 'شعارات الشركاء', 'cta' => 'الدعوة الختامية'];
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
            <x-admin.tr name="cert_button_label" label="نص زر طلب الشهادة في صفحة الدورة" :value="$s['cert_button_label'] ?? []" :max="60"/>
            <label class="check field"><input type="checkbox" name="certificate_requires_completion" value="1" @checked($s['certificate_requires_completion'] ?? false)> <span>اشتراط إكمال الدورة (الدروس المطلوبة والاختبارات إن وُجدت) قبل طلب الشهادة<br><small class="muted">عند إلغاء التفعيل يستطيع الطالب طلب الشهادة عبر واتساب في أي وقت، حتى قبل بدء الدورة. يظهر تقدّمه لفريقك في صفحة الطلب.</small></span></label>
            <label class="check field"><input type="checkbox" name="require_verified_email_for_certificate" value="1" @checked($s['require_verified_email_for_certificate'])> اشتراط تأكيد البريد الإلكتروني قبل طلب الشهادة (فعّله فقط بعد ضبط إعدادات البريد MAIL_* في ملف .env)</label>
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">البيانات الظاهرة في صفحة التحقق العامة</legend>
                @foreach (['holder_name' => 'اسم الحاصل على الشهادة', 'course_title' => 'عنوان الدورة', 'issued_on' => 'تاريخ الإصدار'] as $k => $l)
                    <label class="check small"><input type="checkbox" name="verify_public_fields[]" value="{{ $k }}" @checked(in_array($k, $s['verify_public_fields'] ?? []))> {{ $l }}</label>
                @endforeach
                <p class="hint">رمز التحقق والحالة يظهران دائمًا. بيانات الشحن والملاحظات لا تظهر أبدًا.</p>
            </fieldset>
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">زر واتساب العائم (في كل صفحات الموقع)</legend>
                <label class="check field"><input type="checkbox" name="wa_float_enabled" value="1" @checked($s['wa_float_enabled'] ?? true)> إظهار الزر العائم</label>
                <div class="form-grid">
                    <div class="field"><label for="wa_float_side">المكان</label><select class="select" id="wa_float_side" name="wa_float_side">
                        @foreach (['auto' => 'تلقائي (حسب اتجاه اللغة)', 'left' => 'يسار الشاشة', 'right' => 'يمين الشاشة'] as $k => $l)<option value="{{ $k }}" @selected(($s['wa_float_side'] ?? 'auto') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label class="check" style="margin-top:28px"><input type="checkbox" name="wa_float_show_label" value="1" @checked($s['wa_float_show_label'] ?? true)> إظهار نص بجانب الأيقونة (على الحاسوب)</label>
                        <label class="check"><input type="checkbox" name="wa_float_pulse" value="1" @checked($s['wa_float_pulse'] ?? true)> حركة نبض لجذب الانتباه</label></div>
                </div>
                <x-admin.tr name="wa_float_label" label="نص الزر" :value="$s['wa_float_label'] ?? []" :max="40"/>
                <x-admin.tr name="wa_float_message" label="الرسالة الجاهزة عند فتح واتساب" :value="$s['wa_float_message'] ?? []" type="textarea" :rows="2"/>
            </fieldset>
            @break
        @case('accreditation')
            @php
                $facts = array_values($s['accreditation_facts'] ?? []);
                while (count($facts) < 4) { $facts[] = ['icon' => ['hash', 'globe', 'award', 'calendar'][count($facts)], 'label' => [], 'value' => []]; }
                $icons = ['hash' => '#', 'globe' => 'بلد / عالمي', 'award' => 'شهادة', 'shield' => 'درع', 'calendar' => 'تاريخ', 'check-circle' => 'علامة صح', 'map-pin' => 'مكان', 'users' => 'أشخاص'];
            @endphp
            <div class="alert alert--warning small"><x-icon name="alert"/> <span>اكتب فقط اعتمادات حقيقية وموثّقة، وارفع وثيقة الاعتماد ليطّلع عليها الزوار. الاعتمادات غير الصحيحة تضر بثقة الطلاب وقد تعرّضك للمساءلة.</span></div>
            <label class="check field"><input type="checkbox" name="accreditation_enabled" value="1" @checked($s['accreditation_enabled'])> إظهار قسم الاعتماد في الصفحة الرئيسية</label>
            <label class="check field"><input type="checkbox" name="accreditation_on_verify" value="1" @checked($s['accreditation_on_verify'] ?? true)> إظهاره أيضًا في صفحة «التحقق من الشهادة»</label>
            <x-admin.tr name="accreditation_badge" label="الشارة الصغيرة" :value="$s['accreditation_badge']" :max="60"/>
            <x-admin.tr name="accreditation_title" label="العنوان" :value="$s['accreditation_title']" hint="مثال: شهاداتنا معتمدة من …"/>
            <x-admin.tr name="accreditation_text" label="النص" :value="$s['accreditation_text']" type="textarea" :rows="3"/>
            <div class="form-grid">
                <div class="field"><label for="accreditation_logo">شعار الجهة المعتمِدة</label><input class="input" type="file" id="accreditation_logo" name="accreditation_logo" accept="image/*">
                    @if ($s['accreditation_logo_path'])<img src="{{ \App\Support\Media::url($s['accreditation_logo_path']) }}" alt="" style="max-height:80px;margin-top:6px"><label class="check small"><input type="checkbox" name="remove_accreditation_logo" value="1"> حذف</label>@endif</div>
                <div class="field"><label for="accreditation_doc">وثيقة الاعتماد (PDF أو صورة)</label><input class="input" type="file" id="accreditation_doc" name="accreditation_doc" accept="application/pdf,image/*">
                    @if ($s['accreditation_doc_path'])<a class="small" href="{{ \App\Support\Media::url($s['accreditation_doc_path']) }}" target="_blank">عرض الوثيقة الحالية</a> <label class="check small"><input type="checkbox" name="remove_accreditation_doc" value="1"> حذف</label>@endif
                    <p class="hint">أو ضع رابطًا خارجيًا (موقع الجهة المعتمِدة):</p>
                    <input class="input ltr" type="url" name="accreditation_doc_url" value="{{ old('accreditation_doc_url', $s['accreditation_doc_url']) }}" placeholder="https://" aria-label="رابط وثيقة الاعتماد"></div>
            </div>
            <x-admin.tr name="accreditation_doc_label" label="نص زر الوثيقة" :value="$s['accreditation_doc_label']" :max="60"/>
            <label class="check field"><input type="checkbox" name="accreditation_verify_button" value="1" @checked($s['accreditation_verify_button'] ?? true)> زر «تحقق من صحة شهادة»</label>
            <fieldset class="box"><legend class="label">معلومات الاعتماد (حتى 4، مثل: رقم التسجيل، بلد التسجيل، نوع الشهادة)</legend>
                @foreach ($facts as $i => $f)
                    <div class="repeater-item" style="flex-wrap:wrap;align-items:end">
                        <div class="field" style="flex:0 1 130px"><label for="fi{{ $i }}">الأيقونة</label><select class="select" id="fi{{ $i }}" name="facts[{{ $i }}][icon]">@foreach ($icons as $k => $l)<option value="{{ $k }}" @selected(($f['icon'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="field" style="flex:1 1 150px"><label for="fl{{ $i }}">العنوان (عربي)</label><input class="input" id="fl{{ $i }}" name="facts[{{ $i }}][label][ar]" value="{{ $f['label']['ar'] ?? '' }}" placeholder="رقم التسجيل"></div>
                        <div class="field" style="flex:1 1 150px"><label for="fv{{ $i }}">القيمة (عربي)</label><input class="input" id="fv{{ $i }}" name="facts[{{ $i }}][value][ar]" value="{{ $f['value']['ar'] ?? '' }}" placeholder="N° 15636127"></div>
                        <div class="field" style="flex:1 1 130px"><label for="flf{{ $i }}">Libellé (FR)</label><input class="input" id="flf{{ $i }}" dir="ltr" name="facts[{{ $i }}][label][fr]" value="{{ $f['label']['fr'] ?? '' }}"></div>
                        <div class="field" style="flex:1 1 130px"><label for="fvf{{ $i }}">Valeur (FR)</label><input class="input" id="fvf{{ $i }}" dir="ltr" name="facts[{{ $i }}][value][fr]" value="{{ $f['value']['fr'] ?? '' }}"></div>
                        <input type="hidden" name="facts[{{ $i }}][label][en]" value="{{ $f['label']['en'] ?? '' }}"><input type="hidden" name="facts[{{ $i }}][value][en]" value="{{ $f['value']['en'] ?? '' }}">
                    </div>
                @endforeach
                <p class="hint">اترك القيمة فارغة لإخفاء السطر. النسخة الإنجليزية تستعمل الفرنسية عند غيابها.</p>
            </fieldset>
            @break
        @case('features')
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">الوحدات الظاهرة (تختفي من القائمة والتذييل وخريطة الموقع عند تعطيلها)</legend>
                @foreach (['blog' => 'المدونة والأخبار', 'library' => 'المكتبة', 'tools' => 'الأدوات', 'verification' => 'التحقق من الشهادة', 'centers' => 'مراكز التكوين', 'store' => 'المتجر الرقمي (اختياري، معطّل افتراضيًا)'] as $k => $l)
                    <label class="check"><input type="checkbox" name="features[{{ $k }}]" value="1" @checked($s['features'][$k] ?? ($k === 'centers'))> {{ $l }}</label>
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
            <p class="hint">قسم الاعتماد يُضبط من تبويب <a href="{{ route('admin.settings.edit', 'accreditation') }}">الاعتماد</a>.</p>
            @break
        @case('social')
            <p class="muted small">الروابط الفارغة لا تظهر في الموقع.</p>
            @foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'x' => 'X'] as $k => $l)
                <div class="field"><label for="s-{{ $k }}">{{ $l }}</label><input class="input ltr" type="url" id="s-{{ $k }}" name="social[{{ $k }}]" value="{{ old('social.'.$k, $s['social'][$k] ?? '') }}" placeholder="https://"></div>
            @endforeach
            @break
        @case('store')
            @php $accounts = \App\Support\Shop::bankAccounts() ?: [['bank' => '', 'holder' => '', 'rib' => '']]; @endphp
            <p class="muted small">المتجر مستقل عن الدورات المجانية والشهادات. فعّله من «الوحدات والقائمة». المنتجات: <a href="{{ route('admin.products.index') }}">إدارة المنتجات</a>.</p>
            <x-admin.tr name="store_title" label="عنوان صفحة المتجر" :value="$s['store_title']"/>
            <x-admin.tr name="store_subtitle" label="وصف صفحة المتجر" :value="$s['store_subtitle']" type="textarea" :rows="2"/>
            <fieldset class="box"><legend class="label">العملات</legend>
                <div class="form-grid">
                    <div class="field"><label for="store_default_currency">العملة الافتراضية</label><select class="select" id="store_default_currency" name="store_default_currency"><option value="MAD" @selected($s['store_default_currency'] === 'MAD')>الدرهم المغربي (MAD)</option><option value="USD" @selected($s['store_default_currency'] === 'USD')>الدولار (USD)</option></select></div>
                    <div class="field"><label for="store_usd_rate">سعر الدولار بالدرهم</label><input class="input ltr" type="number" step="0.01" min="0.1" id="store_usd_rate" name="store_usd_rate" value="{{ old('store_usd_rate', $s['store_usd_rate']) }}">
                        <p class="hint">يُستعمل للمنتجات التي ليس لها سعر بالدولار. مثال: 10 يعني 499 درهم ≈ 50$.</p></div>
                </div>
                <label class="check"><input type="checkbox" name="store_usd_enabled" value="1" @checked($s['store_usd_enabled'])> السماح للزبائن بالدفع بالدولار</label>
            </fieldset>
            <fieldset class="box"><legend class="label">طرق الدفع</legend>
                <label class="check field"><input type="checkbox" name="pay_bank_enabled" value="1" @checked($s['pay_bank_enabled'])> <strong>تحويل بنكي</strong> (بالدرهم)</label>
                <div data-repeater class="field">
                    <div data-list>
                        @foreach ($accounts as $i => $a)
                            <div class="repeater-item" data-item style="flex-wrap:wrap">
                                <input class="input" style="flex:1 1 150px" name="bank_accounts[{{ $i }}][bank]" value="{{ $a['bank'] }}" placeholder="البنك (CIH، Attijariwafa…)" aria-label="البنك">
                                <input class="input" style="flex:1 1 150px" name="bank_accounts[{{ $i }}][holder]" value="{{ $a['holder'] }}" placeholder="صاحب الحساب" aria-label="صاحب الحساب">
                                <input class="input ltr" style="flex:2 1 220px" name="bank_accounts[{{ $i }}][rib]" value="{{ $a['rib'] }}" placeholder="RIB — 24 رقمًا" aria-label="RIB">
                                <button class="btn btn--ghost btn--sm" type="button" data-remove>حذف</button>
                            </div>
                        @endforeach
                    </div>
                    <template><div class="repeater-item" data-item style="flex-wrap:wrap">
                        <input class="input" style="flex:1 1 150px" name="bank_accounts[__INDEX__][bank]" placeholder="البنك" aria-label="البنك">
                        <input class="input" style="flex:1 1 150px" name="bank_accounts[__INDEX__][holder]" placeholder="صاحب الحساب" aria-label="صاحب الحساب">
                        <input class="input ltr" style="flex:2 1 220px" name="bank_accounts[__INDEX__][rib]" placeholder="RIB" aria-label="RIB">
                        <button class="btn btn--ghost btn--sm" type="button" data-remove>حذف</button></div></template>
                    <button class="btn btn--outline btn--sm" type="button" data-add><x-icon name="plus" size="16"/> حساب بنكي آخر</button>
                </div>
                <div class="field"><label for="bank_instructions">تعليمات إضافية</label><textarea class="textarea" id="bank_instructions" name="bank_instructions" rows="2">{{ old('bank_instructions', $s['bank_instructions']) }}</textarea></div>
                <hr style="border:0;border-top:1px solid var(--border)">
                <label class="check field"><input type="checkbox" name="pay_agency_enabled" value="1" @checked($s['pay_agency_enabled'])> <strong>وكالات تحويل الأموال</strong> (بالدرهم)</label>
                <div class="form-grid">
                    <div class="field"><label for="agency_names">الوكالات المقبولة</label><input class="input" id="agency_names" name="agency_names" value="{{ old('agency_names', $s['agency_names']) }}"></div>
                    <div class="field"><label for="agency_receiver">اسم المستفيد (كما في البطاقة الوطنية)</label><input class="input" id="agency_receiver" name="agency_receiver" value="{{ old('agency_receiver', $s['agency_receiver']) }}"></div>
                    <div class="field"><label for="agency_city">المدينة</label><input class="input" id="agency_city" name="agency_city" value="{{ old('agency_city', $s['agency_city']) }}"></div>
                    <div class="field"><label for="agency_phone">هاتف المستفيد</label><input class="input ltr" id="agency_phone" name="agency_phone" value="{{ old('agency_phone', $s['agency_phone']) }}"></div>
                </div>
                <hr style="border:0;border-top:1px solid var(--border)">
                <label class="check field"><input type="checkbox" name="pay_whatsapp_enabled" value="1" @checked($s['pay_whatsapp_enabled'])> <strong>الدفع عبر واتساب</strong> (تتفقون على الطريقة في المحادثة)</label>
                <label class="check field"><input type="checkbox" name="pay_paypal_enabled" value="1" @checked($s['pay_paypal_enabled'])> <strong>PayPal / بطاقة</strong> (بالدولار)</label>
                <div class="field"><label for="paypal_link">رابط PayPal.me أو رابط الدفع</label><input class="input ltr" type="url" id="paypal_link" name="paypal_link" value="{{ old('paypal_link', $s['paypal_link']) }}" placeholder="https://paypal.me/yourname"></div>
            </fieldset>
            <label class="check field"><input type="checkbox" name="store_reviews_moderated" value="1" @checked($s['store_reviews_moderated'])> مراجعة تقييمات المنتجات قبل نشرها</label>
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
        @case('appearance')
            @php $themes = \App\Support\Themes::all(); $current = $s['site_theme'] ?? 'nibras'; @endphp
            <h2 class="card__title" style="margin-top:0">الثيمات الجاهزة</h2>
            <p class="muted small">اختر ثيمًا بنقرة واحدة: يغيّر الألوان وشكل الهيدر والأزرار والبطاقات والواجهة معًا. يمكنك بعدها تعديل أي تفصيل أسفله.</p>
            <div class="theme-gallery">
                @foreach ($themes as $key => $t)
                    <div class="theme-card {{ $current === $key ? 'is-current' : '' }}" style="--tp:{{ $t['primary'] }};--ta:{{ $t['accent'] }};--tb:{{ $t['bg'] }};--tf:{{ $t['footer'] }}">
                        <div class="theme-card__preview theme-card__preview--{{ $t['header'] }} theme-card__preview--{{ $t['hero'] }} theme-card__preview--{{ $t['buttons'] }}" aria-hidden="true">
                            <div class="tp-header"><span class="tp-logo"></span><span class="tp-links"><i></i><i></i><i></i></span><span class="tp-btn"></span></div>
                            <div class="tp-hero"><b></b><b class="short"></b><span class="tp-btn tp-btn--lg"></span></div>
                            <div class="tp-cards"><span></span><span></span><span></span></div>
                            <div class="tp-footer"></div>
                        </div>
                        <div class="theme-card__body">
                            <div><strong>{{ $t['name'] }}</strong> @if ($current === $key)<span class="badge badge--green">الحالي</span>@endif
                                <p class="small muted" style="margin:2px 0 0">{{ $t['desc'] }}</p></div>
                            <button class="btn btn--sm {{ $current === $key ? 'btn--ghost' : 'btn--primary' }}" type="submit" form="theme-{{ $key }}">{{ $current === $key ? 'إعادة تطبيق' : 'تطبيق' }}</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <h2 class="card__title" style="margin-top:26px">تخصيص دقيق</h2>
            <div class="form-grid">
                <div class="field"><label for="theme_primary">اللون الأساسي</label><input class="input" type="color" id="theme_primary" name="theme_primary" value="{{ $s['theme_primary'] }}" style="height:46px;padding:4px"></div>
                <div class="field"><label for="theme_accent">لون التمييز (أزرار الدعوة)</label><input class="input" type="color" id="theme_accent" name="theme_accent" value="{{ $s['theme_accent'] }}" style="height:46px;padding:4px"></div>
                <div class="field"><label for="theme_bg">لون خلفية الصفحات</label><input class="input" type="color" id="theme_bg" name="theme_bg" value="{{ $s['theme_bg'] }}" style="height:46px;padding:4px"></div>
                <div class="field"><label for="theme_footer">لون التذييل الداكن</label><input class="input" type="color" id="theme_footer" name="theme_footer" value="{{ $s['theme_footer'] }}" style="height:46px;padding:4px"></div>
                @foreach (['header_style' => ['شكل الهيدر', \App\Support\Themes::HEADER], 'hero_style' => ['خلفية الواجهة الرئيسية', \App\Support\Themes::HEROES], 'button_shape' => ['شكل الأزرار', \App\Support\Themes::BUTTONS], 'card_style' => ['شكل البطاقات', \App\Support\Themes::CARDS], 'footer_style' => ['لون التذييل', \App\Support\Themes::FOOTERS]] as $field => [$label, $options])
                    <div class="field"><label for="{{ $field }}">{{ $label }}</label><select class="select" id="{{ $field }}" name="{{ $field }}">@foreach ($options as $k => $l)<option value="{{ $k }}" @selected(($s[$field] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                @endforeach
                <div class="field"><label for="theme_radius">زوايا البطاقات</label><select class="select" id="theme_radius" name="theme_radius">@foreach (['square' => 'حادة', 'normal' => 'متوسطة', 'round' => 'دائرية'] as $k => $l)<option value="{{ $k }}" @selected($s['theme_radius'] === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="theme_font">الخط</label><select class="select" id="theme_font" name="theme_font"><option value="tajawal" @selected($s['theme_font'] === 'tajawal')>Tajawal + Inter (افتراضي)</option><option value="system" @selected($s['theme_font'] === 'system')>خط الجهاز (أسرع)</option></select></div>
            </div>
            <p class="hint">الهيدر والتذييل (النصوص، الأعمدة، زر «اطلب شهادتك الآن»): <a href="{{ route('admin.layout.edit') }}">الهيدر والتذييل</a>.</p>
            <div class="field"><label for="custom_css">CSS مخصص (متقدم)</label><textarea class="textarea code-area" id="custom_css" name="custom_css" rows="6" dir="ltr" placeholder=".hero { ... }">{{ old('custom_css', $s['custom_css']) }}</textarea></div>
            <label class="check field"><input type="checkbox" name="reset_colors" value="1"> استرجاع التصميم الافتراضي (ثيم نبراس)</label>
            @break
        @case('advanced')
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">وضع الصيانة</legend>
                <label class="check field"><input type="checkbox" name="maintenance_enabled" value="1" @checked($s['maintenance_enabled'])> تفعيل وضع الصيانة (يبقى الموقع متاحًا لك وللمدرّسين بعد تسجيل الدخول)</label>
                <x-admin.tr name="maintenance_message" label="رسالة الصيانة" :value="$s['maintenance_message']" type="textarea" :rows="2"/>
            </fieldset>
            <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">تنبيه ملفات تعريف الارتباط</legend>
                <label class="check field"><input type="checkbox" name="cookie_banner" value="1" @checked($s['cookie_banner'])> إظهار تنبيه ملفات تعريف الارتباط للزوار</label>
                <x-admin.tr name="cookie_text" label="نص التنبيه" :value="$s['cookie_text']" type="textarea" :rows="2"/>
            </fieldset>
            <div class="alert alert--warning small"><x-icon name="alert"/> <span>الأكواد التالية تُضاف كما هي إلى كل الصفحات (مثل Google Analytics أو Meta Pixel). أضف فقط أكوادًا من مصادر موثوقة.</span></div>
            <div class="field"><label for="head_code">كود داخل &lt;head&gt;</label><textarea class="textarea code-area" id="head_code" name="head_code" rows="5" dir="ltr">{{ old('head_code', $s['head_code']) }}</textarea></div>
            <div class="field"><label for="body_code">كود قبل نهاية &lt;/body&gt;</label><textarea class="textarea code-area" id="body_code" name="body_code" rows="5" dir="ltr">{{ old('body_code', $s['body_code']) }}</textarea></div>
            @break
    @endswitch
    <button class="btn btn--primary btn--lg" type="submit">حفظ الإعدادات</button>
</form>
@if ($group === 'appearance')
    @foreach (\App\Support\Themes::all() as $key => $t)
        <form id="theme-{{ $key }}" method="POST" action="{{ route('admin.themes.apply', $key) }}" hidden>@csrf</form>
    @endforeach
@endif
@endsection
