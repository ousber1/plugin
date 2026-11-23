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
        'whatsapp' => 'واتساب والشهادات',
        'features' => 'الوحدات والقائمة',
        'home' => 'الصفحة الرئيسية',
        'social' => 'الشبكات الاجتماعية',
        'store' => 'المتجر والدفع',
        'ai' => 'الذكاء الاصطناعي',
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
            'verify_public_fields' => ['nullable', 'array'], 'verify_public_fields.*' => ['in:holder_name,course_title,issued_on'],
        ], ['whatsapp_number.regex' => 'أدخل الرقم بالصيغة الدولية بدون رموز، مثل 212600000000.']);

        return [
            'whatsapp_number' => preg_replace('/\D+/', '', (string) ($data['whatsapp_number'] ?? '')),
            'whatsapp_template' => $data['whatsapp_template'],
            'require_verified_email_for_certificate' => $request->boolean('require_verified_email_for_certificate'),
            'verify_public_fields' => array_values($data['verify_public_fields'] ?? []),
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
        foreach (['blog', 'library', 'tools', 'store', 'verification'] as $f) {
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
            'bank_name' => ['nullable', 'string', 'max:120'], 'bank_account_holder' => ['nullable', 'string', 'max:120'],
            'bank_rib' => ['nullable', 'string', 'max:40'], 'bank_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        return $data;
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
}
