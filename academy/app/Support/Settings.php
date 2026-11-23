<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value site settings editable from the admin dashboard.
 * Values are JSON encoded; secrets (keys listed in ENCRYPTED) are encrypted.
 */
class Settings
{
    private const CACHE_KEY = 'academy.settings';

    private const ENCRYPTED = ['ai_api_key'];

    private static ?array $loaded = null;

    public static function defaults(): array
    {
        return [
            'site_name' => ['ar' => 'أكاديمية نبراس', 'fr' => 'Académie Nibras', 'en' => 'Nibras Academy'],
            'tagline' => [
                'ar' => 'تعلّم مهارات المستقبل مجانًا، واحصل على شهادتك الورقية إلى باب بيتك',
                'fr' => 'Apprenez gratuitement les compétences de demain et recevez votre certificat imprimé chez vous',
                'en' => 'Learn tomorrow’s skills for free and receive your printed certificate at home',
            ],
            'topbar_text' => [
                'ar' => 'كل الدورات مجانية — والشهادة الورقية تصلك عبر التوصيل داخل المغرب',
                'fr' => 'Toutes les formations sont gratuites — certificat imprimé livré partout au Maroc',
                'en' => 'Every course is free — printed certificates delivered across Morocco',
            ],
            'footer_about' => [
                'ar' => 'منصة تعليمية مغربية تقدّم دورات مجانية عملية باللغة العربية والفرنسية والإنجليزية، مع شهادات ورقية تُرسل إلى عنوانك.',
                'fr' => 'Une plateforme marocaine de formations gratuites et pratiques en arabe, français et anglais, avec certificats imprimés livrés à domicile.',
                'en' => 'A Moroccan learning platform offering free, practical courses in Arabic, French and English, with printed certificates shipped to you.',
            ],
            'logo_path' => null,
            'favicon_path' => null,
            'contact_email' => 'contact@example.ma',
            'contact_phone' => null,
            'contact_address' => ['ar' => 'الدار البيضاء، المغرب', 'fr' => 'Casablanca, Maroc', 'en' => 'Casablanca, Morocco'],
            'whatsapp_number' => '212600000000',
            'whatsapp_template' => "السلام عليكم، أود طلب الشهادة الورقية لدورة {course}.\nرقم طلب الشهادة: {reference}.",
            'social' => ['facebook' => null, 'instagram' => null, 'youtube' => null, 'tiktok' => null, 'linkedin' => null, 'x' => null],
            'features' => ['blog' => true, 'library' => true, 'tools' => true, 'store' => false, 'verification' => true],
            'home_sections' => [
                ['key' => 'hero', 'enabled' => true],
                ['key' => 'stats', 'enabled' => true],
                ['key' => 'categories', 'enabled' => true],
                ['key' => 'featured', 'enabled' => true],
                ['key' => 'how', 'enabled' => true],
                ['key' => 'recent', 'enabled' => true],
                ['key' => 'benefits', 'enabled' => true],
                ['key' => 'testimonials', 'enabled' => true],
                ['key' => 'articles', 'enabled' => true],
                ['key' => 'partners', 'enabled' => true],
                ['key' => 'cta', 'enabled' => true],
            ],
            'nav_extra_links' => [],
            // Floating WhatsApp button (all public pages)
            'wa_float_enabled' => true,
            'wa_float_side' => 'auto', // auto | left | right
            'wa_float_show_label' => true,
            'wa_float_pulse' => true,
            'wa_float_label' => ['ar' => 'تواصل معنا', 'fr' => 'Écrivez-nous', 'en' => 'Chat with us'],
            'wa_float_message' => ['ar' => 'السلام عليكم، لدي سؤال حول الأكاديمية.', 'fr' => 'Bonjour, j’ai une question sur l’académie.', 'en' => 'Hello, I have a question about the academy.'],
            // Appearance
            'theme_primary' => '#3a2a9e',
            'theme_accent' => '#f5b82e',
            'theme_bg' => '#fbf8f1',
            'theme_footer' => '#15122b',
            'theme_radius' => 'normal', // square | normal | round
            'theme_font' => 'tajawal', // tajawal | system
            'topbar_enabled' => true,
            'sticky_header' => true,
            'custom_css' => '',
            // Advanced
            'head_code' => '',
            'body_code' => '',
            'cookie_banner' => false,
            'cookie_text' => ['ar' => 'نستعمل ملفات تعريف الارتباط الضرورية لتشغيل الموقع وتحسين تجربتك.', 'fr' => 'Nous utilisons des cookies nécessaires au fonctionnement du site.', 'en' => 'We use cookies needed to run the site and improve your experience.'],
            'maintenance_enabled' => false,
            'maintenance_message' => ['ar' => 'نعمل على تحسين الموقع وسنعود قريبًا.', 'fr' => 'Le site est en maintenance, nous revenons très vite.', 'en' => 'We are improving the site and will be back soon.'],
            // Visual builder for the homepage (falls back to the default design when off).
            'home_builder_enabled' => false,
            'home_blocks' => [],
            'accreditation_enabled' => false,
            'accreditation_text' => ['ar' => '', 'fr' => '', 'en' => ''],
            'verify_public_fields' => ['holder_name', 'course_title', 'issued_on'],
            // Students may request the printed certificate on WhatsApp without finishing
            // (or starting) the course. Turn on to require completion first.
            'certificate_requires_completion' => false,
            // Off by default: confirmation emails need SMTP settings (MAIL_*) first.
            'require_verified_email_for_certificate' => false,
            'bank_name' => null,
            'bank_account_holder' => null,
            'bank_rib' => null,
            'bank_instructions' => null,
            'ai_api_key' => null,
            // Public AI quiz generator on the Tools page (uses the same key).
            'ai_public_tool' => true,
            'ai_public_daily_limit' => 10,
            'seo_description' => [
                'ar' => 'دورات مجانية عملية في البرمجة والتصميم واللغات والمهارات الرقمية، مع شهادة ورقية تصلك إلى عنوانك في المغرب.',
                'fr' => 'Formations gratuites et pratiques en programmation, design, langues et compétences numériques, avec certificat imprimé livré au Maroc.',
                'en' => 'Free practical courses in programming, design, languages and digital skills, with a printed certificate delivered in Morocco.',
            ],
        ];
    }

    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        $stored = [];
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all();
            });
        } catch (\Throwable) {
            $stored = [];
        }

        return self::$loaded = array_replace(self::defaults(), $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? $default;
        if (in_array($key, self::ENCRYPTED, true) && is_string($value) && $value !== '') {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return $value;
    }

    /** Translated setting value with Arabic fallback. */
    public static function tr(string $key, ?string $locale = null): string
    {
        $value = self::get($key);
        if (! is_array($value)) {
            return (string) $value;
        }
        foreach (array_unique([$locale ?? app()->getLocale(), Locales::DEFAULT, ...Locales::codes()]) as $code) {
            if (trim((string) ($value[$code] ?? '')) !== '') {
                return $value[$code];
            }
        }

        return '';
    }

    public static function feature(string $name): bool
    {
        return (bool) (self::get('features')[$name] ?? false);
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::ENCRYPTED, true) && is_string($value) && $value !== '') {
                $value = Crypt::encryptString($value);
            }
            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$loaded = null;
    }

    public static function siteName(): string
    {
        return self::tr('site_name');
    }

    /** Ordered list of enabled homepage section keys. */
    public static function homeSections(): array
    {
        $known = array_column(self::defaults()['home_sections'], 'key');
        $sections = collect(self::get('home_sections'))->filter(fn ($s) => in_array($s['key'] ?? null, $known, true));
        // Sections added in later versions are appended so they can be managed.
        foreach ($known as $key) {
            if (! $sections->contains('key', $key)) {
                $sections->push(['key' => $key, 'enabled' => false]);
            }
        }

        return $sections->values()->all();
    }

    public static function whatsappLink(string $text): ?string
    {
        $number = preg_replace('/\D+/', '', (string) self::get('whatsapp_number'));
        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }
}
