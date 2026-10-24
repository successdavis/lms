<?php

namespace App\Notifications;

use App\Models\Payment;

class PaymentReceived extends PortalNotification
{
    public static function for(Payment $payment): self
    {
        $amount = '₦'.number_format((float) $payment->amount, 2);
        $what = $payment->description();

        return new self(
            title: "Payment received: {$amount}",
            body: "We received {$amount} for {$what} (reference {$payment->reference}). Your receipt is available on the portal.",
            url: $payment->applicant_id !== null ? '/apply' : '/student/fees',
            sms: "Payment of {$amount} received. Ref {$payment->reference}.",
        );
    }
}
