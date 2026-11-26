<?php

declare(strict_types=1);

namespace App\Services\Builder;

use App\Core\Database;
use App\Services\TranslationService;

/**
 * Options offered by the Custom Wall Art Builder (substrates, frames, sizes, layouts, borders, finishes),
 * optionally restricted by a customizable product's allowed materials / frames.
 */
final class BuilderOptions
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @return array{materials:list<array<string,mixed>>,frames:list<array<string,mixed>>,sizes:list<array<string,mixed>>,layouts:list<array<string,mixed>>,borders:list<array<string,mixed>>,finishes:list<array<string,mixed>>} */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $db = Database::instance();
        $materials = TranslationService::apply('product_material', $db->fetchAll(
            'SELECT id, code, name, description, material_type, price_per_m2, minimum_price, cost_per_m2, min_width_cm, max_width_cm,
                    min_height_cm, max_height_cm, supports_frame, inventory_material_id, consumption_factor
             FROM product_materials WHERE is_active = 1 ORDER BY sort_order, id'
        ), ['name', 'description']);
        $frames = TranslationService::apply('frame', $db->fetchAll(
            'SELECT id, code, name, color_name, color_hex, profile_width_mm, frame_type, price_per_meter, fixed_price, cost_per_meter
             FROM frames WHERE is_active = 1 ORDER BY sort_order, id'
        ), ['name', 'color_name']);
        $options = TranslationService::apply('customization_option', $db->fetchAll(
            'SELECT id, option_group, code, name, width_cm, height_cm, panels, price_type, price_value, meta
             FROM customization_options WHERE is_active = 1 ORDER BY option_group, sort_order, id'
        ), ['name']);
        $by = ['size' => [], 'panel_layout' => [], 'border' => [], 'finish' => []];
        foreach ($options as $o) {
            $o['meta'] = $o['meta'] !== null ? (json_decode((string) $o['meta'], true) ?: []) : [];
            if (isset($by[$o['option_group']])) {
                $by[$o['option_group']][] = $o;
            }
        }
        return self::$cache = [
            'materials' => $materials,
            'frames' => $frames,
            'sizes' => $by['size'],
            'layouts' => $by['panel_layout'],
            'borders' => $by['border'],
            'finishes' => $by['finish'],
        ];
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * Restrict to what a customizable product allows (when the product has explicit material/frame maps).
     *
     * @param array<string,mixed>|null $product
     * @return array<string,mixed>
     */
    public static function forProduct(?array $product): array
    {
        $all = self::all();
        if ($product === null) {
            return $all;
        }
        $db = Database::instance();
        $mats = array_map('intval', array_column($db->fetchAll('SELECT product_material_id FROM product_material_map WHERE product_id = ?', [(int) $product['id']]), 'product_material_id'));
        $frs = array_map('intval', array_column($db->fetchAll('SELECT frame_id FROM product_frame_map WHERE product_id = ?', [(int) $product['id']]), 'frame_id'));
        if ($mats !== []) {
            $all['materials'] = array_values(array_filter($all['materials'], fn ($m) => in_array((int) $m['id'], $mats, true)));
        }
        if ($frs !== []) {
            $all['frames'] = array_values(array_filter($all['frames'], fn ($f) => in_array((int) $f['id'], $frs, true)));
        }
        $max = max(1, (int) $product['max_panels']);
        $all['layouts'] = array_values(array_filter($all['layouts'], fn ($l) => (int) $l['panels'] >= (int) $product['min_panels'] && (int) $l['panels'] <= $max));
        return $all;
    }

    /** @return array<string,mixed>|null */
    public static function find(string $group, int|string $key): ?array
    {
        $all = self::all();
        $list = match ($group) {
            'material' => $all['materials'],
            'frame' => $all['frames'],
            'size' => $all['sizes'],
            'layout' => $all['layouts'],
            'border' => $all['borders'],
            'finish' => $all['finishes'],
            default => [],
        };
        foreach ($list as $item) {
            if ((is_int($key) && (int) $item['id'] === $key) || (is_string($key) && $item['code'] === $key)) {
                return $item;
            }
        }
        return null;
    }
}
