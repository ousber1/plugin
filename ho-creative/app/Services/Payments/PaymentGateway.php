<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /** Fields + action URL for an auto-submitted redirect form. */
    public function redirectForm(Order $order, string $amount): array;

    /**
     * Verify a provider callback. Returns null when the signature is invalid, otherwise
     * ['event_id' => ..., 'order_number' => ..., 'amount' => ..., 'success' => bool, 'reference' => ...].
     */
    public function verifyCallback(Request $request): ?array;

    /** Body the provider expects in response to a callback. */
    public function callbackResponse(bool $ok): string;
}
