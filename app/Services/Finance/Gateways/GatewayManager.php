<?php

namespace App\Services\Finance\Gateways;

use App\Enums\PaymentGateway;
use InvalidArgumentException;

class GatewayManager
{
    /**
     * The gateway students pay through, from config('services.payments.default').
     */
    public function defaultGateway(): PaymentGateway
    {
        return PaymentGateway::from(config('services.payments.default', 'paystack'));
    }

    public function driver(?PaymentGateway $gateway = null): PaymentGatewayInterface
    {
        $gateway ??= $this->defaultGateway();

        // The demo driver stands in for any gateway when demo mode is on.
        if (config('services.payments.demo', true)) {
            return app(DemoGateway::class);
        }

        return match ($gateway) {
            PaymentGateway::Paystack => app(PaystackGateway::class),
            default => throw new InvalidArgumentException(
                "No driver implemented for gateway [{$gateway->value}] yet."
            ),
        };
    }
}
