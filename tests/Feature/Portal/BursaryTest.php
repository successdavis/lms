<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\InvoiceGenerator;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->bursar = User::where('email', 'bursar@demo.edu.ng')->firstOrFail();
    $this->student = Student::firstOrFail();
});

it('blocks students from the bursary portal', function () {
    $this->actingAs($this->student->user)->get('/bursary/fees')->assertForbidden();
});

it('lets the bursar create a fee type and a fee rule', function () {
    $this->actingAs($this->bursar)
        ->post('/bursary/fees/types', [
            'name' => 'Hostel Fee',
            'code' => 'HST',
            'blocks_registration' => false,
            'is_recurring' => true,
        ])
        ->assertRedirect();

    $type = FeeType::where('code', 'HST')->firstOrFail();

    $this->actingAs($this->bursar)
        ->post('/bursary/fees/structures', [
            'fee_type_id' => $type->id,
            'academic_session_id' => AcademicSession::current()->id,
            'indigene_scope' => 'non_indigene',
            'amount' => 45000,
        ])
        ->assertRedirect();

    expect(FeeStructure::where('fee_type_id', $type->id)->count())->toBe(1);
});

it('records a bank-teller payment against an invoice', function () {
    $invoice = app(InvoiceGenerator::class)->generateFor($this->student, AcademicSession::current());

    $this->actingAs($this->bursar)
        ->post('/bursary/payments', [
            'invoice_number' => $invoice->number,
            'amount' => (float) $invoice->total,
            'reference' => 'TELLER-00123',
        ])
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('confirms a pending payment and applies it to the invoice', function () {
    $invoice = app(InvoiceGenerator::class)->generateFor($this->student, AcademicSession::current());

    $payment = $invoice->payments()->create([
        'student_id' => $this->student->id,
        'gateway' => PaymentGateway::Bank,
        'reference' => 'PEND-001',
        'amount' => $invoice->total,
        'status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($this->bursar)
        ->patch("/bursary/payments/{$payment->id}", ['action' => 'confirm'])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('renders the revenue report', function () {
    app(InvoiceGenerator::class)->generateFor($this->student, AcademicSession::current());

    $this->actingAs($this->bursar)
        ->get('/bursary/reports')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('bursary/reports')
            ->where('summary.invoiced', 155000));
});
