<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Asynchronous payment notifications. Webhooks are the source of truth for
 * payments completed away from the browser (closed tabs, USSD, transfers).
 * Both handlers validate the gateway's signature before touching anything,
 * and amounts are re-checked against our own payment record.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private InvoiceGenerator $invoices) {}

    public function paystack(Request $request): Response
    {
        $secret = config('services.paystack.secret');

        abort_if(! $secret, 503, 'Paystack is not configured.');

        $signature = $request->header('x-paystack-signature');
        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        abort_unless(is_string($signature) && hash_equals($expected, $signature), 401);

        $event = $request->json()->all();

        if (($event['event'] ?? null) === 'charge.success') {
            $data = $event['data'] ?? [];
            $this->settle(
                reference: $data['reference'] ?? '',
                paidMinor: (int) ($data['amount'] ?? 0),
                channel: $data['channel'] ?? 'paystack',
                meta: $data,
            );
        }

        return response()->noContent(200);
    }

    public function flutterwave(Request $request): Response
    {
        $hash = config('services.flutterwave.webhook_hash');

        abort_if(! $hash, 503, 'Flutterwave webhook is not configured.');

        $signature = $request->header('verif-hash');

        abort_unless(is_string($signature) && hash_equals($hash, $signature), 401);

        $event = $request->json()->all();
        $data = $event['data'] ?? [];

        if (($event['event'] ?? null) === 'charge.completed' && ($data['status'] ?? null) === 'successful') {
            $this->settle(
                reference: $data['tx_ref'] ?? '',
                paidMinor: (int) round(((float) ($data['amount'] ?? 0)) * 100),
                channel: $data['payment_type'] ?? 'flutterwave',
                meta: $data,
            );
        }

        return response()->noContent(200);
    }

    private function settle(string $reference, int $paidMinor, string $channel, array $meta): void
    {
        if ($reference === '') {
            return;
        }

        $payment = Payment::query()->where('reference', $reference)->first();

        if ($payment === null || $payment->status === PaymentStatus::Successful) {
            return; // unknown or already settled — webhooks may be delivered more than once
        }

        $expectedMinor = (int) round((float) $payment->amount * 100);

        if ($paidMinor < $expectedMinor) {
            return; // short payment: leave pending for manual reconciliation
        }

        $payment->update([
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
            'channel' => $channel,
            'meta' => $meta,
        ]);

        $this->invoices->applyPayment($payment);
    }
}
