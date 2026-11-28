<?php

/*
 * Default product categories with Arabic translations. Only creates missing slugs.
 */

use App\Core\Database;

return static function (Database $db): void {
    $categories = [
        ['family-portraits', 'Portraits de famille', 'صور العائلة'],
        ['couple-wedding', 'Couple & Mariage', 'الأزواج والزفاف'],
        ['baby-kids', 'Bébé & Enfants', 'الأطفال والرضع'],
        ['islamic-wall-art', 'Art islamique', 'الفن الإسلامي'],
        ['arabic-calligraphy', 'Calligraphie arabe', 'الخط العربي'],
        ['modern-abstract-art', 'Art abstrait moderne', 'الفن التجريدي الحديث'],
        ['nature-landscapes', 'Nature & Paysages', 'الطبيعة والمناظر'],
        ['black-white-photography', 'Photographie noir & blanc', 'التصوير بالأبيض والأسود'],
        ['cars-automotive', 'Voitures & Automobile', 'السيارات'],
        ['minimalist-posters', 'Posters minimalistes', 'ملصقات بسيطة'],
        ['motivational-quotes', 'Citations motivantes', 'اقتباسات تحفيزية'],
        ['personalized-gifts', 'Cadeaux personnalisés', 'هدايا مخصصة'],
        ['multi-panel-wall-art', 'Tableaux multi-panneaux', 'لوحات متعددة الأجزاء'],
    ];
    foreach ($categories as $i => [$slug, $fr, $ar]) {
        $id = $db->value('SELECT id FROM categories WHERE slug = ?', [$slug]);
        if ($id === null) {
            $id = $db->insert('categories', [
                'slug' => $slug,
                'name' => $fr,
                'sort_order' => ($i + 1) * 10,
                'is_active' => 1,
                'is_featured' => $i < 6 ? 1 : 0,
            ]);
        }
        $db->query(
            'INSERT IGNORE INTO translations (entity_type, entity_id, locale, field, value) VALUES (?, ?, ?, ?, ?)',
            ['category', (int) $id, 'ar', 'name', $ar]
        );
    }
};
