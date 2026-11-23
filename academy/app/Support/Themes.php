<?php

namespace App\Support;

/**
 * Ready-made site themes. Applying one writes its palette and style choices
 * to the appearance settings; every value can still be fine-tuned afterwards.
 */
class Themes
{
    public const HEADER = ['light' => 'فاتح', 'dark' => 'داكن', 'brand' => 'بلون الهوية'];

    public const BUTTONS = ['rounded' => 'زوايا ناعمة', 'pill' => 'دائرية (كبسولة)', 'square' => 'حادة'];

    public const CARDS = ['elevated' => 'بظل ناعم', 'bordered' => 'بإطار', 'flat' => 'مسطّحة'];

    public const HEROES = ['gradient' => 'تدرّج لوني', 'mesh' => 'تدرّج متعدد الألوان', 'zellige' => 'زخرفة مغربية', 'light' => 'فاتحة'];

    public const FOOTERS = ['dark' => 'داكن', 'brand' => 'بلون الهوية', 'light' => 'فاتح'];

    public static function all(): array
    {
        return [
            'nibras' => ['name' => 'نبراس', 'desc' => 'نيلي عميق مع لمسة ذهبية — التصميم الأصلي.',
                'primary' => '#3a2a9e', 'accent' => '#f5b82e', 'bg' => '#fbf8f1', 'footer' => '#15122b',
                'radius' => 'normal', 'header' => 'light', 'buttons' => 'rounded', 'cards' => 'elevated', 'hero' => 'gradient', 'footer_style' => 'dark'],
            'atlas' => ['name' => 'أطلس', 'desc' => 'أخضر زمردي وذهبي مع زخرفة الزليج المغربي.',
                'primary' => '#0f6b4f', 'accent' => '#d4a017', 'bg' => '#f6f8f3', 'footer' => '#0a2a1f',
                'radius' => 'normal', 'header' => 'light', 'buttons' => 'rounded', 'cards' => 'bordered', 'hero' => 'zellige', 'footer_style' => 'dark'],
            'ocean' => ['name' => 'المحيط', 'desc' => 'أزرق هادئ وفيروزي، أزرار دائرية وواجهة متعددة الألوان.',
                'primary' => '#1655b0', 'accent' => '#14b8c4', 'bg' => '#f4f8fc', 'footer' => '#0b1f3a',
                'radius' => 'round', 'header' => 'light', 'buttons' => 'pill', 'cards' => 'elevated', 'hero' => 'mesh', 'footer_style' => 'dark'],
            'sahara' => ['name' => 'الصحراء', 'desc' => 'ألوان الطين والرمال الدافئة، قائمة علوية داكنة.',
                'primary' => '#a6461f', 'accent' => '#e9a83c', 'bg' => '#fbf5ec', 'footer' => '#2b1a12',
                'radius' => 'round', 'header' => 'dark', 'buttons' => 'rounded', 'cards' => 'elevated', 'hero' => 'zellige', 'footer_style' => 'dark'],
            'royal' => ['name' => 'ملكي', 'desc' => 'عنابي فاخر وذهبي، زوايا حادة وطابع رسمي.',
                'primary' => '#7a1f3d', 'accent' => '#c9a227', 'bg' => '#fbf8f6', 'footer' => '#2a0f19',
                'radius' => 'square', 'header' => 'brand', 'buttons' => 'square', 'cards' => 'bordered', 'hero' => 'gradient', 'footer_style' => 'dark'],
            'minimal' => ['name' => 'بسيط', 'desc' => 'أبيض وأسود مع أزرق واضح، تصميم نظيف بلا ظلال.',
                'primary' => '#111827', 'accent' => '#2563eb', 'bg' => '#ffffff', 'footer' => '#111827',
                'radius' => 'square', 'header' => 'light', 'buttons' => 'square', 'cards' => 'flat', 'hero' => 'light', 'footer_style' => 'light'],
            'midnight' => ['name' => 'ليلي', 'desc' => 'بنفسجي ووردي على خلفية داكنة، عصري وجريء.',
                'primary' => '#6d28d9', 'accent' => '#ec4899', 'bg' => '#f7f5ff', 'footer' => '#0c0a1d',
                'radius' => 'round', 'header' => 'dark', 'buttons' => 'pill', 'cards' => 'elevated', 'hero' => 'mesh', 'footer_style' => 'dark'],
            'spring' => ['name' => 'ربيع', 'desc' => 'فيروزي ومرجاني، فاتح ومرح ومناسب للشباب.',
                'primary' => '#0b7a70', 'accent' => '#f97362', 'bg' => '#f6fbfa', 'footer' => '#0f2e2b',
                'radius' => 'round', 'header' => 'light', 'buttons' => 'pill', 'cards' => 'bordered', 'hero' => 'light', 'footer_style' => 'brand'],
        ];
    }

    /** Settings written when a theme is applied. */
    public static function settingsFor(string $key): array
    {
        $t = self::all()[$key] ?? self::all()['nibras'];

        return [
            'site_theme' => array_key_exists($key, self::all()) ? $key : 'nibras',
            'theme_primary' => $t['primary'], 'theme_accent' => $t['accent'], 'theme_bg' => $t['bg'], 'theme_footer' => $t['footer'],
            'theme_radius' => $t['radius'], 'header_style' => $t['header'], 'button_shape' => $t['buttons'],
            'card_style' => $t['cards'], 'hero_style' => $t['hero'], 'footer_style' => $t['footer_style'],
        ];
    }

    /** Classes added to <body> for the structural style choices. */
    public static function bodyClasses(): string
    {
        $pick = fn (string $key, array $allowed, string $default) => in_array($v = Settings::get($key), array_keys($allowed), true) ? $v : $default;

        return implode(' ', [
            'th-'.(array_key_exists($v = (string) Settings::get('site_theme'), self::all()) ? $v : 'nibras'),
            'hdr-'.$pick('header_style', self::HEADER, 'light'),
            'btns-'.$pick('button_shape', self::BUTTONS, 'rounded'),
            'cards-'.$pick('card_style', self::CARDS, 'elevated'),
            'hero-'.$pick('hero_style', self::HEROES, 'gradient'),
            'ftr-'.$pick('footer_style', self::FOOTERS, 'dark'),
        ]);
    }
}
