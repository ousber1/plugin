<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\ActivityLogger;

/**
 * Raw-material stock: every change is a signed stock movement with the balance after it.
 * Entries with a cost update the weighted average cost (WAC); exits are valued at the current WAC.
 */
final class InventoryService
{
    public const TYPES = ['in', 'out', 'adjustment', 'production', 'waste', 'return'];

    /** Quantities are DECIMAL(14,3): work in thousandths to avoid float drift. */
    public static function toMilli(string|int|float $q): int
    {
        $s = str_replace([' ', ','], ['', '.'], trim((string) $q));
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            throw new \InvalidArgumentException('Invalid quantity');
        }
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        [$int, $dec] = array_pad(explode('.', $s, 2), 2, '');
        $dec = substr(str_pad($dec, 4, '0'), 0, 4);
        $milli = (int) $int * 1000 + intdiv((int) $dec, 10) + ((int) $dec % 10 >= 5 ? 1 : 0);
        return $neg ? -$milli : $milli;
    }

    public static function fromMilli(int $m): string
    {
        $neg = $m < 0;
        $m = abs($m);
        return ($neg ? '-' : '') . intdiv($m, 1000) . '.' . str_pad((string) ($m % 1000), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Record a movement.
     *
     * @param string $type one of TYPES
     * @param string $quantity signed quantity (+ entry / − exit); for 'adjustment' it is the delta
     * @param string|null $unitCost cost per unit (entries); null = current WAC
     * @param array{purchase_id?:int,production_job_id?:int,order_id?:int,user_id?:int,notes?:string} $refs
     * @param bool $allowNegative production may consume more than recorded (the work is physically done)
     * @return array{id:int,balance:string,unit_cost:string,total_cost:string}
     */
    public static function move(int $materialId, string $type, string $quantity, ?string $unitCost = null, array $refs = [], bool $allowNegative = false): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Invalid movement type');
        }
        $qty = self::toMilli($quantity);
        if ($qty === 0) {
            throw new \InvalidArgumentException('Quantity must not be zero');
        }
        if (in_array($type, ['in', 'return'], true) && $qty < 0 || in_array($type, ['out', 'production', 'waste'], true) && $qty > 0) {
            throw new \InvalidArgumentException('Quantity sign does not match the movement type');
        }
        return Database::instance()->transaction(function (Database $db) use ($materialId, $type, $qty, $unitCost, $refs, $allowNegative): array {
            $m = $db->fetch('SELECT id, name, stock_quantity, unit_cost FROM materials WHERE id = ? FOR UPDATE', [$materialId]);
            if ($m === null) {
                throw new \InvalidArgumentException('Unknown material');
            }
            $before = self::toMilli((string) $m['stock_quantity']);
            $after = $before + $qty;
            if ($after < 0 && !$allowNegative && $qty < 0) {
                throw new InsufficientStockException((string) $m['name'], self::fromMilli($before));
            }
            // unit cost in 1/10000 DH (DECIMAL(12,4))
            $wac = self::costToMicro((string) $m['unit_cost']);
            $cost = $unitCost !== null && $unitCost !== '' ? self::costToMicro($unitCost) : $wac;
            if ($qty > 0 && $unitCost !== null && $unitCost !== '') {
                $base = max(0, $before);
                $newWac = $base + $qty > 0 ? intdiv($base * $wac + $qty * $cost + intdiv($base + $qty, 2), $base + $qty) : $cost;
                $db->update('materials', ['unit_cost' => self::microToCost($newWac)], ['id' => $materialId]);
            }
            $totalCents = intdiv(abs($qty) * $cost + 50000, 100000); // milli × 1/10000 DH → cents
            $db->update('materials', ['stock_quantity' => self::fromMilli($after)], ['id' => $materialId]);
            $id = $db->insert('stock_movements', [
                'material_id' => $materialId, 'movement_type' => $type, 'quantity' => self::fromMilli($qty),
                'unit_cost' => self::microToCost($cost), 'total_cost' => Money::fromCents($totalCents), 'balance_after' => self::fromMilli($after),
                'purchase_id' => $refs['purchase_id'] ?? null, 'production_job_id' => $refs['production_job_id'] ?? null,
                'order_id' => $refs['order_id'] ?? null, 'user_id' => $refs['user_id'] ?? null,
                'notes' => isset($refs['notes']) ? mb_substr((string) $refs['notes'], 0, 500) : null,
            ]);
            return ['id' => $id, 'balance' => self::fromMilli($after), 'unit_cost' => self::microToCost($cost), 'total_cost' => Money::fromCents($totalCents)];
        });
    }

    /** Physical count: record the difference as an adjustment. Returns null when nothing changes. @return array<string,mixed>|null */
    public static function count(int $materialId, string $counted, ?int $userId = null, string $notes = ''): ?array
    {
        $current = self::toMilli((string) Database::instance()->value('SELECT stock_quantity FROM materials WHERE id = ?', [$materialId]));
        $delta = self::toMilli($counted) - $current;
        if ($delta === 0) {
            return null;
        }
        return self::move($materialId, 'adjustment', self::fromMilli($delta), null, ['user_id' => $userId, 'notes' => $notes ?: 'Inventaire physique'], true);
    }

    /** @return list<array<string,mixed>> materials at or below their minimum */
    public static function lowStock(int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, sku, name, unit, stock_quantity, min_stock FROM materials WHERE is_active = 1 AND min_stock > 0 AND stock_quantity <= min_stock ORDER BY (stock_quantity / min_stock), name LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Theoretical consumption of one order line: print support (m² × consumption factor) and frame moulding (perimeter in m),
     * mapped to the stock materials linked in Print options. Lines without a linked stock material are skipped (returned as 'unlinked').
     *
     * @param array<string,mixed> $item order_items row
     * @return array{lines:list<array{material_id:int,quantity:string,label:string}>,unlinked:list<string>}
     */
    public static function requirementsFor(array $item): array
    {
        $db = Database::instance();
        $opt = json_decode((string) ($item['options'] ?? ''), true) ?: [];
        $qty = max(1, (int) $item['quantity']);
        $panels = max(1, (int) ($item['panels'] ?? 1));
        $materialId = null;
        $frameId = null;
        $w = 0.0;
        $h = 0.0;
        if (!empty($item['custom_design_id']) && isset($opt['material']['id'])) {
            $materialId = (int) $opt['material']['id'];
            $frameId = isset($opt['frame']['id']) ? (int) $opt['frame']['id'] : null;
            $w = (float) ($opt['panel_width_cm'] ?? 0);
            $h = (float) ($opt['panel_height_cm'] ?? 0);
            $panels = max(1, (int) ($opt['panels'] ?? $panels));
        } elseif (!empty($item['product_variant_id'])) {
            $v = $db->fetch('SELECT product_material_id, frame_id, width_cm, height_cm, panels FROM product_variants WHERE id = ?', [(int) $item['product_variant_id']]);
            if ($v !== null) {
                $materialId = $v['product_material_id'] !== null ? (int) $v['product_material_id'] : null;
                $frameId = $v['frame_id'] !== null ? (int) $v['frame_id'] : null;
                $w = (float) $v['width_cm'];
                $h = (float) $v['height_cm'];
                $panels = max(1, (int) $v['panels']);
            }
        }
        $lines = [];
        $unlinked = [];
        if ($materialId !== null && $w > 0 && $h > 0) {
            $pm = $db->fetch('SELECT pm.name, pm.consumption_factor, pm.inventory_material_id, m.unit FROM product_materials pm LEFT JOIN materials m ON m.id = pm.inventory_material_id WHERE pm.id = ?', [$materialId]);
            if ($pm !== null && $pm['inventory_material_id'] !== null) {
                $area = $w * $h / 10000 * $panels * $qty;
                $amount = match ((string) $pm['unit']) {
                    'm2' => $area * (float) $pm['consumption_factor'],
                    'piece', 'sheet' => $panels * $qty,
                    default => $area * (float) $pm['consumption_factor'],
                };
                $lines[] = ['material_id' => (int) $pm['inventory_material_id'], 'quantity' => number_format($amount, 3, '.', ''), 'label' => (string) $pm['name']];
            } elseif ($pm !== null) {
                $unlinked[] = (string) $pm['name'];
            }
        }
        if ($frameId !== null && $w > 0 && $h > 0) {
            $f = $db->fetch('SELECT f.name, f.inventory_material_id, m.unit FROM frames f LEFT JOIN materials m ON m.id = f.inventory_material_id WHERE f.id = ?', [$frameId]);
            if ($f !== null && $f['inventory_material_id'] !== null) {
                $perimeter = 2 * ($w + $h) / 100 * $panels * $qty;
                $amount = in_array((string) $f['unit'], ['piece', 'box'], true) ? $panels * $qty : $perimeter * 1.1; // 10 % cutting loss (mitres)
                $lines[] = ['material_id' => (int) $f['inventory_material_id'], 'quantity' => number_format($amount, 3, '.', ''), 'label' => (string) $f['name']];
            } elseif ($f !== null) {
                $unlinked[] = (string) $f['name'];
            }
        }
        return ['lines' => $lines, 'unlinked' => $unlinked];
    }

    /**
     * Consume the materials of a production job once (idempotent). Returns the material cost booked.
     */
    public static function consumeForJob(int $jobId, ?int $userId = null): string
    {
        $db = Database::instance();
        if ((int) $db->value("SELECT COUNT(*) FROM stock_movements WHERE production_job_id = ? AND movement_type = 'production'", [$jobId]) > 0) {
            return '0.00';
        }
        $job = $db->fetch('SELECT j.*, o.order_number FROM production_jobs j JOIN orders o ON o.id = j.order_id WHERE j.id = ?', [$jobId]);
        if ($job === null || $job['order_item_id'] === null) {
            return '0.00';
        }
        $item = $db->fetch('SELECT * FROM order_items WHERE id = ?', [(int) $job['order_item_id']]);
        if ($item === null) {
            return '0.00';
        }
        $total = '0.00';
        foreach (self::requirementsFor($item)['lines'] as $l) {
            if (self::toMilli($l['quantity']) <= 0) {
                continue;
            }
            $r = self::move($l['material_id'], 'production', '-' . $l['quantity'], null, [
                'production_job_id' => $jobId, 'order_id' => (int) $job['order_id'], 'user_id' => $userId,
                'notes' => $job['job_number'] . ' · ' . $job['order_number'] . ' · ' . $l['label'],
            ], true);
            $total = Money::add($total, $r['total_cost']);
        }
        if (Money::toCents($total) > 0) {
            $db->query('UPDATE production_jobs SET material_cost = material_cost + ? WHERE id = ?', [$total, $jobId]);
            ActivityLogger::log('inventory.consumed', 'production_job', $jobId, $total);
        }
        return $total;
    }

    /** DECIMAL(12,4) string → integer 1/10000 */
    public static function costToMicro(string $v): int
    {
        $v = str_replace([' ', ','], ['', '.'], trim($v));
        if (!preg_match('/^\d+(\.\d+)?$/', $v)) {
            throw new \InvalidArgumentException('Invalid cost');
        }
        [$i, $d] = array_pad(explode('.', $v, 2), 2, '');
        return (int) $i * 10000 + (int) substr(str_pad($d, 4, '0'), 0, 4);
    }

    public static function microToCost(int $v): string
    {
        return intdiv($v, 10000) . '.' . str_pad((string) ($v % 10000), 4, '0', STR_PAD_LEFT);
    }
}
