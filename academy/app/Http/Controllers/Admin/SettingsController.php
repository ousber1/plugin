<?php

namespace App\Http\Controllers\Admin;

use App\Support\Audit;
use App\Support\Locales;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends AdminController
{
    private const GROUPS = [
        'general' => 'الهوية والتواصل',
        'appearance' => 'الثيمات والألوان',
        'whatsapp' => 'واتساب والشهادات',
        'features' => 'الوحدات والقائمة',
        'home' => 'الصفحة الرئيسية',
        'social' => 'الشبكات الاجتماعية',
        'store' => 'المتجر والدفع',
        'ai' => 'الذكاء الاصطناعي',
        'advanced' => 'متقدم',
    ];

    public function edit(?string $group = 'general')
    {
        abort_unless(array_key_exists($group ?? 'general', self::GROUPS), 404);

        return view('admin.settings.edit', ['group' => $group ?? 'general', 'groups' => self::GROUPS, 's' => Settings::all()]);
    }

    public function update(Request $request, string $group)
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);
        $values = match ($group) {
            'general' => $this->general($request),
            'whatsapp' => $this->whatsapp($request),
            'features' => $this->features($request),
            'home' => $this->home($request),
            'social' => $this->social($request),
            'store' => $this->store($request),
            'ai' => $this->ai($request),
            'appearance' => $this->appearance($request),
            'advanced' => $this->advanced($request),
        };
        Settings::set($values);
        Audit::log('settings.updated', null, ['group' => $group, 'keys' => array_keys($values)]);

        return back()->with('success', 'تم حفظ الإعدادات.');
    }

    private function general(Request $request): array
    {
        $request->validate([
            ...$this->trRules('site_name', true, 80), ...$this->trRules('tagline', false, 200), ...$this->trRules('topbar_text', false, 200),
            ...$this->trRules('footer_about', false, 500), ...$this->trRules('contact_address', false, 200), ...$this->trRules('seo_description', false, 320),
            'contact_email' => ['nullable', 'email', 'max:190'], 'contact_phone' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'], 'favicon' => ['nullable', 'image', 'mimes:png', 'max:256'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);
        $values = [];
        foreach (['site_name', 'tagline', 'topbar_text', 'footer_about', 'contact_address', 'seo_description'] as $k) {
            $values[$k] = $this->trInput($request, $k);
        }
        $values['contact_email'] = $request->input('contact_email');
        $values['contact_phone'] = $request->input('contact_phone');
        if ($request->boolean('remove_logo') && Settings::get('logo_path')) {
            Storage::disk('public')->delete(Settings::get('logo_path'));
            $values['logo_path'] = null;
        }
        if ($request->hasFile('logo')) {
            $values['logo_path'] = $request->file('logo')->store('branding', 'public');
        }
        if ($request->hasFile('favicon')) {
            $values['favicon_path'] = $request->file('favicon')->store('branding', 'public');
        }

        return $values;
    }

    private function whatsapp(Request $request): array
    {
        $data = $request->validate([
            'whatsapp_number' => ['nullable', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'whatsapp_template' => ['required', 'string', 'max:1000', function ($attr, $value, $fail) {
                if (! str_contains($value, '{course}') || ! str_contains($value, '{reference}')) {
                    $fail('يجب أن يحتوي القالب على {course} و {reference}.');
                }
            }],
            'require_verified_email_for_certificate' => ['nullable', 'boolean'],
            'certificate_requires_completion' => ['nullable', 'boolean'],
            'verify_public_fields' => ['nullable', 'array'], 'verify_public_fields.*' => ['in:holder_name,course_title,issued_on'],
            'wa_float_side' => ['nullable', 'in:auto,left,right'],
            ...$this->trRules('wa_float_label', false, 40), ...$this->trRules('wa_float_message', false, 300),
            ...$this->trRules('cert_button_label', false, 60),
        ], ['whatsapp_number.regex' => 'أدخل الرقم بالصيغة الدولية بدون رموز، مثل 212600000000.']);

        return [
            'whatsapp_number' => preg_replace('/\D+/', '', (string) ($data['whatsapp_number'] ?? '')),
            'whatsapp_template' => $data['whatsapp_template'],
            'require_verified_email_for_certificate' => $request->boolean('require_verified_email_for_certificate'),
            'certificate_requires_completion' => $request->boolean('certificate_requires_completion'),
            'verify_public_fields' => array_values($data['verify_public_fields'] ?? []),
            'wa_float_enabled' => $request->boolean('wa_float_enabled'),
            'wa_float_side' => $data['wa_float_side'] ?? 'auto',
            'wa_float_show_label' => $request->boolean('wa_float_show_label'),
            'wa_float_pulse' => $request->boolean('wa_float_pulse'),
            'wa_float_label' => $this->trInput($request, 'wa_float_label'),
            'wa_float_message' => $this->trInput($request, 'wa_float_message'),
            'cert_button_label' => $this->trInput($request, 'cert_button_label'),
        ];
    }

    private function features(Request $request): array
    {
        $request->validate([
            'links' => ['nullable', 'array', 'max:6'],
            'links.*.url' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/|\/)/'],
            'links.*.label' => ['nullable', 'array'],
            'links.*.label.*' => ['nullable', 'string', 'max:40'],
        ], ['links.*.url.regex' => 'يجب أن يبدأ الرابط بـ https:// أو /']);
        $features = [];
        foreach (['blog', 'library', 'tools', 'store', 'verification', 'centers'] as $f) {
            $features[$f] = $request->boolean("features.$f");
        }
        $links = collect($request->input('links', []))->filter(fn ($l) => ! empty($l['url']) && ! empty($l['label']['ar']))
            ->map(fn ($l) => ['url' => $l['url'], 'label' => collect(Locales::codes())->mapWithKeys(fn ($c) => [$c => trim($l['label'][$c] ?? '')])->all()])->values()->all();

        return ['features' => $features, 'nav_extra_links' => $links];
    }

    private function home(Request $request): array
    {
        $request->validate([
            'sections' => ['required', 'array'], 'sections.*.key' => ['required', 'string'], 'sections.*.order' => ['nullable', 'integer'],
            ...$this->trRules('accreditation_text', false, 400),
        ]);
        $known = array_column(Settings::defaults()['home_sections'], 'key');
        $sections = collect($request->input('sections'))->filter(fn ($s) => in_array($s['key'], $known, true))
            ->sortBy(fn ($s) => (int) ($s['order'] ?? 0))
            ->map(fn ($s) => ['key' => $s['key'], 'enabled' => ! empty($s['enabled'])])->values()->all();

        return [
            'home_sections' => $sections,
            'accreditation_enabled' => $request->boolean('accreditation_enabled'),
            'accreditation_text' => $this->trInput($request, 'accreditation_text'),
        ];
    }

    private function social(Request $request): array
    {
        $networks = ['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin', 'x'];
        $request->validate(collect($networks)->mapWithKeys(fn ($n) => ["social.$n" => ['nullable', 'url', 'max:255']])->all());

        return ['social' => collect($networks)->mapWithKeys(fn ($n) => [$n => $request->input("social.$n") ?: null])->all()];
    }

    private function store(Request $request): array
    {
        $data = $request->validate([
            ...$this->trRules('store_title', false, 80), ...$this->trRules('store_subtitle', false, 300),
            'store_default_currency' => ['required', 'in:MAD,USD'], 'store_usd_rate' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'bank_accounts' => ['nullable', 'array', 'max:6'], 'bank_accounts.*.bank' => ['nullable', 'string', 'max:120'],
            'bank_accounts.*.holder' => ['nullable', 'string', 'max:120'], 'bank_accounts.*.rib' => ['nullable', 'string', 'max:40'],
            'bank_instructions' => ['nullable', 'string', 'max:1000'],
            'agency_names' => ['nullable', 'string', 'max:160'], 'agency_receiver' => ['nullable', 'string', 'max:120'],
            'agency_city' => ['nullable', 'string', 'max:80'], 'agency_phone' => ['nullable', 'string', 'max:40'],
            'paypal_link' => ['nullable', 'url', 'max:255', 'regex:/^https:\/\//'],
        ]);
        $accounts = collect($data['bank_accounts'] ?? [])->filter(fn ($a) => trim((string) ($a['rib'] ?? '')) !== '')
            ->map(fn ($a) => ['bank' => trim((string) ($a['bank'] ?? '')), 'holder' => trim((string) ($a['holder'] ?? '')), 'rib' => trim($a['rib'])])->values()->all();

        return [
            'store_title' => $this->trInput($request, 'store_title'), 'store_subtitle' => $this->trInput($request, 'store_subtitle'),
            'store_default_currency' => $data['store_default_currency'], 'store_usd_enabled' => $request->boolean('store_usd_enabled'),
            'store_usd_rate' => (float) $data['store_usd_rate'], 'store_reviews_moderated' => $request->boolean('store_reviews_moderated'),
            'pay_bank_enabled' => $request->boolean('pay_bank_enabled'), 'pay_agency_enabled' => $request->boolean('pay_agency_enabled'),
            'pay_whatsapp_enabled' => $request->boolean('pay_whatsapp_enabled'), 'pay_paypal_enabled' => $request->boolean('pay_paypal_enabled'),
            'bank_accounts' => $accounts, 'bank_instructions' => $data['bank_instructions'] ?? null,
            // Keep the legacy single-account fields in sync with the first account.
            'bank_name' => $accounts[0]['bank'] ?? null, 'bank_account_holder' => $accounts[0]['holder'] ?? null, 'bank_rib' => $accounts[0]['rib'] ?? null,
            'agency_names' => $data['agency_names'] ?? '', 'agency_receiver' => $data['agency_receiver'] ?? '',
            'agency_city' => $data['agency_city'] ?? '', 'agency_phone' => $data['agency_phone'] ?? '', 'paypal_link' => $data['paypal_link'] ?? '',
        ];
    }

    private function ai(Request $request): array
    {
        $request->validate([
            'ai_api_key' => ['nullable', 'string', 'max:300'], 'remove_ai_key' => ['nullable', 'boolean'],
            'ai_public_tool' => ['nullable', 'boolean'], 'ai_public_daily_limit' => ['required', 'integer', 'between:1,500'],
        ]);
        $values = [
            'ai_public_tool' => $request->boolean('ai_public_tool'),
            'ai_public_daily_limit' => (int) $request->input('ai_public_daily_limit'),
        ];
        if ($request->boolean('remove_ai_key')) {
            $values['ai_api_key'] = null;
        } elseif ($request->filled('ai_api_key')) {
            $values['ai_api_key'] = trim($request->input('ai_api_key'));
        }

        return $values;
    }

    private function appearance(Request $request): array
    {
        $color = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $data = $request->validate([
            'theme_primary' => $color, 'theme_accent' => $color, 'theme_bg' => $color, 'theme_footer' => $color,
            'theme_radius' => ['required', 'in:square,normal,round'], 'theme_font' => ['required', 'in:tajawal,system'],
            'header_style' => ['nullable', 'in:'.implode(',', array_keys(\App\Support\Themes::HEADER))],
            'button_shape' => ['nullable', 'in:'.implode(',', array_keys(\App\Support\Themes::BUTTONS))],
            'card_style' => ['nullable', 'in:'.implode(',', array_keys(\App\Support\Themes::CARDS))],
            'hero_style' => ['nullable', 'in:'.implode(',', array_keys(\App\Support\Themes::HEROES))],
            'footer_style' => ['nullable', 'in:'.implode(',', array_keys(\App\Support\Themes::FOOTERS))],
            'custom_css' => ['nullable', 'string', 'max:20000'],
        ]);
        foreach (['header_style', 'button_shape', 'card_style', 'hero_style', 'footer_style'] as $k) {
            $data[$k] ??= Settings::get($k);
        }
        if ($request->boolean('reset_colors')) {
            $data = array_merge($data, \App\Support\Themes::settingsFor('nibras'), ['theme_font' => 'tajawal']);
        }

        return $data + [
            'custom_css' => (string) ($data['custom_css'] ?? ''),
        ];
    }

    private function advanced(Request $request): array
    {
        $data = $request->validate([
            'head_code' => ['nullable', 'string', 'max:20000'], 'body_code' => ['nullable', 'string', 'max:20000'],
            ...$this->trRules('cookie_text', false, 300), ...$this->trRules('maintenance_message', false, 500),
        ]);

        return [
            'head_code' => (string) ($data['head_code'] ?? ''), 'body_code' => (string) ($data['body_code'] ?? ''),
            'cookie_banner' => $request->boolean('cookie_banner'), 'cookie_text' => $this->trInput($request, 'cookie_text'),
            'maintenance_enabled' => $request->boolean('maintenance_enabled'), 'maintenance_message' => $this->trInput($request, 'maintenance_message'),
        ];
    }
}
