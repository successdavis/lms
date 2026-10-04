<?php

namespace App\Services\Finance\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function __construct(private InvoiceGenerator $invoices) {}

    public function initialize(Payment $payment): ?string
    {
        $response = Http::withToken($this->secret())
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $payment->reference,
                'amount' => (string) $payment->amount,
                'currency' => 'NGN',
                'redirect_url' => $payment->verificationUrl(),
                'customer' => [
                    'email' => $payment->payerUser()?->email,
                    'name' => $payment->payerUser()?->name,
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'description' => $payment->description(),
                ],
            ])
            ->throw()
            ->json();

        if (($response['status'] ?? null) !== 'success') {
            throw new RuntimeException('Flutterwave initialization failed: '.($response['message'] ?? 'unknown error'));
        }

        return $response['data']['link'];
    }

    public function verify(Payment $payment): bool
    {
        $response = Http::withToken($this->secret())
            ->get('https://api.flutterwave.com/v3/transactions/verify_by_reference', [
                'tx_ref' => $payment->reference,
            ])
            ->throw()
            ->json();

        $data = $response['data'] ?? [];
        $succeeded = ($data['status'] ?? null) === 'successful'
            && ($data['currency'] ?? null) === 'NGN'
            && (float) ($data['amount'] ?? 0) >= (float) $payment->amount;

        $payment->update([
            'status' => $succeeded ? PaymentStatus::Successful : PaymentStatus::Failed,
            'paid_at' => $succeeded ? now() : null,
            'channel' => $data['payment_type'] ?? null,
            'meta' => $data,
        ]);

        if ($succeeded) {
            if ($payment->invoice_id !== null) {
                $this->invoices->applyPayment($payment);
            }
        }

        return $succeeded;
    }

    private function secret(): string
    {
        $secret = config('services.flutterwave.secret');

        if (! $secret) {
            throw new RuntimeException('FLUTTERWAVE_SECRET_KEY is not configured.');
        }

        return $secret;
    }
}
