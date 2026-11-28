<?php

/*
 * v1.2 print shop: gift and laser categories (FR + AR), the Laser CO2 4040 machine and the usual blanks / sheets as
 * stock items. Stock items start at ZERO quantity and ZERO cost: the owner records real purchases (Purchases →
 * Receive) which set the quantities and the weighted average cost. Safe to re-run (matched by slug / code / SKU).
 */

use App\Core\Database;

return static function (Database $db): void {
    // ---- categories: two universes with sub-categories (wall-art categories stay as they are)
    $tree = [
        ['personalized-gifts', 'Cadeaux personnalisés', 'هدايا مخصصة', [
            ['mugs-bottles', 'Mugs & gourdes', 'أكواب وقنينات'],
            ['custom-textile', 'Textile personnalisé', 'ملابس وأقمشة مخصصة'],
            ['notebooks-stationery', 'Carnets & papeterie', 'دفاتر وقرطاسية'],
            ['photo-gifts', 'Coussins, puzzles & cadeaux photo', 'وسائد وألغاز وهدايا بالصور'],
            ['keychains-accessories', 'Porte-clés & accessoires', 'ميداليات وإكسسوارات'],
        ]],
        ['laser-engraving-cutting', 'Gravure & découpe laser', 'النقش والقص بالليزر', [
            ['engraved-wood', 'Bois gravé', 'خشب منقوش'],
            ['acrylic-led-lamps', 'Acrylique & lampes LED', 'أكريليك ومصابيح LED'],
            ['engraved-leather', 'Cuir gravé', 'جلد منقوش'],
            ['laser-wall-decor', 'Décoration murale découpée', 'ديكور جداري مقصوص'],
            ['name-signs', 'Prénoms & enseignes', 'أسماء ولافتات'],
        ]],
    ];
    $sort = 200;
    $save = function (string $slug, string $fr, string $ar, ?int $parent) use ($db, &$sort): int {
        $id = $db->value('SELECT id FROM categories WHERE slug = ?', [$slug]);
        if ($id === null) {
            $id = $db->insert('categories', ['slug' => $slug, 'name' => $fr, 'parent_id' => $parent, 'sort_order' => $sort, 'is_active' => 1, 'is_featured' => $parent === null ? 1 : 0]);
        }
        $sort += 10;
        $db->query("INSERT IGNORE INTO translations (entity_type, entity_id, locale, field, value) VALUES ('category', ?, 'ar', 'name', ?)", [(int) $id, $ar]);
        return (int) $id;
    };
    foreach ($tree as [$slug, $fr, $ar, $children]) {
        $parentId = $save($slug, $fr, $ar, null);
        foreach ($children as [$cs, $cfr, $car]) {
            $save($cs, $cfr, $car, $parentId);
        }
    }

    // ---- the CO2 laser (40 × 40 cm bed); its hourly cost is set by the owner (Production → Machines)
    if ($db->value("SELECT id FROM machines WHERE code = 'LASER-4040'") === null) {
        $db->insert('machines', [
            'code' => 'LASER-4040', 'name' => 'Laser CO2 4040', 'machine_type' => 'laser', 'status' => 'active',
            'hourly_cost' => '0.00', 'work_width_mm' => 400, 'work_height_mm' => 400,
            'notes' => 'Gravure et découpe : bois, MDF, contreplaqué, acrylique, cuir, liège, carton, verre (gravure), aluminium anodisé (gravure). Pas de métal nu ni de PVC.',
        ]);
    }

    // ---- common stock items (zero quantity, zero cost until the first purchase)
    $items = [
        ['BLANK-MUG-325', 'Mug céramique blanc sublimation 325 ml', 'blank', 'piece', 12],
        ['BLANK-MUG-MAGIC', 'Mug magique noir 325 ml', 'blank', 'piece', 6],
        ['BLANK-BOTTLE-500', 'Gourde inox blanche sublimation 500 ml', 'blank', 'piece', 6],
        ['BLANK-NOTEBOOK-A5', 'Carnet A5 à couverture personnalisable', 'blank', 'piece', 10],
        ['BLANK-TSHIRT-WHITE', 'T-shirt blanc polyester (sublimation)', 'textile', 'piece', 10],
        ['BLANK-CUSHION-40', 'Housse de coussin 40 × 40 cm', 'textile', 'piece', 5],
        ['BLANK-PUZZLE-A4', 'Puzzle A4 à sublimer (120 pièces)', 'blank', 'piece', 5],
        ['BLANK-KEYRING', 'Anneau porte-clés', 'accessory', 'piece', 50],
        ['SUBLI-PAPER-A4', 'Papier sublimation A4', 'sublimation', 'sheet', 100],
        ['SUBLI-PAPER-A3', 'Papier sublimation A3', 'sublimation', 'sheet', 50],
        ['WOOD-MDF3-4040', 'MDF 3 mm — plaque 40 × 40 cm', 'wood', 'sheet', 10],
        ['WOOD-PLY3-4040', 'Contreplaqué peuplier 3 mm — plaque 40 × 40 cm', 'wood', 'sheet', 10],
        ['WOOD-BOARD-BEECH', 'Planche à découper hêtre 30 × 20 cm', 'wood', 'piece', 5],
        ['ACRYL-CLEAR3-4040', 'Acrylique transparent 3 mm — plaque 40 × 40 cm', 'acrylic', 'sheet', 5],
        ['LED-BASE', 'Socle LED pour plaque acrylique', 'accessory', 'piece', 5],
        ['LEATHER-NATURAL', 'Cuir naturel (pour gravure)', 'leather', 'm2', 1],
        ['BOX-GIFT-SMALL', 'Boîte cadeau petite', 'packaging', 'piece', 20],
    ];
    foreach ($items as [$sku, $name, $cat, $unit, $min]) {
        $db->query(
            'INSERT IGNORE INTO materials (sku, name, category, unit, stock_quantity, min_stock, unit_cost, is_active) VALUES (?, ?, ?, ?, 0, ?, 0, 1)',
            [$sku, $name, $cat, $unit, $min]
        );
    }
};
