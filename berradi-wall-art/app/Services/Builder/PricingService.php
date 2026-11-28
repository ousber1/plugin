<?php

declare(strict_types=1);

namespace App\Services\Builder;

use App\Core\Database;
use App\Helpers\Money;

/**
 * Server-side (authoritative) price of a custom wall-art configuration.
 * The browser only displays what this service returns; prices are never trusted from the client.
 *
 * Per panel (multi-panel compositions are made of N identical panels):
 *   print   = max(material.minimum_price, panel_area_m² × material.price_per_m²)
 * Then:
 *   layout  = print_total × layout %            (stretching/assembling several panels)
 *   frame   = N × (frame.fixed_price + panel_perimeter_m × frame.price_per_meter)
 *   border  = fixed per panel | % of print | per m²
 *   finish  = fixed per panel | % of print | per m²
 *   product = product/material price adjustment (customizable catalogue products)
 *   unit    = sum rounded UP to the whole dirham; total = unit × quantity
 */
final class PricingService
{
    public const MAX_QTY = 50;

    /**
     * @param array{material_id:int,frame_id?:?int,layout?:string,panel_width_cm:float|string,panel_height_cm:float|string,border?:?string,finish?:?string,quantity?:int,product_id?:?int} $c
     * @return array{ok:bool,errors:array<string,string>,unit_price?:string,total?:string,quantity?:int,breakdown?:list<array{key:string,label:string,amount:string}>,
     *               panels?:int,panel_width_cm?:float,panel_height_cm?:float,overall_width_cm?:float,overall_height_cm?:float,gap_cm?:float,area_m2?:float,unit_cost?:string,
     *               material?:array<string,mixed>,frame?:?array<string,mixed>,layout?:array<string,mixed>,border?:?array<string,mixed>,finish?:?array<string,mixed>}
     */
    public static function quote(array $c): array
    {
        $errors = [];
        $material = BuilderOptions::find('material', (int) ($c['material_id'] ?? 0));
        if ($material === null) {
            return ['ok' => false, 'errors' => ['material_id' => __('builder.err_material')]];
        }
        $layout = BuilderOptions::find('layout', (string) ($c['layout'] ?? 'single'));
        if ($layout === null) {
            $errors['layout'] = __('builder.err_layout');
            $layout = ['code' => 'single', 'name' => '1', 'panels' => 1, 'price_type' => 'percent', 'price_value' => '0', 'meta' => []];
        }
        $panels = max(1, (int) ($layout['panels'] ?? 1));
        $gap = (float) ($layout['meta']['gap_cm'] ?? 0);

        $w = self::cm($c['panel_width_cm'] ?? null);
        $h = self::cm($c['panel_height_cm'] ?? null);
        if ($w === null || $h === null) {
            $errors['size'] = __('builder.err_size');
            return ['ok' => false, 'errors' => $errors];
        }
        if ($w < (float) $material['min_width_cm'] || $h < (float) $material['min_height_cm']
            || max($w, $h) > max((float) $material['max_width_cm'], (float) $material['max_height_cm'])
            || min($w, $h) > min((float) $material['max_width_cm'], (float) $material['max_height_cm'])) {
            $errors['size'] = __('builder.err_size_range', [
                'min' => (int) $material['min_width_cm'],
                'max' => (int) max((float) $material['max_width_cm'], (float) $material['max_height_cm']),
            ]);
        }

        $frame = null;
        if (!empty($c['frame_id'])) {
            $frame = BuilderOptions::find('frame', (int) $c['frame_id']);
            if ($frame === null) {
                $errors['frame_id'] = __('builder.err_frame');
            } elseif ((int) $material['supports_frame'] !== 1) {
                $errors['frame_id'] = __('builder.err_frame_material');
            }
        }
        $border = null;
        if (!empty($c['border'])) {
            $border = BuilderOptions::find('border', (string) $c['border']);
            if ($border === null || $material['material_type'] !== 'canvas') {
                $errors['border'] = __('builder.err_border');
                $border = null;
            }
        }
        $finish = null;
        if (!empty($c['finish'])) {
            $finish = BuilderOptions::find('finish', (string) $c['finish']);
            if ($finish === null) {
                $errors['finish'] = __('builder.err_finish');
            }
        }
        $qty = (int) ($c['quantity'] ?? 1);
        if ($qty < 1 || $qty > self::MAX_QTY) {
            $errors['quantity'] = __('builder.err_quantity', ['max' => self::MAX_QTY]);
            $qty = max(1, min(self::MAX_QTY, $qty));
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        // ---- money, all in integer centimes
        $areaCm2 = (int) round($w * $h);               // one panel, cm²
        $perimeterCm = (int) round(2 * ($w + $h));     // one panel, cm
        $ppm = Money::toCents((string) $material['price_per_m2']);
        $printPanel = max(Money::toCents((string) $material['minimum_price']), self::div($ppm * $areaCm2, 10000));
        $print = $printPanel * $panels;
        $lines = [['key' => 'print', 'label' => __('builder.line_print', ['material' => $material['name'], 'panels' => $panels]), 'cents' => $print]];

        $layoutCents = self::optionCents($layout, $print, $areaCm2 * $panels, $panels);
        if ($layoutCents > 0) {
            $lines[] = ['key' => 'layout', 'label' => __('builder.line_layout', ['layout' => $layout['name']]), 'cents' => $layoutCents];
        }
        $frameCents = 0;
        if ($frame !== null) {
            $frameCents = $panels * (Money::toCents((string) $frame['fixed_price']) + self::div(Money::toCents((string) $frame['price_per_meter']) * $perimeterCm, 100));
            $lines[] = ['key' => 'frame', 'label' => __('builder.line_frame', ['frame' => $frame['name']]), 'cents' => $frameCents];
        }
        if ($border !== null) {
            $cents = self::optionCents($border, $print, $areaCm2 * $panels, $panels);
            if ($cents > 0) {
                $lines[] = ['key' => 'border', 'label' => $border['name'], 'cents' => $cents];
            }
        }
        if ($finish !== null) {
            $cents = self::optionCents($finish, $print, $areaCm2 * $panels, $panels);
            if ($cents > 0) {
                $lines[] = ['key' => 'finish', 'label' => $finish['name'], 'cents' => $cents];
            }
        }
        if (!empty($c['product_id'])) {
            $adj = Database::instance()->value(
                'SELECT price_adjustment FROM product_material_map WHERE product_id = ? AND product_material_id = ?',
                [(int) $c['product_id'], (int) $material['id']]
            );
            if ($adj !== null && Money::toCents((string) $adj) !== 0) {
                $lines[] = ['key' => 'product', 'label' => __('builder.line_product'), 'cents' => Money::toCents((string) $adj)];
            }
        }
        $sum = array_sum(array_column($lines, 'cents'));
        $unit = (int) (ceil($sum / 100) * 100); // round up to the whole dirham

        $cost = self::div(Money::toCents((string) $material['cost_per_m2']) * $areaCm2 * $panels, 10000);
        if ($frame !== null) {
            $cost += $panels * self::div(Money::toCents((string) $frame['cost_per_meter']) * $perimeterCm, 100);
        }

        return [
            'ok' => true,
            'errors' => [],
            'unit_price' => Money::fromCents($unit),
            'total' => Money::fromCents($unit * $qty),
            'quantity' => $qty,
            'unit_cost' => Money::fromCents($cost),
            'breakdown' => array_map(fn ($l) => ['key' => $l['key'], 'label' => $l['label'], 'amount' => Money::fromCents($l['cents'])], $lines),
            'panels' => $panels,
            'panel_width_cm' => $w,
            'panel_height_cm' => $h,
            'gap_cm' => $gap,
            'overall_width_cm' => round($w * $panels + $gap * ($panels - 1), 1),
            'overall_height_cm' => $h,
            'area_m2' => round($areaCm2 * $panels / 10000, 3),
            'material' => $material,
            'frame' => $frame,
            'layout' => $layout,
            'border' => $border,
            'finish' => $finish,
        ];
    }

    /** @param array<string,mixed> $opt */
    private static function optionCents(array $opt, int $printCents, int $totalAreaCm2, int $panels): int
    {
        $value = Money::toCents((string) $opt['price_value']);
        return match ($opt['price_type']) {
            'percent' => self::div($printCents * $value, 10000),        // value is a percentage in cents (10.00 % = 1000)
            'per_m2' => self::div($value * $totalAreaCm2, 10000),
            default => $value * $panels,                                  // fixed, per panel
        };
    }

    private static function div(int $num, int $den): int
    {
        return intdiv($num + intdiv($den, 2), $den); // round half up (non-negative values)
    }

    /** Parse a dimension in cm (one decimal), null when invalid. */
    private static function cm(mixed $v): ?float
    {
        if (!is_numeric($v)) {
            return null;
        }
        $f = round((float) $v, 1);
        return $f > 0 && $f <= 1000 ? $f : null;
    }

    /**
     * Effective print resolution of a crop.
     * @param int $cropWidthPx  crop width in source pixels (after rotation)
     * @param float $printWidthCm printed width covered by this crop
     */
    public static function dpi(int $cropWidthPx, float $printWidthCm): int
    {
        return $printWidthCm > 0 ? (int) floor($cropWidthPx / ($printWidthCm / 2.54)) : 0;
    }

    /** ok | warning | low according to the admin thresholds. */
    public static function quality(int $dpi): string
    {
        $warn = (int) setting('low_dpi_warning', 100);
        $min = (int) setting('min_dpi_block', 50);
        return $dpi < $min ? 'low' : ($dpi < $warn ? 'warning' : 'ok');
    }
}
