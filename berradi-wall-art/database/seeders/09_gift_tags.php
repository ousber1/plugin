<?php

/*
 * v1.3 recipients and occasions (FR + AR). Only creates missing slugs; the owner can rename, reorder or hide them.
 */

use App\Core\Database;

return static function (Database $db): void {
    $tags = [
        'recipient' => [
            ['maman', 'Maman', 'لماما', 'heart'], ['papa', 'Papa', 'لبابا', 'person'], ['femme', 'Femme', 'للزوجة', 'gem'],
            ['mari', 'Mari', 'للزوج', 'person-badge'], ['couple', 'Couple', 'للزوجين', 'people'], ['grand-mere', 'Grand-mère', 'للجدة', 'flower1'],
            ['grand-pere', 'Grand-père', 'للجد', 'cup-hot'], ['enfants', 'Enfants', 'للأطفال', 'balloon'], ['bebe', 'Bébé', 'للرضيع', 'stars'],
            ['amis', 'Amis', 'للأصدقاء', 'emoji-smile'], ['collegues', 'Collègues & entreprise', 'للزملاء والشركات', 'briefcase'],
            ['enseignants', 'Enseignants', 'للأساتذة', 'book'],
        ],
        'occasion' => [
            ['anniversaire', 'Anniversaire', 'عيد ميلاد', 'cake2'], ['mariage', 'Mariage', 'زفاف', 'suit-heart'], ['naissance', 'Naissance', 'ولادة', 'stars'],
            ['fete-des-meres', 'Fête des mères', 'عيد الأم', 'heart'], ['fete-des-peres', 'Fête des pères', 'عيد الأب', 'award'],
            ['aid', 'Aïd', 'العيد', 'moon-stars'], ['ramadan', 'Ramadan', 'رمضان', 'moon'], ['saint-valentin', 'Saint-Valentin', 'عيد الحب', 'hearts'],
            ['diplome', 'Diplôme & réussite', 'التخرج والنجاح', 'mortarboard'], ['entreprise', 'Cadeaux d’entreprise', 'هدايا الشركات', 'building'],
            ['cremaillere', 'Nouvelle maison', 'منزل جديد', 'house-heart'], ['merci', 'Remerciement', 'شكر', 'hand-thumbs-up'],
        ],
    ];
    foreach ($tags as $type => $list) {
        foreach ($list as $i => [$slug, $fr, $ar, $icon]) {
            $id = $db->value('SELECT id FROM product_tags WHERE tag_type = ? AND slug = ?', [$type, $slug]);
            if ($id === null) {
                $id = $db->insert('product_tags', ['tag_type' => $type, 'slug' => $slug, 'name' => $fr, 'icon' => $icon, 'sort_order' => ($i + 1) * 10]);
            }
            $db->query("INSERT IGNORE INTO translations (entity_type, entity_id, locale, field, value) VALUES ('product_tag', ?, 'ar', 'name', ?)", [(int) $id, $ar]);
        }
    }
};
