<?php

namespace App\Services\Finance\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Remita (TSA) driver — federal institutions must collect through Remita.
 * Flow: generate an RRR bound to the invoice amount, send the student to
 * Remita's hosted finalize page (card/transfer/USSD), or let them pay the
 * RRR at any bank branch; then confirm via the status endpoint.
 */
class RemitaGateway implements PaymentGatewayInterface
{
    public function __construct(private InvoiceGenerator $invoices) {}

    public function initialize(Payment $payment): ?string
    {
        $merchantId = $this->config('merchant_id');
        $serviceTypeId = $this->config('service_type_id');
        $apiKey = $this->config('api_key');
        $orderId = $payment->reference;
        $amount = number_format((float) $payment->amount, 2, '.', '');

        $hash = hash('sha512', $merchantId.$serviceTypeId.$orderId.$amount.$apiKey);

        $response = Http::withHeaders([
            'Authorization' => "remitaConsumerKey={$merchantId},remitaConsumerToken={$hash}",
            'Content-Type' => 'application/json',
        ])->post($this->config('base_url').'/echannelsvc/merchant/api/paymentinit', [
            'serviceTypeId' => $serviceTypeId,
            'amount' => $amount,
            'orderId' => $orderId,
            'payerName' => $payment->payerUser()?->name,
            'payerEmail' => $payment->payerUser()?->email,
            'payerPhone' => $payment->student?->phone ?? $payment->applicant?->phone ?? '',
            'description' => $payment->description(),
        ])->throw();

        $data = $this->decodeJsonp($response->body());

        $rrr = $data['RRR'] ?? null;

        if ($rrr === null) {
            throw new RuntimeException('Remita RRR generation failed: '.($data['statusMessage'] ?? $response->body()));
        }

        $payment->update(['rrr' => $rrr, 'meta' => $data]);

        // Hosted payment page; the RRR is also payable at any bank branch.
        $finalizeHash = hash('sha512', $rrr.$apiKey.$merchantId);

        return $this->config('base_url_root').'/ecomm/finalize.reg'
            .'?merchantId='.$merchantId
            .'&rrr='.$rrr
            .'&hash='.$finalizeHash;
    }

    public function verify(Payment $payment): bool
    {
        if ($payment->rrr === null) {
            return false;
        }

        $merchantId = $this->config('merchant_id');
        $apiKey = $this->config('api_key');
        $hash = hash('sha512', $payment->rrr.$apiKey.$merchantId);

        $response = Http::withHeaders([
            'Authorization' => "remitaConsumerKey={$merchantId},remitaConsumerToken={$hash}",
        ])->get($this->config('base_url')."/echannelsvc/{$merchantId}/{$payment->rrr}/{$hash}/status.reg")
            ->throw();

        $data = $this->decodeJsonp($response->body());

        // '00' = successful, '01' = approved; bank-branch RRR payments can
        // confirm hours later, so a pending status is not a failure.
        $succeeded = in_array($data['status'] ?? null, ['00', '01'], true)
            && (float) ($data['amount'] ?? 0) >= (float) $payment->amount;

        if ($succeeded) {
            $payment->update([
                'status' => PaymentStatus::Successful,
                'paid_at' => now(),
                'channel' => $data['channnel'] ?? $data['channel'] ?? 'remita',
                'meta' => $data,
            ]);
            if ($payment->invoice_id !== null) {
                $this->invoices->applyPayment($payment);
            }
        } else {
            $payment->update(['meta' => $data]);
        }

        return $succeeded;
    }

    /**
     * Remita demo endpoints wrap JSON in JSONP ("jsonp ({...})").
     */
    private function decodeJsonp(string $body): array
    {
        $start = strpos($body, '{');
        $end = strrpos($body, '}');

        if ($start === false || $end === false) {
            throw new RuntimeException('Unexpected Remita response: '.$body);
        }

        return json_decode(substr($body, $start, $end - $start + 1), true) ?? [];
    }

    private function config(string $key): string
    {
        $value = config("services.remita.{$key}");

        if (! $value) {
            throw new RuntimeException("Remita config [services.remita.{$key}] is not set.");
        }

        return $value;
    }
}
