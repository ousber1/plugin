<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * Product families of the print shop and how each one is produced.
 *
 *  wall   decorative wall art: canvas, framed, poster, photo print, multi-panel, limited edition
 *  gift   personalised gifts: printed objects (sublimation / DTF: mugs, bottles, cushions, puzzles…), textiles,
 *         stationery (notebooks, cards, calendars), other gifts
 *  laser  CO2 laser: engraving (wood, leather, glass, anodised metal, acrylic) and cutting (names, wall decor, lamps)
 */
final class ProductFamily
{
    public const FAMILIES = [
        'wall' => ['canvas', 'framed', 'poster', 'photo_print', 'multi_panel', 'limited'],
        'gift' => ['gift_print', 'textile', 'stationery', 'gift_other'],
        'laser' => ['laser_engraving', 'laser_cut'],
    ];

    /** Production stages in workshop order (the board shows them in this order). */
    public const STAGES = ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'print', 'engrave', 'press', 'cut_finish', 'assemble', 'frame', 'quality_control', 'package', 'ship'];

    /** Route of each product type; optional stages are removed by ProductionService::stagesFor(). */
    private const ROUTES = [
        'wall' => ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'print', 'cut_finish', 'frame', 'quality_control', 'package', 'ship'],
        'gift_print' => ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'print', 'press', 'quality_control', 'package', 'ship'],
        'textile' => ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'print', 'press', 'quality_control', 'package', 'ship'],
        'stationery' => ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'print', 'cut_finish', 'assemble', 'quality_control', 'package', 'ship'],
        'gift_other' => ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'assemble', 'quality_control', 'package', 'ship'],
        'laser_engraving' => ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'engrave', 'assemble', 'quality_control', 'package', 'ship'],
        'laser_cut' => ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'engrave', 'assemble', 'quality_control', 'package', 'ship'],
    ];

    /** The stage whose completion books the material consumption (the object is physically made there). */
    private const CONSUMING_STAGE = [
        'wall' => 'print', 'gift_print' => 'print', 'textile' => 'print', 'stationery' => 'print',
        'gift_other' => 'assemble', 'laser_engraving' => 'engrave', 'laser_cut' => 'engrave',
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_merge(...array_values(self::FAMILIES));
    }

    public static function of(?string $type): string
    {
        foreach (self::FAMILIES as $family => $types) {
            if (in_array($type, $types, true)) {
                return $family;
            }
        }
        return 'wall';
    }

    public static function isWallArt(?string $type): bool
    {
        return self::of($type) === 'wall';
    }

    /** @return list<string> */
    public static function route(?string $type): array
    {
        return self::ROUTES[self::isWallArt($type) ? 'wall' : (string) $type] ?? self::ROUTES['wall'];
    }

    public static function consumingStage(?string $type): string
    {
        return self::CONSUMING_STAGE[self::isWallArt($type) ? 'wall' : (string) $type] ?? 'print';
    }

    /** Machine type that usually does a stage (pre-selected on the job). */
    public static function machineTypeFor(string $stage): ?string
    {
        return match ($stage) {
            'engrave' => 'laser',
            'press' => 'press',
            'print' => 'printer',
            default => null,
        };
    }
}
