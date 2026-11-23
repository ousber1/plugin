<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Adapter for CMI (Centre Monétique Interbancaire) "3D Pay Hosting".
 *
 * Implemented from the commonly published CMI merchant integration
 * (hash "ver3": parameters sorted case-insensitively, values escaped for "\" and "|",
 * joined with "|", store key appended, SHA-512, base64). The merchant MUST validate
 * this against the current CMI documentation and in the CMI test environment
 * before enabling it in production. Credentials come from .env (CMI_*).
 */
class CmiGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'cmi';
    }

    protected function cfg(string $k): ?string
    {
        return config('services.cmi.'.$k);
    }

    public function redirectForm(Order $order, string $amount): array
    {
        $params = [
            'clientid' => $this->cfg('client_id'),
            'amount' => number_format((float) $amount, 2, '.', ''),
            'okUrl' => route('payment.return', ['status' => 'ok', 'order' => $order->number]),
            'failUrl' => route('payment.return', ['status' => 'fail', 'order' => $order->number]),
            'TranType' => 'PreAuth',
            'callbackUrl' => route('payment.callback', 'cmi'),
            'shopurl' => url('/'),
            'currency' => '504', // MAD
            'rnd' => (string) microtime(true),
            'storetype' => '3D_PAY_HOSTING',
            'hashAlgorithm' => 'ver3',
            'lang' => $order->locale === 'ar' ? 'ar' : 'fr',
            'BillToName' => $order->contact_name,
            'email' => $order->contact_email,
            'tel' => $order->contact_phone,
            'oid' => $order->number,
            'encoding' => 'UTF-8',
            'refreshtime' => '5',
        ];
        $params['HASH'] = $this->hash($params);

        return ['action' => $this->cfg('gateway_url'), 'fields' => $params];
    }

    public function hash(array $params): string
    {
        unset($params['HASH'], $params['encoding']);
        uksort($params, fn ($a, $b) => strcasecmp($a, $b));
        $vals = array_map(fn ($v) => str_replace(['\\', '|'], ['\\\\', '\\|'], trim((string) $v)), array_values($params));
        $plain = implode('|', $vals).'|'.str_replace(['\\', '|'], ['\\\\', '\\|'], (string) $this->cfg('store_key'));

        return base64_encode(pack('H*', hash('sha512', $plain)));
    }

    public function verifyCallback(Request $request): ?array
    {
        $params = $request->except([]);
        $received = (string) ($params['HASH'] ?? '');
        if (! $received || ! hash_equals($this->hash($params), $received)) {
            return null;
        }

        return [
            'event_id' => (string) ($params['TransId'] ?? $params['oid'].'-'.($params['ProcReturnCode'] ?? '')),
            'order_number' => (string) ($params['oid'] ?? ''),
            'amount' => (string) ($params['amount'] ?? '0'),
            'success' => ($params['ProcReturnCode'] ?? '') === '00' && strcasecmp((string) ($params['Response'] ?? ''), 'Approved') === 0,
            'reference' => $params['TransId'] ?? null,
        ];
    }

    public function callbackResponse(bool $ok): string
    {
        return $ok ? 'ACTION=POSTAUTH' : 'APPROVED';
    }
}
