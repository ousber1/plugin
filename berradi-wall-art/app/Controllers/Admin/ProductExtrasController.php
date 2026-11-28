<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Admin\ProductExtras;
use App\Services\Catalog\ProductTemplates;

/**
 * Gifts & laser products in the back office: template library, personalisation fields, quantity price breaks,
 * bill of materials and option variants (Colour × Size…).
 */
final class ProductExtrasController extends Controller
{
    /** @return array<string,mixed> */
    private function product(string $id): array
    {
        $p = Database::instance()->fetch('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if ($p === null) {
            throw new HttpException(404);
        }
        return $p;
    }

    private function toTab(array $p, string $tab): Response
    {
        return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#' . $tab);
    }

    public function templates(): Response
    {
        $db = Database::instance();
        $existing = array_column($db->fetchAll('SELECT sku FROM products WHERE deleted_at IS NULL'), 'sku');
        return $this->view('admin/products/templates', ['templates' => ProductTemplates::all(), 'existing' => $existing]);
    }

    public function createFromTemplate(): Response
    {
        $key = $this->request->str('template');
        if (!isset(ProductTemplates::all()[$key])) {
            throw new HttpException(404);
        }
        $id = ProductTemplates::create($key);
        $this->flash('success', __('templates.created'));
        return $this->redirectRoute('admin.products.edit', ['id' => $id]);
    }

    public function personalization(string $id): Response
    {
        $p = $this->product($id);
        $rows = $this->request->input('fields', []);
        try {
            $n = ProductExtras::saveFields((int) $p['id'], is_array($rows) ? $rows : []);
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $this->request->all());
        }
        ActivityLogger::log('product.personalization_saved', 'product', (int) $p['id'], (string) $n);
        $this->flash('success', __('common.saved'));
        return $this->toTab($p, 'gift');
    }

    public function tiers(string $id): Response
    {
        $p = $this->product($id);
        $rows = $this->request->input('tiers', []);
        try {
            ProductExtras::saveTiers((int) $p['id'], is_array($rows) ? $rows : []);
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $this->request->all());
        }
        ActivityLogger::log('product.tiers_saved', 'product', (int) $p['id']);
        $this->flash('success', __('common.saved'));
        return $this->toTab($p, 'gift');
    }

    public function components(string $id): Response
    {
        $p = $this->product($id);
        $rows = $this->request->input('components', []);
        try {
            ProductExtras::saveComponents((int) $p['id'], is_array($rows) ? $rows : []);
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $this->request->all());
        }
        ActivityLogger::log('product.components_saved', 'product', (int) $p['id']);
        $this->flash('success', __('common.saved'));
        return $this->toTab($p, 'gift');
    }

    public function optionVariants(string $id): Response
    {
        $p = $this->product($id);
        $this->request->normalizeDecimals(['ov_price', 'ov_cost', 'ov_width', 'ov_height']);
        $d = $this->validate([
            'ov_values1' => 'nullable|max:500', 'ov_values2' => 'nullable|max:500',
            'ov_price' => 'required|decimal:2|min:0', 'ov_cost' => 'nullable|decimal:2|min:0',
            'ov_width' => 'nullable|decimal:1|between:0,500', 'ov_height' => 'nullable|decimal:1|between:0,500',
        ], ['ov_price' => __('admin.p_selling_price')]);
        $split = fn ($s) => array_values(array_unique(array_filter(array_map(fn ($x) => mb_substr(trim($x), 0, 80), explode(',', (string) $s)))));
        $v1 = $split($d['ov_values1'] ?? '');
        $v2 = $split($d['ov_values2'] ?? '');
        if ($v1 === [] && $v2 === []) {
            throw new ValidationException(['ov_values1' => __('personalize.err_values')], $this->request->all());
        }
        $w = (float) ($d['ov_width'] ?? 0);
        $h = (float) ($d['ov_height'] ?? 0);
        $n = ProductExtras::addOptionVariants($p, $v1, $v2, (string) $d['ov_price'], (string) ($d['ov_cost'] ?? '0'), $w, $h);
        ActivityLogger::log('product.option_variants', 'product', (int) $p['id'], (string) $n);
        $warn = ProductExtras::laserWarning((string) $p['product_type'], $w, $h);
        $this->flash($warn !== null ? 'warning' : 'success', ($warn !== null ? $warn . ' ' : '') . __('admin.variants_generated', ['n' => $n]));
        return $this->toTab($p, 'variants');
    }
}
