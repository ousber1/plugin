<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Core\Request;
use App\Helpers\Money;
use App\Services\Auth;
use App\Services\Builder\DesignService;
use App\Services\Builder\PricingService;
use App\Services\CatalogService;

/**
 * Server-side shopping cart.
 *  - guests: random token in an HttpOnly cookie, only its SHA-256 is stored (carts.token)
 *  - customers: the active cart is linked to the account and merged with the guest cart on login
 *  - every view re-prices every line from the catalogue / builder pricing: stored prices are snapshots only
 */
final class CartService
{
    public const COOKIE = 'bwa_cart';
    public const MAX_QTY = 50;
    public const MAX_LINES = 30;

    private static ?string $raw = null;

    private static function rawToken(bool $create): ?string
    {
        if (self::$raw !== null) {
            return self::$raw;
        }
        $c = Request::current()?->cookie(self::COOKIE);
        if (is_string($c) && preg_match('/^[a-f0-9]{64}$/', $c)) {
            return self::$raw = $c;
        }
        if (!$create) {
            return null;
        }
        self::$raw = bin2hex(random_bytes(32));
        $req = Request::current();
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::COOKIE, self::$raw, [
                'expires' => time() + 60 * 86400, 'path' => (($req?->basePath() ?: '') . '/'),
                'secure' => (bool) $req?->isSecure(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        return self::$raw;
    }

    /** Tests only. */
    public static function setToken(?string $raw): void
    {
        self::$raw = $raw;
    }

    /** @return array<string,mixed>|null */
    public static function current(bool $create = false): ?array
    {
        $db = Database::instance();
        $customer = Auth::customer();
        if ($customer !== null) {
            $cart = $db->fetch("SELECT * FROM carts WHERE customer_id = ? AND status IN ('active','abandoned') ORDER BY id DESC LIMIT 1", [(int) $customer['id']]);
            if ($cart !== null || !$create) {
                return $cart !== null ? self::revive($cart) : null;
            }
        }
        $raw = self::rawToken($create);
        if ($raw === null) {
            return null;
        }
        $hash = hash('sha256', $raw);
        $cart = $db->fetch("SELECT * FROM carts WHERE token = ? AND status IN ('active','abandoned')", [$hash]);
        if ($cart !== null) {
            $cart = self::revive($cart);
        }
        if ($cart !== null && $cart['customer_id'] !== null && ($customer === null || (int) $cart['customer_id'] !== (int) $customer['id'])) {
            $cart = null; // belongs to an account that is not logged in here
            if ($create) {
                self::$raw = null;
                $raw = self::rawToken(true);
                $hash = hash('sha256', (string) $raw);
            }
        }
        if ($cart === null && $create) {
            $db->query("UPDATE carts SET token = CONCAT('x', SUBSTRING(SHA2(RAND(), 256), 2)) WHERE token = ? AND status <> 'active'", [$hash]);
            $id = $db->insert('carts', ['token' => $hash, 'customer_id' => $customer['id'] ?? null, 'status' => 'active']);
            $cart = $db->fetch('SELECT * FROM carts WHERE id = ?', [$id]);
        }
        return $cart;
    }

    /** A visitor coming back to an "abandoned" cart simply continues it. @param array<string,mixed> $cart @return array<string,mixed> */
    private static function revive(array $cart): array
    {
        if ($cart['status'] === 'abandoned') {
            Database::instance()->query("UPDATE carts SET status = 'active', last_activity_at = NOW() WHERE id = ?", [(int) $cart['id']]);
            $cart['status'] = 'active';
        }
        return $cart;
    }

    /** After login: attach / merge the guest cart into the customer's cart. */
    public static function mergeOnLogin(int $customerId): void
    {
        $raw = self::rawToken(false);
        if ($raw === null) {
            return;
        }
        $db = Database::instance();
        $guest = $db->fetch("SELECT * FROM carts WHERE token = ? AND status = 'active' AND customer_id IS NULL", [hash('sha256', $raw)]);
        if ($guest === null) {
            return;
        }
        $own = $db->fetch("SELECT * FROM carts WHERE customer_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [$customerId]);
        if ($own === null) {
            $db->update('carts', ['customer_id' => $customerId], ['id' => (int) $guest['id']]);
            return;
        }
        $db->transaction(function (Database $db) use ($guest, $own): void {
            foreach ($db->fetchAll('SELECT * FROM cart_items WHERE cart_id = ?', [(int) $guest['id']]) as $it) {
                $dup = $db->fetch(
                    'SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id <=> ? AND product_variant_id <=> ? AND custom_design_id <=> ?',
                    [(int) $own['id'], $it['product_id'], $it['product_variant_id'], $it['custom_design_id']]
                );
                if ($dup !== null) {
                    $db->update('cart_items', ['quantity' => min(self::MAX_QTY, (int) $dup['quantity'] + (int) $it['quantity'])], ['id' => (int) $dup['id']]);
                    $db->delete('cart_items', ['id' => (int) $it['id']]);
                } else {
                    $db->update('cart_items', ['cart_id' => (int) $own['id']], ['id' => (int) $it['id']]);
                }
            }
            if ($own['coupon_id'] === null && $guest['coupon_id'] !== null) {
                $db->update('carts', ['coupon_id' => $guest['coupon_id']], ['id' => (int) $own['id']]);
            }
            $db->update('carts', ['status' => 'converted'], ['id' => (int) $guest['id']]);
        });
    }

    private static function touch(int $cartId): void
    {
        Database::instance()->query('UPDATE carts SET last_activity_at = NOW() WHERE id = ?', [$cartId]);
    }

    /** @return array{ok:bool,error?:string} */
    public static function addProduct(int $productId, ?int $variantId, int $qty): array
    {
        $item = CatalogService::purchasable($productId, $variantId);
        if ($item === null) {
            return ['ok' => false, 'error' => __('cart.unavailable')];
        }
        if ($item['is_customizable']) {
            return ['ok' => false, 'error' => __('cart.needs_photo')];
        }
        return self::addLine(['product_id' => $productId, 'product_variant_id' => $item['variant_id'], 'custom_design_id' => null], $qty, $item['unit_price'], $item);
    }

    /** @return array{ok:bool,error?:string} */
    public static function addDesign(string $uuid, int $qty): array
    {
        $design = DesignService::findAccessible($uuid);
        $customer = Auth::customer();
        $owns = $design !== null && (($customer !== null && (int) $design['customer_id'] === (int) $customer['id'])
            || ($design['customer_id'] === null && hash_equals((string) $design['owner_token'], \App\Services\OwnerToken::hash())));
        if (!$owns || !in_array($design['status'], ['draft', 'in_cart'], true) || $design['quality_status'] === 'low') {
            return ['ok' => false, 'error' => __('cart.design_unavailable')];
        }
        $quote = self::quoteDesign($design);
        if ($quote === null) {
            return ['ok' => false, 'error' => __('cart.design_unavailable')];
        }
        $res = self::addLine(['product_id' => $design['product_id'] !== null ? (int) $design['product_id'] : null, 'product_variant_id' => null, 'custom_design_id' => (int) $design['id']], $qty, $quote['unit_price'], null);
        if ($res['ok']) {
            Database::instance()->update('custom_designs', ['status' => 'in_cart'], ['id' => (int) $design['id']]);
        }
        return $res;
    }

    /**
     * @param array{product_id:?int,product_variant_id:?int,custom_design_id:?int} $key
     * @param array<string,mixed>|null $stockInfo
     * @return array{ok:bool,error?:string}
     */
    private static function addLine(array $key, int $qty, string $unitPrice, ?array $stockInfo): array
    {
        if ($qty < 1 || $qty > self::MAX_QTY) {
            return ['ok' => false, 'error' => __('cart.bad_quantity', ['max' => self::MAX_QTY])];
        }
        $cart = self::current(true);
        $db = Database::instance();
        $dup = $db->fetch(
            'SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id <=> ? AND product_variant_id <=> ? AND custom_design_id <=> ?',
            [(int) $cart['id'], $key['product_id'], $key['product_variant_id'], $key['custom_design_id']]
        );
        $newQty = min(self::MAX_QTY, $qty + (int) ($dup['quantity'] ?? 0));
        if ($stockInfo !== null && $stockInfo['track_stock'] && $newQty > (int) $stockInfo['stock']) {
            return ['ok' => false, 'error' => __('cart.stock_left', ['count' => max(0, (int) $stockInfo['stock'])])];
        }
        if ($dup !== null) {
            $db->update('cart_items', ['quantity' => $newQty, 'unit_price' => $unitPrice], ['id' => (int) $dup['id']]);
        } else {
            if ((int) $db->value('SELECT COUNT(*) FROM cart_items WHERE cart_id = ?', [(int) $cart['id']]) >= self::MAX_LINES) {
                return ['ok' => false, 'error' => __('cart.too_many_lines')];
            }
            $db->insert('cart_items', $key + ['cart_id' => (int) $cart['id'], 'quantity' => $newQty, 'unit_price' => $unitPrice]);
        }
        self::touch((int) $cart['id']);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function updateQuantity(int $itemId, int $qty): array
    {
        $cart = self::current(false);
        if ($cart === null) {
            return ['ok' => false, 'error' => __('cart.unavailable')];
        }
        if ($qty <= 0) {
            return self::remove($itemId);
        }
        $item = Database::instance()->fetch('SELECT * FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, (int) $cart['id']]);
        if ($item === null) {
            return ['ok' => false, 'error' => __('cart.unavailable')];
        }
        $qty = min(self::MAX_QTY, $qty);
        if ($item['product_id'] !== null && $item['custom_design_id'] === null) {
            $info = CatalogService::purchasable((int) $item['product_id'], $item['product_variant_id'] !== null ? (int) $item['product_variant_id'] : null);
            if ($info !== null && $info['track_stock'] && $qty > $info['stock']) {
                return ['ok' => false, 'error' => __('cart.stock_left', ['count' => max(0, (int) $info['stock'])])];
            }
        }
        Database::instance()->update('cart_items', ['quantity' => $qty], ['id' => $itemId]);
        self::touch((int) $cart['id']);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function remove(int $itemId): array
    {
        $cart = self::current(false);
        if ($cart === null) {
            return ['ok' => false, 'error' => __('cart.unavailable')];
        }
        $item = Database::instance()->fetch('SELECT * FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, (int) $cart['id']]);
        if ($item === null) {
            return ['ok' => false, 'error' => __('cart.unavailable')];
        }
        Database::instance()->delete('cart_items', ['id' => $itemId]);
        if ($item['custom_design_id'] !== null) {
            Database::instance()->query("UPDATE custom_designs SET status = 'draft' WHERE id = ? AND status = 'in_cart'", [(int) $item['custom_design_id']]);
        }
        self::touch((int) $cart['id']);
        return ['ok' => true];
    }

    public static function count(): int
    {
        $cart = self::current(false);
        return $cart === null ? 0 : (int) Database::instance()->value('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?', [(int) $cart['id']]);
    }

    /** Price a saved design with the CURRENT pricing configuration. @return array<string,mixed>|null */
    public static function quoteDesign(array $design): ?array
    {
        $c = is_array($design['config']) ? $design['config'] : (json_decode((string) $design['config'], true) ?: []);
        $q = PricingService::quote([
            'material_id' => (int) ($c['material']['id'] ?? 0),
            'frame_id' => $c['frame']['id'] ?? null,
            'layout' => (string) ($c['layout'] ?? 'single'),
            'panel_width_cm' => $c['panel_width_cm'] ?? null,
            'panel_height_cm' => $c['panel_height_cm'] ?? null,
            'border' => $c['border']['code'] ?? null,
            'finish' => $c['finish']['code'] ?? null,
            'quantity' => 1,
            'product_id' => $c['product']['id'] ?? null,
        ]);
        return $q['ok'] ? $q : null;
    }

    /**
     * Detailed, re-priced lines.
     *
     * @return list<array<string,mixed>>
     */
    public static function lines(array $cart): array
    {
        $db = Database::instance();
        $out = [];
        foreach ($db->fetchAll('SELECT * FROM cart_items WHERE cart_id = ? ORDER BY id', [(int) $cart['id']]) as $it) {
            $qty = (int) $it['quantity'];
            $line = ['id' => (int) $it['id'], 'quantity' => $qty, 'available' => true, 'error' => null, 'design_uuid' => null, 'preview_url' => null];
            if ($it['custom_design_id'] !== null) {
                $d = $db->fetch('SELECT * FROM custom_designs WHERE id = ?', [(int) $it['custom_design_id']]);
                $q = $d !== null && $d['status'] !== 'abandoned' ? self::quoteDesign($d) : null;
                $c = $d !== null ? (json_decode((string) $d['config'], true) ?: []) : [];
                $line += [
                    'kind' => 'design', 'product_id' => $it['product_id'] !== null ? (int) $it['product_id'] : null, 'variant_id' => null,
                    'design_id' => (int) $it['custom_design_id'], 'design_uuid' => $d['uuid'] ?? null, 'category_id' => null,
                    'title' => ($c['product']['title'] ?? null) ?: __('cart.custom_design'),
                    'details' => $d ? cm($c['overall_width_cm'] ?? 0) . ' × ' . cm($c['overall_height_cm'] ?? 0) . ' cm · ' . ($c['material']['name'] ?? '') . (!empty($c['frame']) ? ' · ' . $c['frame']['name'] : '') . ((int) ($c['panels'] ?? 1) > 1 ? ' · ' . (int) $c['panels'] . ' ' . __('cart.panels') : '') : '',
                    'url' => $d ? route('builder.design.show', ['uuid' => $d['uuid']]) : null,
                    'preview_url' => $d && $d['preview_path'] ? route('builder.design.preview', ['uuid' => $d['uuid']]) : null,
                    'unit_price' => $q['unit_price'] ?? (string) $it['unit_price'],
                    'unit_cost' => $q['unit_cost'] ?? '0.00',
                    'snapshot' => $q && $d ? ['config' => $c, 'quote' => $q, 'design' => $d] : null,
                ];
                if ($q === null) {
                    $line['available'] = false;
                    $line['error'] = __('cart.design_unavailable');
                }
            } else {
                $info = CatalogService::purchasable((int) $it['product_id'], $it['product_variant_id'] !== null ? (int) $it['product_variant_id'] : null);
                $line += [
                    'kind' => 'product', 'product_id' => (int) $it['product_id'], 'variant_id' => $it['product_variant_id'] !== null ? (int) $it['product_variant_id'] : null,
                    'design_id' => null, 'category_id' => $info['category_id'] ?? null,
                    'title' => $info['title'] ?? __('cart.unavailable_product'),
                    'details' => $info ? (string) ($info['variant_name'] ?? '') : '',
                    'url' => $info ? route('product.show', ['slug' => $info['slug']]) : null,
                    'preview_url' => $info && $info['image'] ? upload_url($info['image']) : null,
                    'unit_price' => $info['unit_price'] ?? (string) $it['unit_price'],
                    'regular_price' => $info['regular_price'] ?? null,
                    'unit_cost' => $info['unit_cost'] ?? '0.00',
                    'snapshot' => $info,
                ];
                if ($info === null) {
                    $line['available'] = false;
                    $line['error'] = __('cart.unavailable');
                } elseif ($info['track_stock'] && $qty > $info['stock']) {
                    $line['available'] = false;
                    $line['error'] = __('cart.stock_left', ['count' => max(0, (int) $info['stock'])]);
                }
            }
            $line['line_total'] = Money::mul($line['unit_price'], $qty);
            if ($line['available'] && Money::compare($line['unit_price'], (string) $it['unit_price']) !== 0) {
                $db->update('cart_items', ['unit_price' => $line['unit_price']], ['id' => (int) $it['id']]); // keep snapshot current
            }
            $out[] = $line;
        }
        return $out;
    }

    /** @return array{ok:bool,error?:string} */
    public static function applyCoupon(string $code): array
    {
        $cart = self::current(false);
        if ($cart === null) {
            return ['ok' => false, 'error' => __('cart.empty')];
        }
        $lines = array_values(array_filter(self::lines($cart), fn ($l) => $l['available']));
        $subtotal = Money::add('0', ...array_map(fn ($l) => $l['line_total'], $lines));
        $customer = Auth::customer();
        $r = CouponService::evaluate($code, $lines, $subtotal, $customer['id'] ?? null, $customer['email'] ?? $cart['email']);
        if (!$r['ok']) {
            return $r;
        }
        Database::instance()->update('carts', ['coupon_id' => (int) $r['coupon']['id']], ['id' => (int) $cart['id']]);
        return ['ok' => true];
    }

    public static function removeCoupon(): void
    {
        $cart = self::current(false);
        if ($cart !== null) {
            Database::instance()->query('UPDATE carts SET coupon_id = NULL WHERE id = ?', [(int) $cart['id']]);
        }
    }
}
