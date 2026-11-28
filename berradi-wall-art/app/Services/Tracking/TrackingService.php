<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Helpers\Money;
use App\Services\Csp;

/**
 * Analytics & advertising tags (GA4, Google Tag Manager, Google Ads, Meta Pixel + Conversions API, TikTok,
 * Snapchat, Pinterest, Microsoft Clarity) with consent.
 *
 *  - Nothing is loaded for a provider whose ID is empty.
 *  - With the consent banner on (default), analytics tags load only after "analytics" consent and advertising
 *    pixels only after "marketing" consent (Moroccan law 09-08 / CNDP, GDPR for European visitors).
 *  - Tags are loaded by /assets/js/tracking.js (no inline script, so the Content-Security-Policy stays strict);
 *    only the hosts of the enabled providers are added to the policy.
 *  - E-commerce events (view_item, add_to_cart, begin_checkout, purchase, search, generate_lead, sign_up) are
 *    described once here and translated by tracking.js for every provider, with one event id shared with the
 *    server-side Meta Conversions API for deduplication.
 */
final class TrackingService
{
    public const CONSENT_COOKIE = 'bwa_consent';

    /** provider => [setting, id pattern, consent category] */
    public const PROVIDERS = [
        'ga4' => ['track_ga4_id', '/^G-[A-Z0-9]{4,14}$/', 'analytics'],
        'gtm' => ['track_gtm_id', '/^GTM-[A-Z0-9]{4,10}$/', 'analytics'],
        'clarity' => ['track_clarity_id', '/^[a-z0-9]{6,14}$/', 'analytics'],
        'ads' => ['track_ads_id', '/^AW-[0-9]{6,14}$/', 'marketing'],
        'meta' => ['track_meta_pixel_id', '/^[0-9]{10,20}$/', 'marketing'],
        'tiktok' => ['track_tiktok_id', '/^[A-Z0-9]{10,30}$/', 'marketing'],
        'snapchat' => ['track_snapchat_id', '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', 'marketing'],
        'pinterest' => ['track_pinterest_id', '/^[0-9]{10,16}$/', 'marketing'],
    ];

    /** Hosts each provider needs, per CSP directive. */
    private const HOSTS = [
        'google' => [
            'script-src' => ['https://www.googletagmanager.com', 'https://*.googletagmanager.com', 'https://www.googleadservices.com', 'https://googleads.g.doubleclick.net'],
            'connect-src' => ['https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.googletagmanager.com', 'https://*.g.doubleclick.net', 'https://www.google.com', 'https://www.googleadservices.com', 'https://pagead2.googlesyndication.com'],
            'img-src' => ['https://*.google-analytics.com', 'https://*.googletagmanager.com', 'https://*.g.doubleclick.net', 'https://www.google.com', 'https://www.google.co.ma', 'https://googleads.g.doubleclick.net'],
            'frame-src' => ['https://td.doubleclick.net', 'https://www.googletagmanager.com'],
        ],
        'meta' => ['script-src' => ['https://connect.facebook.net'], 'connect-src' => ['https://www.facebook.com', 'https://connect.facebook.net'], 'img-src' => ['https://www.facebook.com']],
        'tiktok' => ['script-src' => ['https://analytics.tiktok.com'], 'connect-src' => ['https://analytics.tiktok.com', 'https://*.tiktok.com'], 'img-src' => ['https://analytics.tiktok.com']],
        'snapchat' => ['script-src' => ['https://sc-static.net'], 'connect-src' => ['https://tr.snapchat.com', 'https://*.snapchat.com'], 'img-src' => ['https://tr.snapchat.com'], 'frame-src' => ['https://tr.snapchat.com']],
        'pinterest' => ['script-src' => ['https://s.pinimg.com'], 'connect-src' => ['https://ct.pinterest.com'], 'img-src' => ['https://ct.pinterest.com']],
        'clarity' => ['script-src' => ['https://www.clarity.ms', 'https://*.clarity.ms'], 'connect-src' => ['https://*.clarity.ms', 'https://c.bing.com'], 'img-src' => ['https://*.clarity.ms', 'https://c.bing.com']],
    ];

    /** @var list<array<string,mixed>> events of the page being rendered */
    private static array $events = [];

    /** @return array<string,string> provider => id, only valid ones */
    public static function enabled(): array
    {
        $out = [];
        foreach (self::PROVIDERS as $p => [$key, $pattern]) {
            $id = trim((string) setting($key, ''));
            if ($id !== '' && preg_match($pattern, $id)) {
                $out[$p] = $id;
            }
        }
        return $out;
    }

    public static function consentRequired(): bool
    {
        return (bool) setting('track_consent_enabled', true);
    }

    /** Visitor's choice from the consent cookie ("v1.a1.m0"); without a banner everything is allowed. @return array{analytics:bool,marketing:bool,given:bool} */
    public static function consent(?Request $request = null): array
    {
        if (!self::consentRequired()) {
            return ['analytics' => true, 'marketing' => true, 'given' => true];
        }
        $raw = (string) (($request ?? Request::current())?->cookie(self::CONSENT_COOKIE) ?? '');
        if (!preg_match('/^v1\.a([01])\.m([01])$/', $raw, $m)) {
            return ['analytics' => false, 'marketing' => false, 'given' => false];
        }
        return ['analytics' => $m[1] === '1', 'marketing' => $m[2] === '1', 'given' => true];
    }

    /** Extra hosts typed by the owner (for tags added inside Google Tag Manager). @return list<string> */
    public static function extraHosts(): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', (string) setting('track_extra_hosts', '')) ?: [] as $h) {
            $h = rtrim(trim($h), '/');
            if (preg_match('#^https://(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+$#', $h)) {
                $out[] = $h;
            }
        }
        return array_values(array_unique(array_slice($out, 0, 30)));
    }

    /** Allow, for this response only, the hosts of the enabled providers. */
    public static function registerCsp(): void
    {
        $enabled = self::enabled();
        $groups = [];
        foreach (array_keys($enabled) as $p) {
            $groups[in_array($p, ['ga4', 'gtm', 'ads'], true) ? 'google' : $p] = true;
        }
        foreach (array_keys($groups) as $g) {
            foreach (self::HOSTS[$g] as $directive => $hosts) {
                Csp::allow($directive, ...$hosts);
            }
        }
        $extra = self::extraHosts();
        if ($extra !== [] && $enabled !== []) {
            foreach (['script-src', 'connect-src', 'img-src', 'frame-src'] as $d) {
                Csp::allow($d, ...$extra);
            }
        }
    }

    // ------------------------------------------------------------------ events

    /** Event for the page being rendered. @param array<string,mixed> $data */
    public static function event(string $name, array $data = [], ?string $id = null): void
    {
        if (self::enabled() === []) {
            return;
        }
        self::$events[] = ['name' => $name, 'id' => $id ?? self::newId()] + $data;
    }

    /** Event to fire on the next page (after a POST/redirect, e.g. add to cart). @param array<string,mixed> $data */
    public static function queue(string $name, array $data = [], ?string $id = null): void
    {
        if (self::enabled() === []) {
            return;
        }
        $q = (array) Session::get('tracking_queue', []);
        $q[] = ['name' => $name, 'id' => $id ?? self::newId()] + $data;
        Session::put('tracking_queue', array_slice($q, -10));
    }

    /** @return list<array<string,mixed>> */
    public static function pull(): array
    {
        $events = array_merge((array) Session::get('tracking_queue', []), self::$events);
        Session::forget('tracking_queue');
        self::$events = [];
        return array_values($events);
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(12));
    }

    /**
     * Item in GA4 shape (tracking.js converts it for the other providers).
     *
     * @return array{id:string,name:string,category:string,variant:string,price:float,quantity:int}
     */
    public static function item(string $id, string $name, string $price, int $qty = 1, string $category = '', string $variant = ''): array
    {
        return ['id' => $id, 'name' => mb_substr($name, 0, 100), 'category' => mb_substr($category, 0, 100), 'variant' => mb_substr($variant, 0, 100),
            'price' => Money::toCents($price) / 100, 'quantity' => max(1, $qty)];
    }

    /** Item id used everywhere (feeds, pixels): the product id, or "custom" for photo-builder designs. */
    public static function itemId(?int $productId, bool $custom = false): string
    {
        return $custom ? 'custom' . ($productId ? '-' . $productId : '') : (string) $productId;
    }

    /** @param list<array<string,mixed>> $lines cart lines (CartService::lines) @return list<array<string,mixed>> */
    public static function cartItems(array $lines): array
    {
        return array_values(array_map(fn ($l) => self::item(
            self::itemId($l['product_id'] ?? null, ($l['kind'] ?? '') === 'design'),
            (string) $l['title'],
            (string) $l['unit_price'],
            (int) $l['quantity'],
            '',
            (string) ($l['details'] ?? '')
        ), array_filter($lines, fn ($l) => !empty($l['available']))));
    }

    /** @param list<array<string,mixed>> $items order_items rows @return list<array<string,mixed>> */
    public static function orderItems(array $items): array
    {
        return array_map(fn ($i) => self::item(
            self::itemId($i['product_id'] !== null ? (int) $i['product_id'] : null, !empty($i['custom_design_id'])),
            (string) $i['title'],
            (string) $i['unit_price'],
            (int) $i['quantity'],
            '',
            trim((string) ($i['material_name'] ?? '') . ' ' . (string) ($i['frame_name'] ?? ''))
        ), $items);
    }

    /** JSON for tracking.js (null when no provider is set: nothing is loaded). @return array<string,mixed>|null */
    public static function pageConfig(): ?array
    {
        $enabled = self::enabled();
        if ($enabled === []) {
            self::pull();
            return null;
        }
        self::registerCsp();
        $categories = [];
        foreach (array_keys($enabled) as $p) {
            $categories[$p] = self::PROVIDERS[$p][2];
        }
        return [
            'ids' => $enabled,
            'categories' => $categories,
            'consentRequired' => self::consentRequired(),
            'cookie' => self::CONSENT_COOKIE,
            'adsPurchaseLabel' => preg_match('/^[A-Za-z0-9_-]{4,40}$/', (string) setting('track_ads_purchase_label', '')) ? (string) setting('track_ads_purchase_label') : '',
            'currency' => (string) setting('currency_code', 'MAD'),
            'events' => self::pull(),
        ];
    }

    // ------------------------------------------------------------------ Meta Conversions API (server side)

    public static function capiToken(): string
    {
        return trim((string) setting('track_meta_capi_token', ''));
    }

    /**
     * Queue a server event (sent by the cron) — only with marketing consent and a configured pixel + token.
     *
     * @param array<string,mixed> $order orders row
     * @param list<array<string,mixed>> $items
     */
    public static function queuePurchase(array $order, array $items, ?Request $request = null): bool
    {
        $request ??= Request::current();
        $pixel = self::enabled()['meta'] ?? null;
        if ($pixel === null || self::capiToken() === '' || !self::consent($request)['marketing']) {
            return false;
        }
        $norm = fn (?string $v) => $v !== null && trim($v) !== '' ? hash('sha256', mb_strtolower(trim($v))) : null;
        $phone = preg_replace('/\D+/', '', (string) ($order['customer_phone'] ?? ''));
        if ($phone !== '' && str_starts_with($phone, '0')) {
            $phone = '212' . substr($phone, 1); // Moroccan numbers in international format, as Meta expects
        }
        $userData = array_filter([
            'em' => ($e = $norm($order['customer_email'] ?? null)) !== null ? [$e] : null,
            'ph' => $phone !== '' ? [hash('sha256', $phone)] : null,
            'ct' => ($c = $norm(str_replace(' ', '', (string) ($order['shipping_city'] ?? '')))) !== null ? [$c] : null,
            'country' => [hash('sha256', 'ma')],
            'external_id' => !empty($order['customer_id']) ? [hash('sha256', 'customer-' . $order['customer_id'])] : null,
            'client_ip_address' => $request?->ip(),
            'client_user_agent' => $request?->userAgent(),
            'fbp' => self::cookieValue($request, '_fbp'),
            'fbc' => self::cookieValue($request, '_fbc'),
        ]);
        $event = [
            'event_name' => 'Purchase',
            'event_time' => time(),
            'event_id' => 'order-' . $order['order_number'],
            'action_source' => 'website',
            'event_source_url' => absolute_url('order/confirmation/' . $order['order_number']),
            'user_data' => $userData,
            'custom_data' => [
                'currency' => (string) setting('currency_code', 'MAD'),
                'value' => Money::toCents((string) $order['grand_total']) / 100,
                'order_id' => (string) $order['order_number'],
                'content_type' => 'product',
                'contents' => array_map(fn ($i) => ['id' => (string) $i['id'], 'quantity' => (int) $i['quantity'], 'item_price' => $i['price']], $items),
                'num_items' => array_sum(array_map(fn ($i) => (int) $i['quantity'], $items)),
            ],
        ];
        try {
            Database::instance()->query(
                'INSERT IGNORE INTO tracking_events (provider, event_name, event_id, order_id, payload) VALUES (?, ?, ?, ?, ?)',
                ['meta', 'Purchase', $event['event_id'], (int) $order['id'], json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
            );
        } catch (\Throwable $e) {
            Logger::warning('Conversions API queue failed: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    private static function cookieValue(?Request $request, string $name): ?string
    {
        $v = (string) ($request?->cookie($name) ?? '');
        return preg_match('/^fb\.\d\.\d{10,13}\.[A-Za-z0-9_.-]{4,200}$/', $v) ? $v : null;
    }

    /**
     * Cron: send queued server events (5 attempts). The HTTP sender can be replaced in tests.
     *
     * @param (callable(string,array<string,mixed>):array{status:int,body:string})|null $send
     */
    public static function sendQueued(int $limit = 50, ?callable $send = null): int
    {
        $pixel = self::enabled()['meta'] ?? null;
        $token = self::capiToken();
        if ($pixel === null || $token === '') {
            return 0;
        }
        $send ??= [self::class, 'httpPost'];
        $db = Database::instance();
        $sent = 0;
        foreach ($db->fetchAll("SELECT * FROM tracking_events WHERE provider = 'meta' AND status = 'queued' AND attempts < 5 ORDER BY id LIMIT " . max(1, $limit)) as $row) {
            $body = ['data' => [json_decode((string) $row['payload'], true)]];
            $test = trim((string) setting('track_meta_test_code', ''));
            if ($test !== '' && preg_match('/^[A-Za-z0-9]{4,20}$/', $test)) {
                $body['test_event_code'] = $test;
            }
            $url = 'https://graph.facebook.com/v19.0/' . rawurlencode($pixel) . '/events?access_token=' . rawurlencode($token);
            try {
                $res = $send($url, $body);
                $ok = $res['status'] >= 200 && $res['status'] < 300;
                $error = $ok ? null : mb_substr('HTTP ' . $res['status'] . ' ' . $res['body'], 0, 500);
            } catch (\Throwable $e) {
                $ok = false;
                $error = mb_substr($e->getMessage(), 0, 500);
            }
            $attempts = (int) $row['attempts'] + 1;
            $db->update('tracking_events', [
                'status' => $ok ? 'sent' : ($attempts >= 5 ? 'failed' : 'queued'),
                'attempts' => $attempts,
                'last_error' => $error !== null ? str_replace($token, '***', $error) : null,
                'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
            ], ['id' => (int) $row['id']]);
            $sent += $ok ? 1 : 0;
        }
        return $sent;
    }

    /** @param array<string,mixed> $body @return array{status:int,body:string} */
    public static function httpPost(string $url, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $out = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) {
            throw new \RuntimeException('Network error: ' . $err);
        }
        return ['status' => $status, 'body' => mb_substr((string) $out, 0, 400)];
    }

    /** Tests only. */
    public static function reset(): void
    {
        self::$events = [];
    }
}
