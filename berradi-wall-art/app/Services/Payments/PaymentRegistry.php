<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\Crypt;

/**
 * Payment methods offered at checkout.
 * Offline methods (cash on delivery, bank transfer, manual…) are available when active and within their
 * order-amount limits. Online methods are only offered when an implementation is registered AND configured —
 * so enabling a provider without working credentials can never show a fake card payment.
 */
final class PaymentRegistry
{
    /** @var array<string,class-string<PaymentGatewayInterface>> provider code => gateway class */
    private const GATEWAYS = [
        'cmi' => CmiGateway::class,
    ];

    /** Providers that exist as rows but have no integration (reason shown in the back office). */
    public const UNSUPPORTED = [
        'paypal' => 'payment_methods.paypal_unsupported',
    ];

    public static function hasIntegration(?string $provider): bool
    {
        return $provider !== null && isset(self::GATEWAYS[$provider]);
    }

    /** @return class-string<PaymentGatewayInterface>|null */
    public static function gatewayClass(?string $provider): ?string
    {
        return $provider !== null ? (self::GATEWAYS[$provider] ?? null) : null;
    }

    public static function gateway(string $provider): ?PaymentGatewayInterface
    {
        $class = self::GATEWAYS[$provider] ?? null;
        if ($class === null || !class_exists($class)) {
            return null;
        }
        $method = self::methodRow($provider);
        if ($method === null || !(int) $method['is_active']) {
            return null;
        }
        $gw = new $class();
        return $gw->isConfigured() ? $gw : null;
    }

    /** @return array<string,mixed>|null */
    public static function methodRow(string $provider): ?array
    {
        return Database::instance()->fetch('SELECT * FROM payment_methods WHERE provider = ? ORDER BY id LIMIT 1', [$provider]);
    }

    /** @return array<string,string> */
    public static function decryptConfig(?string $stored): array
    {
        if ($stored === null || $stored === '') {
            return [];
        }
        try {
            $data = json_decode(Crypt::decrypt($stored), true);
        } catch (\Throwable) {
            return []; // key changed or data damaged: the provider simply counts as not configured
        }
        return is_array($data) ? array_map('strval', array_filter($data, 'is_scalar')) : [];
    }

    /** @param array<string,string> $config */
    public static function encryptConfig(array $config): ?string
    {
        $config = array_filter($config, fn ($v) => $v !== '');
        return $config === [] ? null : Crypt::encrypt((string) json_encode($config));
    }

    /** True when the order total is inside the method's limits (null limit = no limit). @param array<string,mixed> $m */
    public static function withinLimits(array $m, ?string $total): bool
    {
        if ($total === null) {
            return true;
        }
        $t = Money::toCents($total);
        if (($m['min_order_amount'] ?? null) !== null && $m['min_order_amount'] !== '' && $t < Money::toCents((string) $m['min_order_amount'])) {
            return false;
        }
        if (($m['max_order_amount'] ?? null) !== null && $m['max_order_amount'] !== '' && $t > Money::toCents((string) $m['max_order_amount'])) {
            return false;
        }
        return true;
    }

    /**
     * @param string|null $total order total; methods outside their min/max are left out
     * @return list<array<string,mixed>>
     */
    public static function available(?string $total = null): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT id, code, name, description, instructions, is_online, provider, min_order_amount, max_order_amount
             FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id'
        );
        $rows = \App\Services\TranslationService::apply('payment_method', $rows, ['name', 'description', 'instructions']);
        return array_values(array_filter($rows, fn ($m) => self::withinLimits($m, $total)
            && ((int) $m['is_online'] === 0 || ($m['provider'] !== null && self::gateway((string) $m['provider']) !== null))));
    }

    /** @return array<string,mixed>|null */
    public static function find(string $code, ?string $total = null): ?array
    {
        foreach (self::available($total) as $m) {
            if ($m['code'] === $code) {
                return $m;
            }
        }
        return null;
    }

    /** Translated row of any method (confirmation pages, e-mails). @return array<string,mixed>|null */
    public static function describe(int $id): ?array
    {
        $m = Database::instance()->fetch('SELECT id, code, name, description, instructions, is_online, provider FROM payment_methods WHERE id = ?', [$id]);
        return $m !== null ? \App\Services\TranslationService::applyOne('payment_method', $m, ['name', 'description', 'instructions']) : null;
    }
}
