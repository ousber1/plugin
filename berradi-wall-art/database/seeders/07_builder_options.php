<?php

/*
 * Builder additions: square formats, material descriptions and Arabic names of print options.
 * Idempotent: only inserts what is missing, never overwrites admin edits.
 */

use App\Core\Database;

return static function (Database $db): void {
    foreach ([['30x30', '30 × 30 cm', 30], ['50x50', '50 × 50 cm', 50], ['70x70', '70 × 70 cm', 70]] as $i => [$code, $name, $s]) {
        $db->query(
            "INSERT IGNORE INTO customization_options (option_group, code, name, width_cm, height_cm, price_type, price_value, sort_order)
             VALUES ('size', ?, ?, ?, ?, 'fixed', 0, ?)",
            [$code, $name, $s . '.0', $s . '.0', 50 + $i]
        );
    }

    $descriptions = [
        'canvas' => ['Toile coton-polyester tendue sur châssis bois, prête à accrocher.', 'كانفاس مشدود على إطار خشبي، جاهز للتعليق.', 'Toile canvas premium'],
        'poster' => ['Papier mat 200 g aux couleurs profondes, idéal sous cadre.', 'ورق مطفي 200 غ بألوان عميقة، مثالي مع إطار.', 'ملصق ورق مطفي'],
        'photo_paper' => ['Papier photo satiné, rendu fidèle des portraits et détails.', 'ورق فوتوغرافي ساتان، دقة عالية للوجوه والتفاصيل.', 'ورق فوتوغرافي ساتان'],
        'bache' => ['Bâche résistante pour grands formats et usage extérieur.', 'قماش مشمع متين للمقاسات الكبيرة والاستعمال الخارجي.', 'قماش مشمع'],
        'vinyl' => ['Vinyle adhésif pour murs lisses et vitrines.', 'فينيل لاصق للجدران الملساء والواجهات.', 'فينيل لاصق'],
    ];
    foreach ($descriptions as $code => [$fr, $ar, $arName]) {
        $id = $db->value('SELECT id FROM product_materials WHERE code = ?', [$code]);
        if ($id === null) {
            continue;
        }
        $db->query("UPDATE product_materials SET description = ? WHERE id = ? AND (description IS NULL OR description = '')", [$fr, (int) $id]);
        foreach (['description' => $ar, 'name' => $arName] as $field => $value) {
            $db->query('INSERT IGNORE INTO translations (entity_type, entity_id, locale, field, value) VALUES (?, ?, ?, ?, ?)', ['product_material', (int) $id, 'ar', $field, $value]);
        }
    }

    $frames = ['black' => 'إطار أسود مطفي', 'white' => 'إطار أبيض', 'gold' => 'إطار ذهبي', 'oak' => 'إطار خشب البلوط', 'walnut' => 'إطار خشب الجوز', 'floating_black' => 'إطار عائم أسود'];
    foreach ($frames as $code => $ar) {
        $id = $db->value('SELECT id FROM frames WHERE code = ?', [$code]);
        if ($id !== null) {
            $db->query('INSERT IGNORE INTO translations (entity_type, entity_id, locale, field, value) VALUES (?, ?, ?, ?, ?)', ['frame', (int) $id, 'ar', 'name', $ar]);
        }
    }

    $options = [
        'border' => ['wrap' => 'صورة ملتفة على الحواف', 'mirror' => 'حواف معكوسة', 'white' => 'حواف بيضاء', 'black' => 'حواف سوداء'],
        'panel_layout' => ['single' => 'جزء واحد', 'diptych' => 'جزءان', 'triptych' => '3 أجزاء', 'quad' => '4 أجزاء', 'penta' => '5 أجزاء'],
        'finish' => ['matte' => 'تشطيب مطفي', 'gloss' => 'ورنيش لامع'],
    ];
    foreach ($options as $group => $items) {
        foreach ($items as $code => $ar) {
            $id = $db->value('SELECT id FROM customization_options WHERE option_group = ? AND code = ?', [$group, $code]);
            if ($id !== null) {
                $db->query('INSERT IGNORE INTO translations (entity_type, entity_id, locale, field, value) VALUES (?, ?, ?, ?, ?)', ['customization_option', (int) $id, 'ar', 'name', $ar]);
            }
        }
    }
};
