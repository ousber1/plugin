<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Request;

/**
 * Contract for future online payment providers (CMI, PayPal, ...).
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
     * Start a payment for an order: returns the URL (or auto-submitted form data) to send the customer to.
     *
     * @param array<string,mixed> $order
     * @return array{redirect_url:string,form?:array<string,string>}
     */
    public function start(array $order): array;

    /**
     * Verify a provider callback (signature, amount, currency, order reference).
     *
     * @return array{verified:bool,order_number?:string,amount?:string,transaction_id?:string,raw?:array<string,mixed>}
     */
    public function verifyCallback(Request $request): array;
}
