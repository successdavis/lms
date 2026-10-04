<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\Payment;
use App\Models\Student;
use App\Services\Finance\InvoiceGenerator;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    config(['services.paystack.secret' => 'sk_test_secret']);
    config(['services.flutterwave.webhook_hash' => 'flw-verif-hash']);

    $student = Student::firstOrFail();
    $this->invoice = app(InvoiceGenerator::class)->generateFor($student, AcademicSession::current());
    $this->payment = $this->invoice->payments()->create([
        'student_id' => $student->id,
        'gateway' => PaymentGateway::Paystack,
        'reference' => 'PAY-WEBHOOK-1',
        'amount' => $this->invoice->total,
        'status' => PaymentStatus::Pending,
    ]);
});

function paystackBody(Payment $payment, ?int $amountKobo = null): string
{
    return json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => $payment->reference,
            'amount' => $amountKobo ?? (int) round((float) $payment->amount * 100),
            'channel' => 'card',
        ],
    ]);
}

it('settles a pending payment from a signed paystack webhook', function () {
    $body = paystackBody($this->payment);
    $signature = hash_hmac('sha512', $body, 'sk_test_secret');

    $this->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(200);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('rejects a paystack webhook with a bad signature', function () {
    $body = paystackBody($this->payment);

    $this->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => 'forged',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(401);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('ignores a paystack webhook paying less than the expected amount', function () {
    $body = paystackBody($this->payment, 1000); // ₦10 against a ₦155,000 invoice
    $signature = hash_hmac('sha512', $body, 'sk_test_secret');

    $this->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(200);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('settles a pending payment from a flutterwave webhook with the verif-hash', function () {
    $this->payment->update(['gateway' => PaymentGateway::Flutterwave]);

    $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $this->payment->reference,
            'status' => 'successful',
            'amount' => (float) $this->payment->amount,
            'payment_type' => 'card',
        ],
    ], ['verif-hash' => 'flw-verif-hash'])->assertStatus(200);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Successful);
});

it('rejects a flutterwave webhook without the verif-hash', function () {
    $this->postJson('/webhooks/flutterwave', ['event' => 'charge.completed'], ['verif-hash' => 'wrong'])
        ->assertStatus(401);
});
