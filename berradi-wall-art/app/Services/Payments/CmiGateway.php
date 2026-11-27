<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Request;
use App\Helpers\Money;

/**
 * CMI (Centre Monétique Interbancaire) — "3D Pay Hosting" card payment, hash version 3.
 *
 * Flow:
 *  1. start(): the customer's browser posts a signed form to the CMI hosted page (card data never touches this site);
 *  2. CMI calls callbackUrl server-to-server with the result, signed with the store key → verifyCallback();
 *     only this call records the payment, and we answer "ACTION=POSTAUTH" to capture an approved pre-authorisation;
 *  3. CMI sends the browser back to okUrl / failUrl: informative only, never marks anything as paid.
 *
 * Hash v3: every posted parameter except "hash" and "encoding", sorted case-insensitively by name, values
 * escaped (\ → \\, | → \|) and joined with "|", then "|" + escaped store key; SHA-512, base64 of the raw digest.
 */
final class CmiGateway implements PaymentGatewayInterface
{
    public const TEST_URL = 'https://testpayment.cmi.co.ma/fim/est3Dgate';
    public const LIVE_URL = 'https://payment.cmi.co.ma/fim/est3Dgate';
    private const CURRENCY_MAD = '504';

    /** @param array{client_id?:string,store_key?:string}|null $config */
    public function __construct(private ?array $config = null, private ?bool $testMode = null)
    {
        if ($this->config === null) {
            $method = PaymentRegistry::methodRow('cmi');
            $this->config = $method !== null ? PaymentRegistry::decryptConfig($method['config'] ?? null) : [];
            $this->testMode = $method !== null ? (bool) (int) $method['test_mode'] : true;
        }
        $this->testMode ??= true;
    }

    public function code(): string
    {
        return 'cmi';
    }

    public static function fields(): array
    {
        return [
            ['key' => 'client_id', 'label' => 'payment_methods.cmi_client_id', 'secret' => false, 'pattern' => '/^[0-9]{5,20}$/'],
            ['key' => 'store_key', 'label' => 'payment_methods.cmi_store_key', 'secret' => true, 'pattern' => '/^[\x21-\x7E]{6,128}$/'],
        ];
    }

    public function isConfigured(): bool
    {
        return preg_match('/^[0-9]{5,20}$/', (string) ($this->config['client_id'] ?? '')) === 1
            && (string) ($this->config['store_key'] ?? '') !== '';
    }

    public function gatewayUrl(): string
    {
        return $this->testMode ? self::TEST_URL : self::LIVE_URL;
    }

    public function start(array $order, string $accessToken): array
    {
        $amount = Money::normalize((string) ($order['balance_due'] ?? $order['grand_total']));
        $number = (string) $order['order_number'];
        $back = fn (string $result) => absolute_url('payment-return/cmi/' . $result . '/' . rawurlencode($number) . '/' . $accessToken);
        $fields = [
            'clientid' => (string) $this->config['client_id'],
            'storetype' => '3D_PAY_HOSTING',
            'TranType' => 'PreAuth',
            'amount' => $amount,
            'currency' => self::CURRENCY_MAD,
            'oid' => $number,
            'okUrl' => $back('ok'),
            'failUrl' => $back('fail'),
            'callbackUrl' => absolute_url('webhooks/payments/cmi'),
            'shopurl' => absolute_url('/'),
            'lang' => locale() === 'ar' ? 'ar' : 'fr',
            'rnd' => bin2hex(random_bytes(10)),
            'hashAlgorithm' => 'ver3',
            'encoding' => 'UTF-8',
            'refreshtime' => '5',
            'AutoRedirect' => 'true',
            'BillToName' => mb_substr((string) $order['customer_name'], 0, 60),
            'BillToStreet1' => mb_substr((string) ($order['shipping_address'] ?? ''), 0, 100),
            'BillToCity' => mb_substr((string) ($order['shipping_city'] ?? ''), 0, 60),
            'BillToCountry' => self::CURRENCY_MAD,
            'email' => (string) ($order['customer_email'] ?? ''),
            'tel' => (string) ($order['customer_phone'] ?? ''),
        ];
        $fields['HASH'] = self::hash($fields, (string) $this->config['store_key']);
        return ['action' => $this->gatewayUrl(), 'fields' => $fields];
    }

    /** @param array<string,mixed> $params */
    public static function hash(array $params, string $storeKey): string
    {
        $keys = array_keys($params);
        natcasesort($keys);
        $escape = fn (string $v): string => str_replace('|', '\\|', str_replace('\\', '\\\\', $v));
        $plain = '';
        foreach ($keys as $k) {
            $lower = strtolower((string) $k);
            if ($lower === 'hash' || $lower === 'encoding') {
                continue;
            }
            $plain .= $escape(trim(is_scalar($params[$k]) ? (string) $params[$k] : '')) . '|';
        }
        $plain .= $escape($storeKey);
        return base64_encode((string) pack('H*', hash('sha512', $plain)));
    }

    public function verifyCallback(Request $request): array
    {
        $p = array_filter($request->post(), 'is_scalar');
        $received = (string) ($p['HASH'] ?? $p['hash'] ?? '');
        if (!$this->isConfigured() || $received === '') {
            return ['verified' => false, 'approved' => false, 'error' => 'missing hash or configuration'];
        }
        if (!hash_equals(self::hash($p, (string) $this->config['store_key']), $received)) {
            return ['verified' => false, 'approved' => false, 'error' => 'bad signature'];
        }
        if ((string) ($p['clientid'] ?? '') !== (string) $this->config['client_id']) {
            return ['verified' => false, 'approved' => false, 'error' => 'other merchant'];
        }
        if ((string) ($p['currency'] ?? '') !== self::CURRENCY_MAD) {
            return ['verified' => false, 'approved' => false, 'error' => 'currency'];
        }
        $amount = (string) ($p['amount'] ?? '');
        if (!preg_match('/^\d{1,10}([.,]\d{1,2})?$/', $amount)) {
            return ['verified' => false, 'approved' => false, 'error' => 'amount'];
        }
        $approved = (string) ($p['ProcReturnCode'] ?? '') === '00' && strcasecmp((string) ($p['Response'] ?? ''), 'Approved') === 0;
        unset($p['HASH'], $p['hash']);
        // card data is never sent by CMI in full, but keep only what an accountant needs
        $raw = array_intersect_key($p, array_flip(['oid', 'amount', 'currency', 'Response', 'ProcReturnCode', 'TransId', 'AuthCode',
            'mdStatus', 'ErrMsg', 'EXTRA_TRXDATE', 'MaskedPan', 'EXTRA_CARDBRAND', 'xid']));
        return [
            'verified' => true,
            'approved' => $approved,
            'order_number' => (string) ($p['oid'] ?? ''),
            'amount' => Money::normalize(str_replace(',', '.', $amount)),
            'transaction_id' => 'cmi:' . (string) ($p['TransId'] ?? $p['xid'] ?? ''),
            'raw' => $raw,
        ];
    }

    public function reply(bool $verified, bool $approved): string
    {
        if (!$verified) {
            return 'FAILURE';
        }
        // capture the pre-authorisation only when it was approved; otherwise just acknowledge
        return $approved ? 'ACTION=POSTAUTH' : 'APPROVED';
    }
}
