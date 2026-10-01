<?php

namespace App\Services\Finance\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway implements PaymentGatewayInterface
{
    public function __construct(private InvoiceGenerator $invoices) {}

    public function initialize(Payment $payment): ?string
    {
        $response = Http::withToken($this->secret())
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $payment->student->user->email,
                'amount' => (int) round((float) $payment->amount * 100), // kobo
                'reference' => $payment->reference,
                'callback_url' => route('student.fees.verify', $payment),
            ])
            ->throw()
            ->json();

        if (! ($response['status'] ?? false)) {
            throw new RuntimeException('Paystack initialization failed: '.($response['message'] ?? 'unknown error'));
        }

        return $response['data']['authorization_url'];
    }

    public function verify(Payment $payment): bool
    {
        $response = Http::withToken($this->secret())
            ->get("https://api.paystack.co/transaction/verify/{$payment->reference}")
            ->throw()
            ->json();

        $data = $response['data'] ?? [];
        $succeeded = ($data['status'] ?? null) === 'success'
            && (int) ($data['amount'] ?? 0) >= (int) round((float) $payment->amount * 100);

        $payment->update([
            'status' => $succeeded ? PaymentStatus::Successful : PaymentStatus::Failed,
            'paid_at' => $succeeded ? now() : null,
            'channel' => $data['channel'] ?? null,
            'meta' => $data,
        ]);

        if ($succeeded) {
            $this->invoices->applyPayment($payment);
        }

        return $succeeded;
    }

    private function secret(): string
    {
        $secret = config('services.paystack.secret');

        if (! $secret) {
            throw new RuntimeException('PAYSTACK_SECRET_KEY is not configured.');
        }

        return $secret;
    }
}
