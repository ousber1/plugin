<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Helpers\Str;
use App\Services\Catalog\Personalization;
use App\Services\Catalog\ProductFamily;
use App\Services\TranslationService;

/**
 * Back-office data of gifts and laser products: personalisation fields (FR/AR), quantity price breaks, bill of
 * materials, option variants (Colour × Size…) and the laser bed check.
 */
final class ProductExtras
{
    /** @return list<array<string,mixed>> fields with their Arabic texts and choices as text (one per line) */
    public static function fieldsForForm(int $productId): array
    {
        $db = Database::instance();
        $rows = $db->fetchAll('SELECT * FROM product_personalization_fields WHERE product_id = ? ORDER BY sort_order, id', [$productId]);
        $ar = [];
        foreach ($db->fetchAll("SELECT entity_id, field, value FROM translations WHERE entity_type = 'personalization_field' AND locale = 'ar'") as $t) {
            $ar[(int) $t['entity_id']][$t['field']] = $t['value'];
        }
        foreach ($rows as &$r) {
            $r['ar'] = $ar[(int) $r['id']] ?? [];
            $r['choices_text'] = implode("\n", json_decode((string) $r['choices'], true) ?: []);
        }
        unset($r);
        return $rows;
    }

    /**
     * Replace the personalisation fields of a product (rows[id|new][...]).
     *
     * @param array<string|int,mixed> $rows
     * @return int fields kept
     */
    public static function saveFields(int $productId, array $rows): int
    {
        $db = Database::instance();
        $errors = [];
        $clean = [];
        $keys = [];
        foreach ($rows as $rid => $r) {
            if (!is_array($r) || !empty($r['delete'])) {
                if (is_array($r) && is_numeric($rid)) {
                    $clean[] = ['id' => (int) $rid, 'delete' => true];
                }
                continue;
            }
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue; // empty "new field" row
            }
            $type = (string) ($r['field_type'] ?? 'text');
            if (!in_array($type, Personalization::TYPES, true)) {
                $errors["fields.{$rid}"] = __('personalize.err_type');
                continue;
            }
            $key = trim((string) ($r['field_key'] ?? '')) ?: Str::slug($label, '_');
            $key = substr((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($key)), 0, 40) ?: 'field';
            while (in_array($key, $keys, true)) {
                $key .= '_2';
            }
            $keys[] = $key;
            $choices = array_values(array_unique(array_filter(array_map(fn ($c) => mb_substr(trim($c), 0, 80), preg_split('/\R|;/', (string) ($r['choices'] ?? '')) ?: []))));
            if ($type === 'select' && $choices === []) {
                $errors["fields.{$rid}"] = __('personalize.err_choices', ['field' => $label]);
                continue;
            }
            $price = str_replace(',', '.', trim((string) ($r['price_add'] ?? '0'))) ?: '0';
            if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $price)) {
                $errors["fields.{$rid}"] = __('personalize.err_price', ['field' => $label]);
                continue;
            }
            $dim = fn ($v) => ($v = str_replace(',', '.', trim((string) $v))) !== '' && is_numeric($v) && (float) $v > 0 && (float) $v <= 500 ? number_format((float) $v, 1, '.', '') : null;
            $clean[] = [
                'id' => is_numeric($rid) ? (int) $rid : null,
                'row' => [
                    'field_key' => $key, 'field_type' => $type, 'label' => mb_substr($label, 0, 120),
                    'help' => mb_substr(trim((string) ($r['help'] ?? '')), 0, 255) ?: null,
                    'placeholder' => mb_substr(trim((string) ($r['placeholder'] ?? '')), 0, 120) ?: null,
                    'is_required' => !empty($r['is_required']) ? 1 : 0,
                    'max_length' => in_array($type, ['text', 'textarea'], true) && (int) ($r['max_length'] ?? 0) > 0 ? min(2000, (int) $r['max_length']) : null,
                    'choices' => in_array($type, ['select', 'font'], true) && $choices !== [] ? json_encode($choices, JSON_UNESCAPED_UNICODE) : null,
                    'price_add' => Money::normalize($price),
                    'print_width_cm' => $type === 'photo' ? $dim($r['print_width_cm'] ?? '') : null,
                    'print_height_cm' => $type === 'photo' ? $dim($r['print_height_cm'] ?? '') : null,
                    'sort_order' => (int) ($r['sort_order'] ?? 0),
                ],
                'ar' => ['label' => trim((string) ($r['ar_label'] ?? '')) ?: null, 'help' => trim((string) ($r['ar_help'] ?? '')) ?: null, 'placeholder' => trim((string) ($r['ar_placeholder'] ?? '')) ?: null],
            ];
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $kept = 0;
        $db->transaction(function (Database $db) use ($productId, $clean, &$kept): void {
            // free the keys first so renaming / swapping keys never hits the unique index
            foreach ($clean as $c) {
                if (!empty($c['id'])) {
                    $db->query("UPDATE product_personalization_fields SET field_key = CONCAT('tmp_', id) WHERE id = ? AND product_id = ?", [$c['id'], $productId]);
                }
            }
            foreach ($clean as $c) {
                if (!empty($c['delete'])) {
                    $db->query('DELETE FROM product_personalization_fields WHERE id = ? AND product_id = ?', [$c['id'], $productId]);
                    $db->query("DELETE FROM translations WHERE entity_type = 'personalization_field' AND entity_id = ?", [$c['id']]);
                    continue;
                }
                $id = $c['id'];
                if ($id !== null && $db->value('SELECT id FROM product_personalization_fields WHERE id = ? AND product_id = ?', [$id, $productId]) !== null) {
                    $db->update('product_personalization_fields', $c['row'], ['id' => $id]);
                } else {
                    $id = $db->insert('product_personalization_fields', $c['row'] + ['product_id' => $productId]);
                }
                TranslationService::save('personalization_field', (int) $id, 'ar', $c['ar']);
                $kept++;
            }
        });
        Personalization::forget();
        return $kept;
    }

    /** @param array<int|string,mixed> $rows rows[i][min_qty|discount_percent] */
    public static function saveTiers(int $productId, array $rows): int
    {
        $tiers = [];
        foreach ($rows as $r) {
            if (!is_array($r) || trim((string) ($r['min_qty'] ?? '')) === '') {
                continue;
            }
            $q = (int) $r['min_qty'];
            $pct = str_replace(',', '.', trim((string) ($r['discount_percent'] ?? '')));
            if ($q < 2 || $q > 100000 || !is_numeric($pct) || (float) $pct <= 0 || (float) $pct >= 90) {
                throw new ValidationException(['tiers' => __('personalize.err_tier')]);
            }
            $tiers[$q] = number_format((float) $pct, 2, '.', '');
        }
        ksort($tiers);
        // a bigger quantity must never cost more per unit
        $prev = 0.0;
        foreach ($tiers as $pct) {
            if ((float) $pct < $prev) {
                throw new ValidationException(['tiers' => __('personalize.err_tier_order')]);
            }
            $prev = (float) $pct;
        }
        $db = Database::instance();
        $db->transaction(function (Database $db) use ($productId, $tiers): void {
            $db->query('DELETE FROM product_price_tiers WHERE product_id = ?', [$productId]);
            foreach ($tiers as $q => $pct) {
                $db->insert('product_price_tiers', ['product_id' => $productId, 'min_qty' => $q, 'discount_percent' => $pct]);
            }
        });
        return count($tiers);
    }

    /** @param array<int|string,mixed> $rows rows[i][material_id|variant_id|quantity|delete] */
    public static function saveComponents(int $productId, array $rows): int
    {
        $db = Database::instance();
        $lines = [];
        foreach ($rows as $r) {
            if (!is_array($r) || !empty($r['delete']) || (int) ($r['material_id'] ?? 0) <= 0) {
                continue;
            }
            $qty = str_replace(',', '.', trim((string) ($r['quantity'] ?? '')));
            if (!is_numeric($qty) || (float) $qty <= 0 || (float) $qty > 100000) {
                throw new ValidationException(['components' => __('personalize.err_component')]);
            }
            $mid = (int) $r['material_id'];
            $vid = (int) ($r['variant_id'] ?? 0) ?: null;
            if ($db->value('SELECT id FROM materials WHERE id = ?', [$mid]) === null
                || ($vid !== null && $db->value('SELECT id FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $productId]) === null)) {
                throw new ValidationException(['components' => __('personalize.err_component')]);
            }
            $lines[] = ['material_id' => $mid, 'product_variant_id' => $vid, 'quantity' => number_format((float) $qty, 3, '.', '')];
        }
        $db->transaction(function (Database $db) use ($productId, $lines): void {
            $db->query('DELETE FROM product_components WHERE product_id = ?', [$productId]);
            foreach ($lines as $l) {
                $db->insert('product_components', $l + ['product_id' => $productId]);
            }
        });
        return count($lines);
    }

    /**
     * Create the variants Option 1 × Option 2 (e.g. Blanc, Noir × S, M, L) at one price; existing combinations are kept.
     *
     * @param list<string> $values1
     * @param list<string> $values2
     * @return int variants created
     */
    public static function addOptionVariants(array $product, array $values1, array $values2, string $price, string $cost, float $w, float $h): int
    {
        $db = Database::instance();
        $values1 = $values1 ?: [''];
        $values2 = $values2 ?: [''];
        $n = 0;
        $first = (int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ?', [(int) $product['id']]) === 0;
        $sort = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM product_variants WHERE product_id = ?', [(int) $product['id']]);
        foreach ($values1 as $o1) {
            foreach ($values2 as $o2) {
                if ($o1 === '' && $o2 === '') {
                    continue;
                }
                if ($db->value('SELECT id FROM product_variants WHERE product_id = ? AND option1 <=> ? AND option2 <=> ?', [(int) $product['id'], $o1 ?: null, $o2 ?: null]) !== null) {
                    continue;
                }
                $name = implode(' · ', array_filter([$o1, $o2]));
                $db->insert('product_variants', [
                    'product_id' => (int) $product['id'],
                    'sku' => CatalogAdmin::uniqueSku('product_variants', $product['sku'] . '-' . strtoupper(substr(Str::slug($name), 0, 24))),
                    'name' => mb_substr($name, 0, 150), 'option1' => $o1 ?: null, 'option2' => $o2 ?: null,
                    'width_cm' => number_format($w, 1, '.', ''), 'height_cm' => number_format($h, 1, '.', ''),
                    'price' => Money::normalize($price), 'cost_price' => Money::normalize($cost ?: '0'),
                    'is_default' => $first && $n === 0 ? 1 : 0, 'sort_order' => ++$sort,
                ]);
                $n++;
            }
        }
        CatalogAdmin::syncFromVariants((int) $product['id']);
        return $n;
    }

    /** @return array<int,array<string,string>> variant id => [option1, option2] in Arabic */
    public static function variantTranslations(int $productId): array
    {
        $out = [];
        foreach (Database::instance()->fetchAll(
            "SELECT t.entity_id, t.field, t.value FROM translations t JOIN product_variants v ON v.id = t.entity_id
             WHERE t.entity_type = 'product_variant' AND t.locale = 'ar' AND v.product_id = ?",
            [$productId]
        ) as $t) {
            $out[(int) $t['entity_id']][$t['field']] = $t['value'];
        }
        return $out;
    }

    /** Largest bed of an active laser (mm), e.g. [400, 400] for a 4040; null when no laser is declared. @return array{0:int,1:int}|null */
    public static function laserBed(): ?array
    {
        $m = Database::instance()->fetch("SELECT MAX(work_width_mm) AS w, MAX(work_height_mm) AS h FROM machines WHERE machine_type = 'laser' AND status = 'active' AND work_width_mm IS NOT NULL");
        return $m !== null && $m['w'] !== null ? [(int) $m['w'], (int) $m['h']] : null;
    }

    /** Warning when a laser piece is bigger than the bed (it must then be made in several parts). */
    public static function laserWarning(?string $type, float $wCm, float $hCm): ?string
    {
        $bed = self::laserBed();
        if ($bed === null || ProductFamily::of($type) !== 'laser' || $wCm <= 0 || $hCm <= 0) {
            return null;
        }
        [$bw, $bh] = [$bed[0] / 10, $bed[1] / 10];
        $fits = ($wCm <= $bw && $hCm <= $bh) || ($wCm <= $bh && $hCm <= $bw);
        return $fits ? null : __('personalize.laser_too_big', ['w' => cm($wCm), 'h' => cm($hCm), 'bw' => cm($bw), 'bh' => cm($bh)]);
    }
}
