<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Request;

/**
 * Contract for online payment providers (CMI…).
 *
 * An order is NEVER marked as paid from the browser redirect: only a server-to-server notification whose
 * signature has been verified by the gateway may create a "confirmed" payment.
 */
interface PaymentGatewayInterface
{
    /** Provider code, equal to payment_methods.provider. */
    public function code(): string;

    /** True only when the provider credentials are configured (payment_methods.config). */
    public function isConfigured(): bool;

    /**
     * Form to send the customer to the provider's hosted payment page (auto-submitted by the browser).
     *
     * @param array<string,mixed> $order       orders row
     * @param string              $accessToken the order's tracking token (return URLs work without a session)
     * @return array{action:string,fields:array<string,string>}
     */
    public function start(array $order, string $accessToken): array;

    /**
     * Verify a provider callback (signature, approval, currency, order reference).
     *
     * @return array{verified:bool,approved:bool,order_number?:string,amount?:string,transaction_id?:string,raw?:array<string,mixed>,error?:string}
     */
    public function verifyCallback(Request $request): array;

    /** Plain-text body the provider expects in answer to its callback. */
    public function reply(bool $verified, bool $approved): string;

    /**
     * Settings form of this provider in the back office.
     *
     * @return list<array{key:string,label:string,secret:bool,pattern:string}>
     */
    public static function fields(): array;
}
