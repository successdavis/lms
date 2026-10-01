<?php

namespace App\Services\Finance\Gateways;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Begin a checkout for a pending payment. Returns a redirect URL to the
     * gateway's hosted page, or null when the payment completed synchronously
     * (e.g. the demo gateway, or a bank-teller record).
     */
    public function initialize(Payment $payment): ?string;

    /**
     * Confirm the payment's final state with the gateway and apply it to the
     * invoice when successful. Returns true when the payment is successful.
     */
    public function verify(Payment $payment): bool;
}
