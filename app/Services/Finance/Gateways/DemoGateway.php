<?php

namespace App\Services\Finance\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Finance\InvoiceGenerator;

/**
 * Development/demo gateway: every payment succeeds immediately. Swap for
 * Paystack/Remita in production via config('services.payments.default').
 */
class DemoGateway implements PaymentGatewayInterface
{
    public function __construct(private InvoiceGenerator $invoices) {}

    public function initialize(Payment $payment): ?string
    {
        $payment->update([
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
            'channel' => 'demo',
        ]);

        $this->invoices->applyPayment($payment);

        return null;
    }

    public function verify(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Successful;
    }
}
