<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Local test provider (APP_ENV != production only). Signs callbacks with APP_KEY
 * via HMAC so the same trusted-event pipeline as a real provider is exercised.
 */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public static function sign(array $p): string
    {
        ksort($p);
        unset($p['signature']);

        return hash_hmac('sha256', http_build_query($p), (string) config('app.key'));
    }

    public function redirectForm(Order $order, string $amount): array
    {
        return ['action' => route('payment.sandbox', $order->number), 'fields' => ['amount' => $amount]];
    }

    public function verifyCallback(Request $request): ?array
    {
        $p = $request->only(['event_id', 'oid', 'amount', 'status']);
        if (! hash_equals(self::sign($p), (string) $request->input('signature'))) {
            return null;
        }

        return ['event_id' => $p['event_id'], 'order_number' => $p['oid'], 'amount' => $p['amount'], 'success' => $p['status'] === 'paid', 'reference' => $p['event_id']];
    }

    public function callbackResponse(bool $ok): string
    {
        return $ok ? 'OK' : 'KO';
    }
}
