<?php

declare(strict_types=1);

namespace App\Services\Builder;

use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Helpers\Str;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\ImageUploadService;
use App\Services\OwnerToken;
use App\Services\UploadException;

/**
 * Validates and saves a complete builder configuration ("custom design").
 *
 * Everything the client sends is re-checked: option ids, product restrictions, photo ownership,
 * crop geometry and aspect ratio, print resolution (DPI) and the price (recomputed by PricingService).
 * Crops are stored in ORIGINAL photo pixels so production can print from the untouched original.
 */
final class DesignService
{
    public const MODES = ['span', 'per_panel'];
    private const RATIO_TOLERANCE = 0.03;

    /**
     * @param array<string,mixed> $in
     * @return array{uuid:string,unit_price:string,total:string,quantity:int,quality:string,min_dpi:int}
     * @throws ValidationException
     */
    public static function save(array $in): array
    {
        $errors = [];
        $product = null;
        if (!empty($in['product'])) {
            $product = Database::instance()->fetch(
                'SELECT id, slug, title, min_panels, max_panels FROM products WHERE slug = ? AND is_active = 1 AND is_customizable = 1 AND deleted_at IS NULL',
                [(string) $in['product']]
            );
            if ($product === null) {
                throw new ValidationException(['product' => __('builder.err_product')]);
            }
            $allowed = BuilderOptions::forProduct($product);
            if (!in_array((int) ($in['material_id'] ?? 0), array_map('intval', array_column($allowed['materials'], 'id')), true)) {
                $errors['material_id'] = __('builder.err_material');
            }
            if (!empty($in['frame_id']) && !in_array((int) $in['frame_id'], array_map('intval', array_column($allowed['frames'], 'id')), true)) {
                $errors['frame_id'] = __('builder.err_frame');
            }
            if (!in_array((string) ($in['layout'] ?? 'single'), array_column($allowed['layouts'], 'code'), true)) {
                $errors['layout'] = __('builder.err_layout');
            }
        }

        $quote = PricingService::quote([
            'material_id' => (int) ($in['material_id'] ?? 0),
            'frame_id' => !empty($in['frame_id']) ? (int) $in['frame_id'] : null,
            'layout' => (string) ($in['layout'] ?? 'single'),
            'panel_width_cm' => $in['panel_width_cm'] ?? null,
            'panel_height_cm' => $in['panel_height_cm'] ?? null,
            'border' => $in['border'] ?? null,
            'finish' => $in['finish'] ?? null,
            'quantity' => (int) ($in['quantity'] ?? 1),
            'product_id' => $product['id'] ?? null,
        ]);
        $errors += $quote['errors'];
        if ($errors !== [] || !$quote['ok']) {
            throw new ValidationException($errors ?: ['size' => __('builder.err_size')]);
        }

        $panels = (int) $quote['panels'];
        $mode = $panels > 1 && ($in['mode'] ?? 'span') === 'per_panel' ? 'per_panel' : 'span';
        $images = is_array($in['images'] ?? null) ? array_values($in['images']) : [];
        $expected = $mode === 'per_panel' ? $panels : 1;
        if (count($images) !== $expected) {
            throw new ValidationException(['images' => __('builder.err_images', ['count' => $expected])]);
        }

        $targetRatio = $mode === 'span'
            ? (float) $quote['overall_width_cm'] / (float) $quote['overall_height_cm']
            : (float) $quote['panel_width_cm'] / (float) $quote['panel_height_cm'];
        $printWidth = $mode === 'span' ? (float) $quote['overall_width_cm'] : (float) $quote['panel_width_cm'];

        $checked = [];
        $minDpi = PHP_INT_MAX;
        $usedPanels = [];
        foreach ($images as $i => $img) {
            $row = is_array($img) ? CustomerUploadService::findAccessible((string) ($img['uuid'] ?? ''), false) : null;
            if ($row === null) {
                throw new ValidationException(['images' => __('builder.err_image_access')]);
            }
            $panel = (int) ($img['panel'] ?? $i);
            if ($panel < 0 || $panel >= $expected || isset($usedPanels[$panel])) {
                throw new ValidationException(['images' => __('builder.err_crop')]);
            }
            $usedPanels[$panel] = true;
            $crop = self::validateCrop((array) ($img['crop'] ?? []), (int) $row['width_px'], (int) $row['height_px'], $targetRatio);
            if ($crop === null) {
                throw new ValidationException(['images' => __('builder.err_crop')]);
            }
            $dpi = PricingService::dpi((int) $crop['width'], $printWidth);
            $minDpi = min($minDpi, $dpi);
            $checked[] = ['row' => $row, 'panel' => $panel, 'crop' => $crop, 'dpi' => $dpi];
        }
        $quality = PricingService::quality($minDpi);
        if ($quality === 'low') {
            throw new ValidationException(['images' => __('builder.err_dpi', ['dpi' => $minDpi, 'min' => (int) setting('min_dpi_block', 50)])]);
        }

        $orientation = abs((float) $quote['overall_width_cm'] - (float) $quote['overall_height_cm']) < 0.5
            ? 'square' : ((float) $quote['overall_width_cm'] > (float) $quote['overall_height_cm'] ? 'landscape' : 'portrait');
        $uuid = Str::uuid();
        $previewRel = self::storePreview($uuid, (string) ($in['preview'] ?? ''));

        $config = [
            'version' => 1,
            'mode' => $mode,
            'layout' => $quote['layout']['code'],
            'panels' => $panels,
            'gap_cm' => $quote['gap_cm'],
            'panel_width_cm' => $quote['panel_width_cm'],
            'panel_height_cm' => $quote['panel_height_cm'],
            'overall_width_cm' => $quote['overall_width_cm'],
            'overall_height_cm' => $quote['overall_height_cm'],
            'material' => ['id' => (int) $quote['material']['id'], 'code' => $quote['material']['code'], 'name' => $quote['material']['name']],
            'frame' => $quote['frame'] ? ['id' => (int) $quote['frame']['id'], 'code' => $quote['frame']['code'], 'name' => $quote['frame']['name'], 'color' => $quote['frame']['color_hex']] : null,
            'border' => $quote['border'] ? ['code' => $quote['border']['code'], 'name' => $quote['border']['name']] : null,
            'finish' => $quote['finish'] ? ['code' => $quote['finish']['code'], 'name' => $quote['finish']['name']] : null,
            'quantity' => $quote['quantity'],
            'breakdown' => $quote['breakdown'],
            'unit_cost' => $quote['unit_cost'],
            'min_dpi' => $minDpi,
            'product' => $product ? ['id' => (int) $product['id'], 'slug' => $product['slug'], 'title' => $product['title']] : null,
        ];
        $customer = Auth::customer();

        Database::instance()->transaction(function (Database $db) use ($uuid, $customer, $product, $quote, $orientation, $panels, $config, $previewRel, $quality, $checked): void {
            $designId = $db->insert('custom_designs', [
                'uuid' => $uuid,
                'customer_id' => $customer['id'] ?? null,
                'owner_token' => OwnerToken::hash(),
                'product_id' => $product['id'] ?? null,
                'product_material_id' => (int) $quote['material']['id'],
                'frame_id' => $quote['frame'] ? (int) $quote['frame']['id'] : null,
                'width_cm' => $quote['panel_width_cm'],
                'height_cm' => $quote['panel_height_cm'],
                'orientation' => $orientation,
                'panels' => $panels,
                'border_option' => $quote['border']['code'] ?? null,
                'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'preview_path' => $previewRel,
                'unit_price' => $quote['unit_price'],
                'quality_status' => $quality,
                'status' => 'draft',
            ]);
            foreach ($checked as $c) {
                $db->insert('custom_design_images', [
                    'custom_design_id' => $designId,
                    'uploaded_image_id' => (int) $c['row']['id'],
                    'panel_index' => $c['panel'],
                    'crop' => json_encode($c['crop']),
                    'effective_dpi' => min(65535, $c['dpi']),
                ]);
                if ($c['row']['status'] === 'temporary') {
                    $db->update('uploaded_images', ['status' => 'attached'], ['id' => (int) $c['row']['id']]);
                }
            }
            ActivityLogger::log('design.saved', 'custom_design', $designId, $uuid, ['price' => $quote['unit_price'], 'dpi' => $config['min_dpi']]);
        });

        return [
            'uuid' => $uuid,
            'unit_price' => (string) $quote['unit_price'],
            'total' => (string) $quote['total'],
            'quantity' => (int) $quote['quantity'],
            'quality' => $quality,
            'min_dpi' => $minDpi,
        ];
    }

    /**
     * Crop in original pixels after rotation: {x, y, width, height, rotate}.
     * @param array<string,mixed> $c
     * @return array{x:int,y:int,width:int,height:int,rotate:int}|null
     */
    public static function validateCrop(array $c, int $imgW, int $imgH, float $targetRatio): ?array
    {
        foreach (['x', 'y', 'width', 'height'] as $k) {
            if (!isset($c[$k]) || !is_numeric($c[$k])) {
                return null;
            }
        }
        $rotate = (int) ($c['rotate'] ?? 0);
        $rotate = (($rotate % 360) + 360) % 360;
        if (!in_array($rotate, [0, 90, 180, 270], true)) {
            return null;
        }
        [$W, $H] = in_array($rotate, [90, 270], true) ? [$imgH, $imgW] : [$imgW, $imgH];
        $x = (int) round((float) $c['x']);
        $y = (int) round((float) $c['y']);
        $w = (int) round((float) $c['width']);
        $h = (int) round((float) $c['height']);
        $tol = max(3, (int) round(max($W, $H) * 0.005));
        if ($w < 50 || $h < 50 || $x < -$tol || $y < -$tol || $x + $w > $W + $tol || $y + $h > $H + $tol) {
            return null;
        }
        if (abs(($w / $h) - $targetRatio) / $targetRatio > self::RATIO_TOLERANCE) {
            return null;
        }
        // clamp into the image
        $x = max(0, min($x, $W - 1));
        $y = max(0, min($y, $H - 1));
        $w = min($w, $W - $x);
        $h = min($h, $H - $y);
        return ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'rotate' => $rotate];
    }

    /** Decode the browser-rendered mockup (data URL), validate and re-encode it privately. */
    private static function storePreview(string $uuid, string $dataUrl): ?string
    {
        if ($dataUrl === '' || !preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        if (strlen($m[2]) > 4 * 1024 * 1024 * 4 / 3) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bwap');
        file_put_contents($tmp, $bin);
        try {
            $v = ImageUploadService::validate(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'preview'], ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 4, true);
            $sub = 'designs/' . date('Y/m');
            $dir = (string) Config::get('uploads.preview_dir') . '/' . $sub;
            if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                return null;
            }
            $rel = $sub . '/' . $uuid . '.' . $v['ext'];
            return ImageUploadService::reencode($tmp, $dir . '/' . $uuid . '.' . $v['ext'], $v['mime'], 1600, 85) ? $rel : null;
        } catch (UploadException) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array<string,mixed>|null design the visitor may see (owner, customer, or staff) */
    public static function findAccessible(string $uuid): ?array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
            return null;
        }
        $d = Database::instance()->fetch('SELECT * FROM custom_designs WHERE uuid = ?', [$uuid]);
        if ($d === null) {
            return null;
        }
        $customer = Auth::customer();
        $own = ($customer !== null && $d['customer_id'] !== null && (int) $d['customer_id'] === (int) $customer['id'])
            || ($d['customer_id'] === null && $d['owner_token'] !== null && hash_equals((string) $d['owner_token'], OwnerToken::hash()));
        if (!$own && !Auth::can('artwork.view')) {
            return null;
        }
        $d['config'] = json_decode((string) $d['config'], true) ?: [];
        $d['images'] = Database::instance()->fetchAll(
            'SELECT di.panel_index, di.crop, di.effective_dpi, u.uuid, u.width_px, u.height_px, u.status AS image_status, u.original_name
             FROM custom_design_images di JOIN uploaded_images u ON u.id = di.uploaded_image_id
             WHERE di.custom_design_id = ? ORDER BY di.panel_index',
            [(int) $d['id']]
        );
        return $d;
    }

    /** Owner deletes a design that is not ordered. */
    public static function delete(array $design): bool
    {
        if (!in_array($design['status'], ['draft', 'abandoned', 'in_cart'], true)) {
            return false;
        }
        if (!empty($design['preview_path']) && !str_contains((string) $design['preview_path'], '..')) {
            @unlink((string) Config::get('uploads.preview_dir') . '/' . $design['preview_path']);
        }
        Database::instance()->delete('custom_designs', ['id' => (int) $design['id']]);
        ActivityLogger::log('design.deleted', 'custom_design', (int) $design['id']);
        return true;
    }
}
