<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Block-based page builder: block definitions (shared with the editor UI),
 * strict sanitization of everything an editor saves, and translation helpers.
 */
class Builder
{
    public const ICONS = ['layers', 'book', 'play', 'award', 'check-circle', 'globe', 'users', 'chart', 'sparkles', 'shield', 'lamp', 'code', 'briefcase', 'phone', 'truck', 'star', 'heart', 'clock', 'unlock', 'mail', 'map-pin', 'whatsapp', 'store', 'file-text'];

    /** Common design settings available on every block. */
    public static function styleFields(): array
    {
        return [
            ['k' => 'bg', 'type' => 'select', 'label' => 'الخلفية', 'default' => 'none', 'options' => [
                'none' => 'بدون (لون الصفحة)', 'white' => 'أبيض', 'soft' => 'بنفسجي فاتح', 'brand' => 'اللون الأساسي', 'dark' => 'داكن', 'gradient' => 'متدرّج', 'amber' => 'كهرماني فاتح', 'custom' => 'لون مخصص']],
            ['k' => 'bg_color', 'type' => 'color', 'label' => 'لون الخلفية المخصص', 'default' => '#ffffff', 'show' => ['bg' => 'custom']],
            ['k' => 'bg_image', 'type' => 'image', 'label' => 'صورة الخلفية (اختيارية)'],
            ['k' => 'overlay', 'type' => 'select', 'label' => 'تعتيم فوق الصورة', 'default' => 'm', 'options' => ['none' => 'بدون', 's' => 'خفيف', 'm' => 'متوسط', 'l' => 'قوي']],
            ['k' => 'text', 'type' => 'select', 'label' => 'لون النص', 'default' => 'auto', 'options' => ['auto' => 'تلقائي', 'dark' => 'داكن', 'light' => 'فاتح']],
            ['k' => 'pad', 'type' => 'select', 'label' => 'المسافة الداخلية', 'default' => 'm', 'options' => ['none' => 'بدون', 's' => 'صغيرة', 'm' => 'متوسطة', 'l' => 'كبيرة', 'xl' => 'كبيرة جدًا']],
            ['k' => 'width', 'type' => 'select', 'label' => 'العرض', 'default' => 'normal', 'options' => ['narrow' => 'ضيّق', 'normal' => 'عادي', 'wide' => 'عريض', 'full' => 'كامل الشاشة']],
            ['k' => 'align', 'type' => 'select', 'label' => 'المحاذاة', 'default' => 'start', 'options' => ['start' => 'بداية السطر', 'center' => 'وسط']],
            ['k' => 'anim', 'type' => 'select', 'label' => 'حركة الظهور', 'default' => 'none', 'options' => ['none' => 'بدون', 'fade' => 'تلاشي', 'up' => 'صعود']],
            ['k' => 'hide_mobile', 'type' => 'checkbox', 'label' => 'إخفاء على الهاتف'],
            ['k' => 'hide_desktop', 'type' => 'checkbox', 'label' => 'إخفاء على الحاسوب'],
            ['k' => 'anchor', 'type' => 'text', 'label' => 'معرّف القسم (للروابط #)', 'max' => 40],
        ];
    }

    /** Block types and their content fields. 'tr' = translatable (ar/fr/en). */
    public static function blocks(): array
    {
        $btnStyles = ['primary' => 'أساسي', 'amber' => 'كهرماني', 'outline' => 'إطار', 'white' => 'أبيض', 'whatsapp' => 'واتساب'];
        $cols = ['2' => '2', '3' => '3', '4' => '4'];

        return [
            'hero' => ['label' => 'واجهة رئيسية (Hero)', 'icon' => 'sparkles', 'group' => 'أساسي', 'style' => ['bg' => 'gradient', 'pad' => 'xl', 'text' => 'light'], 'fields' => [
                ['k' => 'badge', 'type' => 'text', 'tr' => true, 'label' => 'شارة صغيرة'],
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان', 'max' => 200],
                ['k' => 'subtitle', 'type' => 'textarea', 'tr' => true, 'label' => 'النص'],
                ['k' => 'show_search', 'type' => 'checkbox', 'label' => 'إظهار البحث عن الدورات'],
                ['k' => 'btn1_label', 'type' => 'text', 'tr' => true, 'label' => 'الزر الأول'],
                ['k' => 'btn1_url', 'type' => 'url', 'label' => 'رابط الزر الأول'],
                ['k' => 'btn2_label', 'type' => 'text', 'tr' => true, 'label' => 'الزر الثاني'],
                ['k' => 'btn2_url', 'type' => 'url', 'label' => 'رابط الزر الثاني'],
                ['k' => 'image', 'type' => 'image', 'label' => 'صورة جانبية'],
                ['k' => 'height', 'type' => 'select', 'label' => 'الارتفاع', 'default' => 'm', 'options' => ['s' => 'صغير', 'm' => 'متوسط', 'l' => 'كبير', 'screen' => 'ملء الشاشة']],
            ]],
            'heading' => ['label' => 'عنوان', 'icon' => 'list', 'group' => 'أساسي', 'style' => ['pad' => 's'], 'fields' => [
                ['k' => 'eyebrow', 'type' => 'text', 'tr' => true, 'label' => 'نص صغير فوق العنوان'],
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان', 'max' => 200],
                ['k' => 'subtitle', 'type' => 'textarea', 'tr' => true, 'label' => 'وصف'],
                ['k' => 'size', 'type' => 'select', 'label' => 'الحجم', 'default' => 'l', 'options' => ['m' => 'متوسط', 'l' => 'كبير', 'xl' => 'كبير جدًا']],
            ]],
            'text' => ['label' => 'نص منسّق', 'icon' => 'file-text', 'group' => 'أساسي', 'fields' => [
                ['k' => 'body', 'type' => 'rich', 'tr' => true, 'label' => 'المحتوى'],
            ]],
            'image' => ['label' => 'صورة', 'icon' => 'eye', 'group' => 'أساسي', 'fields' => [
                ['k' => 'image', 'type' => 'image', 'label' => 'الصورة'],
                ['k' => 'alt', 'type' => 'text', 'tr' => true, 'label' => 'وصف الصورة (للمكفوفين ومحركات البحث)'],
                ['k' => 'caption', 'type' => 'text', 'tr' => true, 'label' => 'تعليق'],
                ['k' => 'link', 'type' => 'url', 'label' => 'رابط عند النقر'],
                ['k' => 'rounded', 'type' => 'checkbox', 'label' => 'زوايا دائرية', 'default' => true],
                ['k' => 'shadow', 'type' => 'checkbox', 'label' => 'ظل'],
            ]],
            'image_text' => ['label' => 'صورة ونص', 'icon' => 'layers', 'group' => 'أساسي', 'style' => ['bg' => 'white'], 'fields' => [
                ['k' => 'image', 'type' => 'image', 'label' => 'الصورة'],
                ['k' => 'side', 'type' => 'select', 'label' => 'مكان الصورة', 'default' => 'end', 'options' => ['start' => 'في البداية', 'end' => 'في النهاية']],
                ['k' => 'eyebrow', 'type' => 'text', 'tr' => true, 'label' => 'نص صغير'],
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'body', 'type' => 'rich', 'tr' => true, 'label' => 'النص'],
                ['k' => 'btn_label', 'type' => 'text', 'tr' => true, 'label' => 'نص الزر'],
                ['k' => 'btn_url', 'type' => 'url', 'label' => 'رابط الزر'],
            ]],
            'buttons' => ['label' => 'أزرار', 'icon' => 'arrow', 'group' => 'أساسي', 'style' => ['pad' => 's'], 'fields' => [
                ['k' => 'items', 'type' => 'items', 'label' => 'الأزرار', 'add' => 'إضافة زر', 'fields' => [
                    ['k' => 'label', 'type' => 'text', 'tr' => true, 'label' => 'النص'],
                    ['k' => 'url', 'type' => 'url', 'label' => 'الرابط'],
                    ['k' => 'style', 'type' => 'select', 'label' => 'الشكل', 'default' => 'primary', 'options' => $btnStyles],
                ]],
            ]],
            'spacer' => ['label' => 'مسافة / فاصل', 'icon' => 'arrow-down', 'group' => 'أساسي', 'style' => ['pad' => 'none'], 'fields' => [
                ['k' => 'height', 'type' => 'select', 'label' => 'الارتفاع', 'default' => 'm', 'options' => ['s' => '20px', 'm' => '48px', 'l' => '80px', 'xl' => '120px']],
                ['k' => 'line', 'type' => 'checkbox', 'label' => 'إظهار خط فاصل'],
            ]],
            'features' => ['label' => 'مزايا (شبكة)', 'icon' => 'check-circle', 'group' => 'محتوى', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'subtitle', 'type' => 'textarea', 'tr' => true, 'label' => 'وصف'],
                ['k' => 'columns', 'type' => 'select', 'label' => 'الأعمدة', 'default' => '3', 'options' => $cols],
                ['k' => 'items', 'type' => 'items', 'label' => 'العناصر', 'add' => 'إضافة ميزة', 'fields' => [
                    ['k' => 'icon', 'type' => 'icon', 'label' => 'الأيقونة', 'default' => 'check-circle'],
                    ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                    ['k' => 'text', 'type' => 'textarea', 'tr' => true, 'label' => 'النص'],
                ]],
            ]],
            'steps' => ['label' => 'خطوات مرقّمة', 'icon' => 'list', 'group' => 'محتوى', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'items', 'type' => 'items', 'label' => 'الخطوات', 'add' => 'إضافة خطوة', 'fields' => [
                    ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                    ['k' => 'text', 'type' => 'textarea', 'tr' => true, 'label' => 'النص'],
                ]],
            ]],
            'stats' => ['label' => 'أرقام وإحصائيات', 'icon' => 'chart', 'group' => 'محتوى', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'mode', 'type' => 'select', 'label' => 'المصدر', 'default' => 'auto', 'options' => ['auto' => 'أرقام حقيقية من الموقع', 'custom' => 'أرقام أكتبها بنفسي']],
                ['k' => 'items', 'type' => 'items', 'label' => 'الأرقام (عند الاختيار اليدوي)', 'add' => 'إضافة رقم', 'show' => ['mode' => 'custom'], 'fields' => [
                    ['k' => 'value', 'type' => 'text', 'label' => 'الرقم', 'max' => 20],
                    ['k' => 'label', 'type' => 'text', 'tr' => true, 'label' => 'الوصف'],
                ]],
            ]],
            'faq' => ['label' => 'أسئلة شائعة', 'icon' => 'info', 'group' => 'محتوى', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'items', 'type' => 'items', 'label' => 'الأسئلة', 'add' => 'إضافة سؤال', 'fields' => [
                    ['k' => 'q', 'type' => 'text', 'tr' => true, 'label' => 'السؤال'],
                    ['k' => 'a', 'type' => 'textarea', 'tr' => true, 'label' => 'الجواب'],
                ]],
            ]],
            'cta' => ['label' => 'دعوة لاتخاذ إجراء', 'icon' => 'arrow', 'group' => 'محتوى', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'text', 'type' => 'textarea', 'tr' => true, 'label' => 'النص'],
                ['k' => 'btn_label', 'type' => 'text', 'tr' => true, 'label' => 'نص الزر'],
                ['k' => 'btn_url', 'type' => 'url', 'label' => 'رابط الزر'],
                ['k' => 'look', 'type' => 'select', 'label' => 'الشكل', 'default' => 'band', 'options' => ['band' => 'شريط ملوّن', 'amber' => 'كهرماني', 'light' => 'فاتح']],
            ]],
            'whatsapp' => ['label' => 'تواصل عبر واتساب', 'icon' => 'whatsapp', 'group' => 'محتوى', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'text', 'type' => 'textarea', 'tr' => true, 'label' => 'النص'],
                ['k' => 'btn_label', 'type' => 'text', 'tr' => true, 'label' => 'نص الزر'],
                ['k' => 'message', 'type' => 'textarea', 'tr' => true, 'label' => 'الرسالة الجاهزة في واتساب'],
            ]],
            'video' => ['label' => 'فيديو (YouTube / Vimeo)', 'icon' => 'play', 'group' => 'وسائط', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'url', 'type' => 'url', 'label' => 'رابط الفيديو'],
                ['k' => 'caption', 'type' => 'text', 'tr' => true, 'label' => 'تعليق'],
            ]],
            'gallery' => ['label' => 'معرض صور', 'icon' => 'layers', 'group' => 'وسائط', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'columns', 'type' => 'select', 'label' => 'الأعمدة', 'default' => '3', 'options' => $cols],
                ['k' => 'items', 'type' => 'items', 'label' => 'الصور', 'add' => 'إضافة صورة', 'fields' => [
                    ['k' => 'image', 'type' => 'image', 'label' => 'الصورة'],
                    ['k' => 'alt', 'type' => 'text', 'tr' => true, 'label' => 'وصف الصورة'],
                ]],
            ]],
            'courses' => ['label' => 'دورات', 'icon' => 'book', 'group' => 'ديناميكي', 'style' => ['bg' => 'white'], 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'subtitle', 'type' => 'textarea', 'tr' => true, 'label' => 'وصف'],
                ['k' => 'source', 'type' => 'select', 'label' => 'الدورات المعروضة', 'default' => 'latest', 'options' => ['featured' => 'المميزة', 'latest' => 'الأحدث', 'popular' => 'الأكثر تسجيلًا', 'category' => 'من تصنيف محدد']],
                ['k' => 'category', 'type' => 'category', 'label' => 'التصنيف', 'show' => ['source' => 'category']],
                ['k' => 'count', 'type' => 'number', 'label' => 'العدد', 'default' => 4, 'min' => 1, 'max' => 12],
                ['k' => 'btn_label', 'type' => 'text', 'tr' => true, 'label' => 'نص زر «عرض الكل»'],
            ]],
            'categories' => ['label' => 'مجالات الدورات', 'icon' => 'layers', 'group' => 'ديناميكي', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
            ]],
            'accreditation' => ['label' => 'قسم الاعتماد', 'icon' => 'shield', 'group' => 'ديناميكي', 'fields' => [
                ['k' => 'note', 'type' => 'checkbox', 'label' => 'يُعرض المحتوى من الإعدادات › الاعتماد (فعّله هناك)'],
            ]],
            'centers' => ['label' => 'مراكز التكوين', 'icon' => 'map-pin', 'group' => 'ديناميكي', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'subtitle', 'type' => 'textarea', 'tr' => true, 'label' => 'وصف'],
                ['k' => 'count', 'type' => 'number', 'label' => 'العدد', 'default' => 6, 'min' => 1, 'max' => 12],
            ]],
            'posts' => ['label' => 'مقالات المدونة', 'icon' => 'file-text', 'group' => 'ديناميكي', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'count', 'type' => 'number', 'label' => 'العدد', 'default' => 3, 'min' => 1, 'max' => 9],
            ]],
            'testimonials' => ['label' => 'آراء الطلاب', 'icon' => 'users', 'group' => 'ديناميكي', 'style' => ['bg' => 'white'], 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
            ]],
            'partners' => ['label' => 'شعارات الشركاء', 'icon' => 'award', 'group' => 'ديناميكي', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
            ]],
            'contact' => ['label' => 'نموذج تواصل', 'icon' => 'mail', 'group' => 'ديناميكي', 'fields' => [
                ['k' => 'title', 'type' => 'text', 'tr' => true, 'label' => 'العنوان'],
                ['k' => 'text', 'type' => 'textarea', 'tr' => true, 'label' => 'النص'],
            ]],
            'html' => ['label' => 'HTML آمن', 'icon' => 'code', 'group' => 'متقدم', 'fields' => [
                ['k' => 'code', 'type' => 'rich', 'tr' => true, 'label' => 'المحتوى (تُزال السكربتات تلقائيًا)'],
            ]],
        ];
    }

    /** Translated value with Arabic fallback. */
    public static function t(mixed $value, ?string $locale = null): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }
        foreach (array_unique([$locale ?? app()->getLocale(), 'ar', 'fr', 'en']) as $code) {
            if (isset($value[$code]) && trim((string) $value[$code]) !== '') {
                return (string) $value[$code];
            }
        }

        return '';
    }

    /** Sanitize a full block list coming from the editor. */
    public static function sanitize(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }
        $defs = self::blocks();
        $out = [];
        foreach (array_slice($blocks, 0, 80) as $block) {
            if (! is_array($block) || ! isset($defs[$block['type'] ?? ''])) {
                continue;
            }
            $type = $block['type'];
            $out[] = [
                'id' => preg_match('/^[a-z0-9]{4,20}$/', (string) ($block['id'] ?? '')) ? $block['id'] : Str::lower(Str::random(10)),
                'type' => $type,
                'hidden' => ! empty($block['hidden']),
                'content' => self::cleanFields($defs[$type]['fields'], is_array($block['content'] ?? null) ? $block['content'] : []),
                'style' => self::cleanFields(self::styleFields(), is_array($block['style'] ?? null) ? $block['style'] : []),
            ];
        }

        return $out;
    }

    private static function cleanFields(array $fields, array $data): array
    {
        $clean = [];
        foreach ($fields as $f) {
            $v = $data[$f['k']] ?? null;
            if ($f['type'] === 'items') {
                $items = [];
                foreach (array_slice(is_array($v) ? $v : [], 0, 30) as $item) {
                    if (is_array($item)) {
                        $items[] = self::cleanFields($f['fields'], $item);
                    }
                }
                $clean[$f['k']] = $items;

                continue;
            }
            if (! empty($f['tr'])) {
                $tr = [];
                foreach (['ar', 'fr', 'en'] as $code) {
                    $tr[$code] = self::cleanValue($f, is_array($v) ? ($v[$code] ?? '') : ($code === 'ar' ? $v : ''));
                }
                $clean[$f['k']] = $tr;
            } else {
                $clean[$f['k']] = self::cleanValue($f, $v);
            }
        }

        return $clean;
    }

    private static function cleanValue(array $f, mixed $v): mixed
    {
        switch ($f['type']) {
            case 'rich':
                return Html::clean(is_string($v) ? mb_substr($v, 0, 50000) : '');
            case 'checkbox':
                return $v === null ? (bool) ($f['default'] ?? false) : filter_var($v, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                $n = is_numeric($v) ? (int) $v : (int) ($f['default'] ?? 0);

                return max($f['min'] ?? 0, min($f['max'] ?? 100, $n));
            case 'select':
                return array_key_exists((string) $v, $f['options']) ? (string) $v : (string) ($f['default'] ?? array_key_first($f['options']));
            case 'icon':
                return in_array($v, self::ICONS, true) ? $v : ($f['default'] ?? 'check-circle');
            case 'color':
                return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : ($f['default'] ?? '#ffffff');
            case 'url':
                $v = trim((string) $v);

                return $v !== '' && preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $v) && ! preg_match('/[\s"<>]/', $v) ? mb_substr($v, 0, 500) : '';
            case 'image':
                $v = trim((string) $v);

                return $v !== '' && preg_match('#^[A-Za-z0-9_\-/.]+$#', $v) && ! str_contains($v, '..') && Storage::disk('public')->exists($v) ? $v : '';
            case 'category':
                return preg_match('/^[a-z0-9\-]{0,120}$/', (string) $v) ? (string) $v : '';
            default: // text, textarea
                $max = $f['max'] ?? ($f['type'] === 'textarea' ? 2000 : 300);

                return mb_substr(trim(strip_tags((string) $v)), 0, $max);
        }
    }

    /** Embed URL for YouTube / Vimeo links, or null. */
    public static function embed(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    /** Starter blocks matching the default homepage, so editing starts from the current design. */
    public static function defaultHome(): array
    {
        $tr = fn ($ar, $fr = '', $en = '') => ['ar' => $ar, 'fr' => $fr, 'en' => $en];
        $b = fn ($type, $content, $style = []) => ['id' => Str::lower(Str::random(10)), 'type' => $type, 'hidden' => false, 'content' => $content, 'style' => $style];

        return self::sanitize([
            $b('hero', ['badge' => $tr('كل الدورات مجانية 100%', 'Toutes les formations sont 100 % gratuites', 'Every course is 100% free'),
                'title' => $tr('تعلّم مهارة جديدة اليوم، واحصل على شهادتك الورقية غدًا', 'Apprenez une compétence aujourd’hui, recevez votre certificat imprimé demain', 'Learn a new skill today, get your printed certificate tomorrow'),
                'subtitle' => $tr(Settings::tr('tagline', 'ar'), Settings::tr('tagline', 'fr'), Settings::tr('tagline', 'en')), 'show_search' => true,
                'btn1_label' => $tr('تصفّح الدورات', 'Voir les formations', 'Browse courses'), 'btn1_url' => '/courses',
                'btn2_label' => $tr('أنشئ حسابك المجاني', 'Créez votre compte gratuit', 'Create your free account'), 'btn2_url' => '/register', 'height' => 'm'],
                ['bg' => 'gradient', 'pad' => 'xl', 'text' => 'light']),
            $b('stats', ['mode' => 'auto'], ['pad' => 's']),
            $b('categories', ['title' => $tr('اختر المجال الذي يناسبك', 'Choisissez votre domaine', 'Choose your field')]),
            $b('courses', ['title' => $tr('دورات مميزة', 'Formations à la une', 'Featured courses'), 'source' => 'featured', 'count' => 4, 'btn_label' => $tr('كل الدورات', 'Toutes les formations', 'All courses')], ['bg' => 'white']),
            $b('steps', ['title' => $tr('من التسجيل إلى الشهادة في أربع خطوات', 'De l’inscription au certificat en quatre étapes', 'From sign-up to certificate in four steps'), 'items' => [
                ['title' => $tr('أنشئ حسابك', 'Créez votre compte', 'Create your account'), 'text' => $tr('حساب مجاني في أقل من دقيقة.', 'Un compte gratuit en moins d’une minute.', 'A free account in under a minute.')],
                ['title' => $tr('سجّل في الدورة مجانًا', 'Inscrivez-vous gratuitement', 'Enroll for free'), 'text' => $tr('بدون أي دفع أو بطاقة بنكية.', 'Sans paiement ni carte bancaire.', 'No payment, no bank card.')],
                ['title' => $tr('تعلّم وأكمل الدروس', 'Apprenez et terminez les leçons', 'Learn and complete lessons'), 'text' => $tr('تقدّمك محفوظ، وتابع من حيث توقفت.', 'Votre progression est enregistrée.', 'Your progress is saved.')],
                ['title' => $tr('اطلب شهادتك عبر واتساب', 'Demandez votre certificat via WhatsApp', 'Request your certificate on WhatsApp'), 'text' => $tr('نؤكد بياناتك ونرسل الشهادة الورقية إلى عنوانك.', 'Nous envoyons le certificat imprimé chez vous.', 'We ship the printed certificate to you.')],
            ]]),
            $b('courses', ['title' => $tr('دورات منشورة حديثًا', 'Récemment publiées', 'Recently published'), 'source' => 'latest', 'count' => 4, 'btn_label' => $tr('عرض الكل', 'Voir tout', 'View all')], ['bg' => 'white']),
            $b('features', ['title' => $tr('تعلّم بلا عوائق', 'Apprendre sans obstacles', 'Learning without barriers'), 'columns' => '3', 'items' => [
                ['icon' => 'unlock', 'title' => $tr('مجانية بالكامل', 'Entièrement gratuit', 'Completely free'), 'text' => $tr('كل الدورات مفتوحة مجانًا، بدون اشتراك.', 'Toutes les formations sont gratuites.', 'Every course is free.')],
                ['icon' => 'globe', 'title' => $tr('بلغتك', 'Dans votre langue', 'In your language'), 'text' => $tr('شرح بالعربية والدارجة مع المصطلحات بالفرنسية والإنجليزية.', 'Explications en arabe et darija, termes en français et anglais.', 'Explained in Arabic and Darija, with French and English terms.')],
                ['icon' => 'award', 'title' => $tr('شهادة ورقية', 'Certificat imprimé', 'Printed certificate'), 'text' => $tr('اطلبها عبر واتساب وتصلك إلى عنوانك.', 'Demandez-le sur WhatsApp, il arrive chez vous.', 'Request it on WhatsApp and get it delivered.')],
            ]]),
            $b('centers', ['title' => $tr('مراكزنا في المغرب', 'Nos centres au Maroc', 'Our centres in Morocco'), 'subtitle' => $tr('', '', ''), 'count' => 6]),
            $b('testimonials', ['title' => $tr('ماذا يقول طلابنا؟', 'Ce que disent nos apprenants', 'What our students say')], ['bg' => 'white']),
            $b('posts', ['title' => $tr('أحدث المقالات', 'Derniers articles', 'Latest articles'), 'count' => 3]),
            $b('cta', ['title' => $tr('جاهز للبدء؟', 'Prêt à commencer ?', 'Ready to start?'), 'text' => $tr('اختر دورتك الأولى الآن وابدأ التعلم مجانًا.', 'Choisissez votre première formation et commencez gratuitement.', 'Pick your first course and start learning for free.'),
                'btn_label' => $tr('تصفّح الدورات', 'Voir les formations', 'Browse courses'), 'btn_url' => '/courses', 'look' => 'band']),
        ]);
    }

    /** Starter blocks for a new builder page. */
    public static function defaultPage(string $title): array
    {
        $tr = fn ($ar) => ['ar' => $ar, 'fr' => '', 'en' => ''];

        return self::sanitize([
            ['type' => 'hero', 'content' => ['title' => $tr($title), 'subtitle' => $tr('اكتب هنا وصفًا قصيرًا للصفحة.'), 'height' => 's'], 'style' => ['bg' => 'gradient', 'pad' => 'l', 'text' => 'light', 'align' => 'center']],
            ['type' => 'text', 'content' => ['body' => $tr('<p>ابدأ بكتابة محتوى الصفحة هنا. يمكنك إضافة صور، مزايا، أسئلة شائعة وأزرار من قائمة الكتل.</p>')], 'style' => ['width' => 'narrow']],
        ]);
    }
}
