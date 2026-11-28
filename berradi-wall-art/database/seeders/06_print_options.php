<?php

/*
 * Default print substrates, frames and customization options used by the Custom Wall Art Builder.
 * Prices are starting defaults to be reviewed by the administrator (admin > catalogue options).
 * Only inserted when the tables are empty.
 */

use App\Core\Database;

return static function (Database $db): void {
    if ((int) $db->value('SELECT COUNT(*) FROM product_materials') === 0) {
        $materials = [
            // code, name, type, price/m2, min price, cost/m2, supports frame
            ['canvas', 'Toile canvas premium', 'canvas', '650.00', '199.00', '180.00', 1],
            ['poster', 'Poster papier mat 200g', 'poster', '300.00', '79.00', '60.00', 1],
            ['photo_paper', 'Papier photo satiné', 'photo_paper', '420.00', '99.00', '95.00', 1],
            ['bache', 'Bâche (intérieur / extérieur)', 'bache', '220.00', '120.00', '55.00', 0],
            ['vinyl', 'Vinyle adhésif', 'vinyl', '280.00', '99.00', '70.00', 0],
        ];
        foreach ($materials as $i => [$code, $name, $type, $p, $min, $cost, $frame]) {
            $db->insert('product_materials', [
                'code' => $code, 'name' => $name, 'material_type' => $type,
                'price_per_m2' => $p, 'minimum_price' => $min, 'cost_per_m2' => $cost,
                'supports_frame' => $frame, 'sort_order' => $i + 1,
            ]);
        }
    }

    if ((int) $db->value('SELECT COUNT(*) FROM frames') === 0) {
        $frames = [
            ['black', 'Cadre noir mat', 'Noir', '#171717', 'wood', '90.00', '25.00'],
            ['white', 'Cadre blanc', 'Blanc', '#FFFFFF', 'wood', '90.00', '25.00'],
            ['gold', 'Cadre doré', 'Doré', '#C6A66B', 'wood', '120.00', '35.00'],
            ['oak', 'Cadre chêne naturel', 'Chêne', '#B88A57', 'wood', '110.00', '30.00'],
            ['walnut', 'Cadre noyer', 'Noyer', '#5C4033', 'wood', '120.00', '35.00'],
            ['floating_black', 'Caisse américaine noire', 'Noir', '#171717', 'floating', '140.00', '40.00'],
        ];
        foreach ($frames as $i => [$code, $name, $color, $hex, $type, $ppm, $cpm]) {
            $db->insert('frames', [
                'code' => $code, 'name' => $name, 'color_name' => $color, 'color_hex' => $hex,
                'frame_type' => $type, 'price_per_meter' => $ppm, 'cost_per_meter' => $cpm,
                'sort_order' => $i + 1,
            ]);
        }
    }

    if ((int) $db->value('SELECT COUNT(*) FROM customization_options') === 0) {
        $options = [
            // group, code, name, w, h, panels, price_type, price, meta
            ['size', '30x40', '30 × 40 cm', '30.0', '40.0', null, 'fixed', '0.00', null],
            ['size', '40x60', '40 × 60 cm', '40.0', '60.0', null, 'fixed', '0.00', null],
            ['size', '50x70', '50 × 70 cm', '50.0', '70.0', null, 'fixed', '0.00', null],
            ['size', '60x90', '60 × 90 cm', '60.0', '90.0', null, 'fixed', '0.00', null],
            ['size', '70x100', '70 × 100 cm', '70.0', '100.0', null, 'fixed', '0.00', null],
            ['size', '100x150', '100 × 150 cm', '100.0', '150.0', null, 'fixed', '0.00', null],
            ['border', 'wrap', 'Image enveloppante', null, null, null, 'fixed', '0.00', null],
            ['border', 'mirror', 'Bords miroir', null, null, null, 'fixed', '0.00', null],
            ['border', 'white', 'Bords blancs', null, null, null, 'fixed', '0.00', null],
            ['border', 'black', 'Bords noirs', null, null, null, 'fixed', '0.00', null],
            ['panel_layout', 'single', '1 panneau', null, null, 1, 'percent', '0.00', '{"gap_cm":0}'],
            ['panel_layout', 'diptych', '2 panneaux', null, null, 2, 'percent', '10.00', '{"gap_cm":3}'],
            ['panel_layout', 'triptych', '3 panneaux', null, null, 3, 'percent', '15.00', '{"gap_cm":3}'],
            ['panel_layout', 'quad', '4 panneaux', null, null, 4, 'percent', '20.00', '{"gap_cm":3}'],
            ['panel_layout', 'penta', '5 panneaux', null, null, 5, 'percent', '25.00', '{"gap_cm":3}'],
            ['finish', 'matte', 'Finition mate', null, null, null, 'fixed', '0.00', null],
            ['finish', 'gloss', 'Vernis brillant', null, null, null, 'per_m2', '60.00', null],
        ];
        foreach ($options as $i => [$group, $code, $name, $w, $h, $panels, $ptype, $price, $meta]) {
            $db->insert('customization_options', [
                'option_group' => $group, 'code' => $code, 'name' => $name,
                'width_cm' => $w, 'height_cm' => $h, 'panels' => $panels,
                'price_type' => $ptype, 'price_value' => $price, 'meta' => $meta,
                'sort_order' => $i + 1,
            ]);
        }
    }
};
