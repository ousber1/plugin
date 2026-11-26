<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Builder\BuilderOptions;
use App\Services\TranslationService;

/**
 * Print supports, frames and builder options with their prices (drive the builder and variant pricing).
 */
final class OptionController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $ar = function (string $type): array {
            $out = [];
            foreach (Database::instance()->fetchAll("SELECT entity_id, value FROM translations WHERE entity_type = ? AND locale = 'ar' AND field = 'name'", [$type]) as $t) {
                $out[(int) $t['entity_id']] = $t['value'];
            }
            return $out;
        };
        return $this->view('admin/options/index', [
            'materials' => $db->fetchAll('SELECT * FROM product_materials ORDER BY sort_order, id'),
            'frames' => $db->fetchAll('SELECT * FROM frames ORDER BY sort_order, id'),
            'options' => $db->fetchAll('SELECT * FROM customization_options ORDER BY option_group, sort_order, id'),
            'stock' => $db->fetchAll('SELECT id, sku, name FROM materials WHERE is_active = 1 ORDER BY name'),
            'arMaterials' => $ar('product_material'), 'arFrames' => $ar('frame'), 'arOptions' => $ar('customization_option'),
            'tab' => in_array($this->request->str('tab'), ['materials', 'frames', 'options'], true) ? $this->request->str('tab') : 'materials',
        ]);
    }

    private function money(string $key, bool $required = true): ?string
    {
        $v = trim($this->request->str($key));
        if ($v === '' && !$required) {
            return null;
        }
        if (!preg_match('/^\d{1,8}([.,]\d{1,2})?$/', $v)) {
            throw new \App\Core\ValidationException([$key => __('validation.decimal', ['attribute' => $key])], $this->request->all());
        }
        return Money::normalize($v);
    }

    private function done(string $tab, string $action, string $entity, int $id): Response
    {
        BuilderOptions::flush();
        ActivityLogger::log('option.' . $action, $entity, $id);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.options.index', ['tab' => $tab]) . '#row-' . $entity . '-' . $id);
    }

    public function saveMaterial(?string $id = null): Response
    {
        $this->request->normalizeDecimals(['min_width_cm', 'max_width_cm', 'min_height_cm', 'max_height_cm', 'consumption_factor']);
        $d = $this->validate([
            'code' => 'required|alpha_dash|max:40', 'name' => 'required|max:120', 'description' => 'nullable|max:500',
            'material_type' => 'required|in:canvas,poster,photo_paper,bache,vinyl,other',
            'min_width_cm' => 'required|decimal:1|between:1,1000', 'max_width_cm' => 'required|decimal:1|between:1,1000',
            'min_height_cm' => 'required|decimal:1|between:1,1000', 'max_height_cm' => 'required|decimal:1|between:1,1000',
            'consumption_factor' => 'required|decimal:3|between:1,5', 'inventory_material_id' => 'nullable|integer|exists:materials,id',
            'sort_order' => 'nullable|integer', 'ar_name' => 'nullable|max:120', 'ar_description' => 'nullable|max:500',
        ]);
        $row = [
            'code' => strtolower((string) $d['code']), 'name' => $d['name'], 'description' => $d['description'], 'material_type' => $d['material_type'],
            'price_per_m2' => $this->money('price_per_m2'), 'minimum_price' => $this->money('minimum_price'), 'cost_per_m2' => $this->money('cost_per_m2'),
            'min_width_cm' => $d['min_width_cm'], 'max_width_cm' => $d['max_width_cm'], 'min_height_cm' => $d['min_height_cm'], 'max_height_cm' => $d['max_height_cm'],
            'consumption_factor' => $d['consumption_factor'], 'inventory_material_id' => !empty($d['inventory_material_id']) ? (int) $d['inventory_material_id'] : null,
            'supports_frame' => $this->request->bool('supports_frame') ? 1 : 0, 'is_active' => $this->request->bool('is_active') ? 1 : 0, 'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
        $id = $this->upsert('product_materials', $row, $id, 'code');
        TranslationService::save('product_material', $id, 'ar', ['name' => $d['ar_name'] ?? null, 'description' => $d['ar_description'] ?? null]);
        return $this->done('materials', 'material_saved', 'product_material', $id);
    }

    public function saveFrame(?string $id = null): Response
    {
        $d = $this->validate([
            'code' => 'required|alpha_dash|max:40', 'name' => 'required|max:120', 'color_name' => 'required|max:60', 'color_hex' => 'required|hex_color',
            'profile_width_mm' => 'required|integer|between:5,150', 'frame_type' => 'required|in:wood,aluminium,floating,box,canvas_stretcher',
            'inventory_material_id' => 'nullable|integer|exists:materials,id', 'sort_order' => 'nullable|integer', 'ar_name' => 'nullable|max:120',
        ]);
        $row = [
            'code' => strtolower((string) $d['code']), 'name' => $d['name'], 'color_name' => $d['color_name'], 'color_hex' => strtoupper((string) $d['color_hex']),
            'profile_width_mm' => (int) $d['profile_width_mm'], 'frame_type' => $d['frame_type'],
            'price_per_meter' => $this->money('price_per_meter'), 'fixed_price' => $this->money('fixed_price'), 'cost_per_meter' => $this->money('cost_per_meter'),
            'inventory_material_id' => !empty($d['inventory_material_id']) ? (int) $d['inventory_material_id'] : null,
            'is_active' => $this->request->bool('is_active') ? 1 : 0, 'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
        $id = $this->upsert('frames', $row, $id, 'code');
        TranslationService::save('frame', $id, 'ar', ['name' => $d['ar_name'] ?? null]);
        return $this->done('frames', 'frame_saved', 'frame', $id);
    }

    public function saveOption(?string $id = null): Response
    {
        $this->request->normalizeDecimals(['width_cm', 'height_cm', 'gap_cm']);
        $d = $this->validate([
            'option_group' => 'required|in:size,border,panel_layout,finish', 'code' => 'required|alpha_dash|max:60', 'name' => 'required|max:120',
            'width_cm' => 'nullable|decimal:1|between:1,1000', 'height_cm' => 'nullable|decimal:1|between:1,1000', 'panels' => 'nullable|integer|between:1,5',
            'price_type' => 'required|in:fixed,percent,per_m2', 'gap_cm' => 'nullable|decimal:1|between:0,30', 'sort_order' => 'nullable|integer', 'ar_name' => 'nullable|max:120',
        ]);
        if ($d['option_group'] === 'size' && (empty($d['width_cm']) || empty($d['height_cm']))) {
            throw new \App\Core\ValidationException(['width_cm' => __('admin.size_needs_dims')], $this->request->all());
        }
        if ($d['option_group'] === 'panel_layout' && empty($d['panels'])) {
            throw new \App\Core\ValidationException(['panels' => __('admin.layout_needs_panels')], $this->request->all());
        }
        $row = [
            'option_group' => $d['option_group'], 'code' => (string) $d['code'], 'name' => $d['name'],
            'width_cm' => $d['width_cm'] ?: null, 'height_cm' => $d['height_cm'] ?: null, 'panels' => $d['panels'] ?: null,
            'price_type' => $d['price_type'], 'price_value' => $this->money('price_value'),
            'meta' => $d['option_group'] === 'panel_layout' ? json_encode(['gap_cm' => (float) ($d['gap_cm'] ?? 3)]) : null,
            'is_active' => $this->request->bool('is_active') ? 1 : 0, 'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
        $db = Database::instance();
        $dup = $db->value('SELECT id FROM customization_options WHERE option_group = ? AND code = ? AND id <> ?', [$row['option_group'], $row['code'], (int) ($id ?? 0)]);
        if ($dup !== null) {
            throw new \App\Core\ValidationException(['code' => __('validation.unique', ['attribute' => 'code'])], $this->request->all());
        }
        if ($id === null) {
            $newId = $db->insert('customization_options', $row);
        } else {
            if ($db->value('SELECT id FROM customization_options WHERE id = ?', [(int) $id]) === null) {
                throw new HttpException(404);
            }
            $db->update('customization_options', $row, ['id' => (int) $id]);
            $newId = (int) $id;
        }
        TranslationService::save('customization_option', $newId, 'ar', ['name' => $d['ar_name'] ?? null]);
        return $this->done('options', 'option_saved', 'customization_option', $newId);
    }

    /** @param array<string,mixed> $row */
    private function upsert(string $table, array $row, ?string $id, string $uniqueCol): int
    {
        $db = Database::instance();
        $dup = $db->value("SELECT id FROM {$table} WHERE {$uniqueCol} = ? AND id <> ?", [$row[$uniqueCol], (int) ($id ?? 0)]);
        if ($dup !== null) {
            throw new \App\Core\ValidationException([$uniqueCol => __('validation.unique', ['attribute' => $uniqueCol])], $this->request->all());
        }
        if ($id === null) {
            return $db->insert($table, $row);
        }
        if ($db->value("SELECT id FROM {$table} WHERE id = ?", [(int) $id]) === null) {
            throw new HttpException(404);
        }
        $db->update($table, $row, ['id' => (int) $id]);
        return (int) $id;
    }
}
