<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Database;

/**
 * Payment methods offered at checkout.
 * Offline methods (cash on delivery, bank transfer, manual) are always available when active.
 * Online methods are only offered when an implementation is registered AND configured — so enabling
 * "CMI" in the database without a working integration can never show a fake card payment.
 */
final class PaymentRegistry
{
    /** @var array<string,class-string<PaymentGatewayInterface>> provider code => gateway class */
    private const GATEWAYS = [
        // 'cmi' => \App\Services\Payments\CmiGateway::class,   (future activation)
    ];

    public static function gateway(string $provider): ?PaymentGatewayInterface
    {
        $class = self::GATEWAYS[$provider] ?? null;
        if ($class === null || !class_exists($class)) {
            return null;
        }
        $gw = new $class();
        return $gw->isConfigured() ? $gw : null;
    }

    /** @return list<array<string,mixed>> */
    public static function available(): array
    {
        $rows = Database::instance()->fetchAll('SELECT id, code, name, description, instructions, is_online, provider FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id');
        $rows = \App\Services\TranslationService::apply('payment_method', $rows, ['name', 'description', 'instructions']);
        return array_values(array_filter($rows, fn ($m) => (int) $m['is_online'] === 0 || ($m['provider'] !== null && self::gateway((string) $m['provider']) !== null)));
    }

    /** @return array<string,mixed>|null */
    public static function find(string $code): ?array
    {
        foreach (self::available() as $m) {
            if ($m['code'] === $code) {
                return $m;
            }
        }
        return null;
    }
}
