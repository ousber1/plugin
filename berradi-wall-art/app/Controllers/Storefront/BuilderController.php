<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\Builder\BuilderOptions;
use App\Services\Builder\CustomerUploadService;
use App\Services\Builder\DesignService;
use App\Services\Builder\PricingService;
use App\Services\OwnerToken;
use App\Services\SeoService;
use App\Services\UploadException;

/**
 * Custom Wall Art Builder: page, private photo uploads, live pricing API and design saving.
 */
final class BuilderController extends Controller
{
    public function index(): Response
    {
        OwnerToken::raw(); // make sure the ownership cookie exists before the first upload
        $product = null;
        $slug = (string) $this->request->query('product', '');
        if ($slug !== '') {
            $product = Database::instance()->fetch(
                'SELECT id, slug, title, product_type, min_panels, max_panels FROM products WHERE slug = ? AND is_active = 1 AND is_customizable = 1 AND deleted_at IS NULL',
                [$slug]
            );
            if ($product !== null) {
                $product = \App\Services\TranslationService::applyOne('product', $product, ['title']);
            }
        }
        $options = BuilderOptions::forProduct($product);
        $defaults = $this->defaults($product, $options);
        $config = [
            'product' => $product ? ['slug' => $product['slug'], 'title' => $product['title']] : null,
            'materials' => array_map(fn ($m) => [
                'id' => (int) $m['id'], 'code' => $m['code'], 'name' => $m['name'], 'description' => $m['description'], 'type' => $m['material_type'],
                'supportsFrame' => (int) $m['supports_frame'] === 1,
                'min' => (float) $m['min_width_cm'], 'max' => max((float) $m['max_width_cm'], (float) $m['max_height_cm']),
                'maxShort' => min((float) $m['max_width_cm'], (float) $m['max_height_cm']),
            ], $options['materials']),
            'frames' => array_map(fn ($f) => ['id' => (int) $f['id'], 'name' => $f['name'], 'color' => $f['color_hex'], 'type' => $f['frame_type'], 'profileMm' => (int) $f['profile_width_mm']], $options['frames']),
            'sizes' => array_map(fn ($s) => ['code' => $s['code'], 'w' => (float) $s['width_cm'], 'h' => (float) $s['height_cm']], $options['sizes']),
            'layouts' => array_map(fn ($l) => ['code' => $l['code'], 'name' => $l['name'], 'panels' => (int) $l['panels'], 'gap' => (float) ($l['meta']['gap_cm'] ?? 0)], $options['layouts']),
            'borders' => array_map(fn ($b) => ['code' => $b['code'], 'name' => $b['name']], $options['borders']),
            'finishes' => array_map(fn ($f) => ['code' => $f['code'], 'name' => $f['name']], $options['finishes']),
            'defaults' => $defaults,
            'limits' => [
                'maxMb' => CustomerUploadService::maxMb(),
                'maxFiles' => (int) setting('upload_max_files', 10),
                'dpiWarn' => (int) setting('low_dpi_warning', 100),
                'dpiMin' => (int) setting('min_dpi_block', 50),
                'maxQty' => PricingService::MAX_QTY,
            ],
            'urls' => [
                'upload' => route('builder.upload'),
                'price' => route('builder.price'),
                'save' => route('builder.design.save'),
                'image' => url('builder/image/__UUID__/__VARIANT__'),
                'deleteImage' => url('builder/image/__UUID__/delete'),
                'whatsapp' => whatsapp_link(null, ''),
            ],
            'recent' => $this->recentUploads(),
            'currency' => (string) setting('currency_symbol', 'DH'),
            'currencyAfter' => (string) setting('currency_position', 'after') !== 'before',
            'i18n' => \App\Core\Translator::all('builder'),
        ];
        return $this->view('storefront/builder/index', [
            'config' => $config,
            'product' => $product,
            'options' => $options,
            'seo' => SeoService::page(__('builder.title'), __('builder.meta'), ['canonical' => '/custom-wall-art']),
        ]);
    }

    /** Photos this visitor uploaded recently, so a page reload does not lose them. @return list<array<string,mixed>> */
    private function recentUploads(): array
    {
        $customer = auth_customer();
        $rows = Database::instance()->fetchAll(
            "SELECT uuid, original_name, width_px, height_px, size_bytes FROM uploaded_images
             WHERE status IN ('temporary','attached') AND preview_path IS NOT NULL AND created_at > NOW() - INTERVAL 7 DAY
               AND ((customer_id IS NULL AND owner_token = ?) OR (customer_id IS NOT NULL AND customer_id = ?))
             ORDER BY id DESC LIMIT 12",
            [OwnerToken::hash(), (int) ($customer['id'] ?? 0)]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'uuid' => $r['uuid'], 'name' => $r['original_name'], 'width' => (int) $r['width_px'], 'height' => (int) $r['height_px'], 'size' => (int) $r['size_bytes'],
                'preview' => url('builder/image/' . $r['uuid'] . '/preview'), 'thumb' => url('builder/image/' . $r['uuid'] . '/thumb'),
            ];
        }
        return $out;
    }

    /**
     * Sensible first configuration (product type decides material / frame).
     *
     * @param array<string,mixed>|null $product
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function defaults(?array $product, array $options): array
    {
        $byCode = [];
        foreach ($options['materials'] as $m) {
            $byCode[$m['code']] = (int) $m['id'];
        }
        $type = $product['product_type'] ?? 'canvas';
        $materialCode = match ($type) {
            'framed', 'photo_print' => 'photo_paper',
            'poster' => 'poster',
            default => 'canvas',
        };
        $materialId = $byCode[$materialCode] ?? (int) ($options['materials'][0]['id'] ?? 0);
        $frameId = null;
        if ($type === 'framed' && $options['frames'] !== []) {
            $frameId = (int) $options['frames'][0]['id'];
        }
        $layout = $options['layouts'][0]['code'] ?? 'single';
        foreach ($options['layouts'] as $l) {
            if ((int) $l['panels'] === max(1, (int) ($product['min_panels'] ?? 1))) {
                $layout = $l['code'];
                break;
            }
        }
        $size = $options['sizes'][1] ?? $options['sizes'][0] ?? ['width_cm' => 40, 'height_cm' => 60];
        return [
            'materialId' => $materialId,
            'frameId' => $frameId,
            'layout' => $layout,
            'width' => (float) $size['width_cm'],
            'height' => (float) $size['height_cm'],
            'border' => $materialCode === 'canvas' && $options['borders'] !== [] ? $options['borders'][0]['code'] : null,
            'finish' => $options['finishes'][0]['code'] ?? null,
        ];
    }

    public function upload(): Response
    {
        $file = $this->request->file('photo');
        if ($file === null) {
            return $this->json(['ok' => false, 'message' => __('validation.upload_missing')], 422);
        }
        // per-visitor quota of active photos (abuse protection)
        $active = (int) Database::instance()->value(
            "SELECT COUNT(*) FROM uploaded_images WHERE owner_token = ? AND status IN ('temporary','attached') AND created_at > NOW() - INTERVAL 1 DAY",
            [OwnerToken::hash()]
        );
        if ($active >= max(20, 4 * (int) setting('upload_max_files', 10))) {
            return $this->json(['ok' => false, 'message' => __('builder.quota')], 429);
        }
        try {
            $info = (new CustomerUploadService())->store($file, $this->request->file('preview'));
        } catch (UploadException $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        return $this->json([
            'ok' => true,
            'image' => $info + [
                'preview' => url('builder/image/' . $info['uuid'] . '/preview'),
                'thumb' => url('builder/image/' . $info['uuid'] . '/thumb'),
            ],
        ]);
    }

    public function image(string $uuid, string $variant): Response
    {
        if (!in_array($variant, ['preview', 'thumb', 'original'], true)) {
            throw new HttpException(404);
        }
        $row = CustomerUploadService::findAccessible($uuid);
        if ($row === null) {
            throw new HttpException(404); // never reveal whether someone else's photo exists
        }
        return (new CustomerUploadService())->serve($row, $variant);
    }

    public function deleteImage(string $uuid): Response
    {
        $row = CustomerUploadService::findAccessible($uuid, false);
        $status = $row === null ? 404 : (CustomerUploadService::isDeletable($row) ? 200 : 409);
        if ($status === 200) {
            (new CustomerUploadService())->purge($row, 'customer');
        }
        $message = match ($status) {
            200 => __('builder.photo_deleted'),
            409 => __('builder.cannot_delete_ordered'),
            default => __('errors.404_title'),
        };
        if ($this->request->isAjax()) {
            return $this->json(['ok' => $status === 200, 'message' => $message], $status);
        }
        $this->flash($status === 200 ? 'success' : 'danger', $message);
        return auth_customer() ? $this->redirectRoute('customer.designs') : $this->redirectRoute('builder.index');
    }

    public function price(): Response
    {
        $in = $this->request->post();
        $quote = PricingService::quote([
            'material_id' => (int) ($in['material_id'] ?? 0),
            'frame_id' => !empty($in['frame_id']) ? (int) $in['frame_id'] : null,
            'layout' => (string) ($in['layout'] ?? 'single'),
            'panel_width_cm' => $in['panel_width_cm'] ?? null,
            'panel_height_cm' => $in['panel_height_cm'] ?? null,
            'border' => $in['border'] ?? null,
            'finish' => $in['finish'] ?? null,
            'quantity' => (int) ($in['quantity'] ?? 1),
            'product_id' => $this->productId((string) ($in['product'] ?? '')),
        ]);
        if (!$quote['ok']) {
            return $this->json(['ok' => false, 'errors' => $quote['errors'], 'message' => (string) reset($quote['errors'])], 422);
        }
        return $this->json([
            'ok' => true,
            'unit_price' => $quote['unit_price'],
            'total' => $quote['total'],
            'unit_label' => money($quote['unit_price']),
            'total_label' => money($quote['total']),
            'quantity' => $quote['quantity'],
            'breakdown' => array_map(fn ($l) => ['label' => $l['label'], 'amount' => money($l['amount'])], $quote['breakdown']),
            'panels' => $quote['panels'],
            'overall' => ['w' => $quote['overall_width_cm'], 'h' => $quote['overall_height_cm']],
            'panel' => ['w' => $quote['panel_width_cm'], 'h' => $quote['panel_height_cm']],
            'gap' => $quote['gap_cm'],
        ]);
    }

    private function productId(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }
        $id = Database::instance()->value('SELECT id FROM products WHERE slug = ? AND is_customizable = 1 AND is_active = 1', [$slug]);
        return $id !== null ? (int) $id : null;
    }

    public function saveDesign(): Response
    {
        try {
            $result = DesignService::save($this->request->post());
        } catch (ValidationException $e) {
            return $this->json(['ok' => false, 'errors' => $e->errors, 'message' => (string) (array_values($e->errors)[0] ?? '')], 422);
        }
        return $this->json([
            'ok' => true,
            'design' => $result + [
                'unit_label' => money($result['unit_price']),
                'total_label' => money($result['total']),
                'url' => route('builder.design.show', ['uuid' => $result['uuid']]),
            ],
        ]);
    }

    public function showDesign(string $uuid): Response
    {
        $design = DesignService::findAccessible($uuid);
        if ($design === null) {
            throw new HttpException(404);
        }
        $c = $design['config'];
        $ref = strtoupper(substr($design['uuid'], 0, 8));
        $message = __('builder.whatsapp_design', [
            'ref' => $ref,
            'size' => cm($c['overall_width_cm']) . ' × ' . cm($c['overall_height_cm']) . ' cm',
            'material' => $c['material']['name'] ?? '',
            'price' => money((string) $design['unit_price']),
        ]);
        return $this->view('storefront/builder/design', [
            'design' => $design,
            'ref' => $ref,
            'whatsapp' => whatsapp_link(null, $message),
            'seo' => SeoService::page(__('builder.design_title'), null, ['noindex' => true]),
        ]);
    }

    public function designPreview(string $uuid): Response
    {
        $design = DesignService::findAccessible($uuid);
        $path = $design && $design['preview_path'] && !str_contains((string) $design['preview_path'], '..')
            ? (string) config('uploads.preview_dir') . '/' . $design['preview_path'] : null;
        if ($path === null || !is_file($path)) {
            throw new HttpException(404);
        }
        $mime = str_ends_with($path, '.png') ? 'image/png' : (str_ends_with($path, '.webp') ? 'image/webp' : 'image/jpeg');
        return Response::file($path, $mime);
    }

    public function deleteDesign(string $uuid): Response
    {
        $design = DesignService::findAccessible($uuid);
        if ($design === null || \App\Services\Auth::can('artwork.view') && !$this->owns($design)) {
            throw new HttpException(404);
        }
        $ok = DesignService::delete($design);
        $this->flash($ok ? 'success' : 'danger', $ok ? __('builder.design_deleted') : __('builder.cannot_delete_ordered'));
        return auth_customer() ? $this->redirectRoute('customer.designs') : $this->redirectRoute('builder.index');
    }

    /** @param array<string,mixed> $design */
    private function owns(array $design): bool
    {
        $c = auth_customer();
        return ($c !== null && (int) $design['customer_id'] === (int) $c['id'])
            || ($design['customer_id'] === null && hash_equals((string) $design['owner_token'], OwnerToken::hash()));
    }
}
