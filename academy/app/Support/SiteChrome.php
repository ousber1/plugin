<?php

namespace App\Support;

/** Header call-to-action and footer structure, configured in Admin › الهيدر والتذييل. */
class SiteChrome
{
    public const COLUMN_TYPES = [
        'links' => 'روابط مخصّصة',
        'categories' => 'مجالات الدورات',
        'pages' => 'روابط الأكاديمية والصفحات',
        'services' => 'الخدمات والأدوات',
        'centers' => 'مراكز التكوين',
        'contact' => 'معلومات التواصل',
    ];

    /** @return array{label:string,url:string,whatsapp:bool,external:bool}|null */
    public static function headerCta(): ?array
    {
        if (! Settings::get('header_cta_enabled') || ($label = trim(Settings::tr('header_cta_label'))) === '') {
            return null;
        }
        if (Settings::get('header_cta_action') === 'url') {
            $url = (string) Settings::get('header_cta_url');
            if ($url === '') {
                return null;
            }

            return ['label' => $label, 'url' => $url, 'whatsapp' => false, 'external' => str_starts_with($url, 'http')];
        }
        $url = Settings::whatsappLink(Settings::tr('header_cta_message') ?: $label);

        return $url ? ['label' => $label, 'url' => $url, 'whatsapp' => true, 'external' => true] : null;
    }

    public static function footerColumns(): array
    {
        return collect(Settings::get('footer_columns') ?: [])
            ->filter(fn ($c) => is_array($c) && isset(self::COLUMN_TYPES[$c['type'] ?? '']))
            ->take(4)->values()->all();
    }

    public static function copyright(): string
    {
        return strtr(Settings::tr('footer_copyright'), ['{year}' => date('Y'), '{site}' => Settings::siteName()]);
    }

    public static function label(array $item): string
    {
        $l = $item['label'] ?? '';
        if (! is_array($l)) {
            return (string) $l;
        }

        return trim($l[app()->getLocale()] ?? '') ?: trim($l['ar'] ?? '') ?: (string) collect($l)->first(fn ($v) => trim((string) $v) !== '');
    }
}
