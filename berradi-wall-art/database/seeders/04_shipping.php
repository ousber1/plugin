<?php

/*
 * Default Morocco shipping zones and city rates (editable in admin > shipping).
 * Only inserted when no zone exists yet, so admin changes are never overwritten.
 */

use App\Core\Database;

return static function (Database $db): void {
    if ((int) $db->value('SELECT COUNT(*) FROM shipping_zones') > 0) {
        return;
    }
    $zones = [
        ['Casablanca & Mohammedia', 'Livraison locale', '25.00', '800.00', 1, 2, ['Casablanca', 'Mohammedia', 'Bouskoura', 'Dar Bouazza']],
        ['Grandes villes', 'Axe principal du Maroc', '40.00', '1000.00', 1, 3, ['Rabat', 'Salé', 'Témara', 'Kénitra', 'Marrakech', 'Fès', 'Meknès', 'Tanger', 'Tétouan', 'Agadir', 'El Jadida', 'Settat', 'Oujda', 'Béni Mellal', 'Safi', 'Nador']],
        ['Reste du Maroc', 'Autres villes et régions', '50.00', '1200.00', 2, 5, []],
        ['Provinces du Sud', 'Laâyoune, Dakhla, Guelmim...', '70.00', '1500.00', 3, 7, ['Laâyoune', 'Dakhla', 'Guelmim', 'Tan-Tan', 'Smara']],
    ];
    foreach ($zones as $i => [$name, $desc, $price, $free, $min, $max, $cities]) {
        $zoneId = $db->insert('shipping_zones', [
            'name' => $name,
            'country' => 'MA',
            'description' => $desc,
            'default_price' => $price,
            'free_shipping_threshold' => $free,
            'min_days' => $min,
            'max_days' => $max,
            'sort_order' => $i + 1,
        ]);
        foreach ($cities as $city) {
            $db->insert('shipping_rates', [
                'shipping_zone_id' => $zoneId,
                'city' => $city,
                'price' => $price,
            ]);
        }
    }
};
