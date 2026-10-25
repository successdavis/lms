<?php

use App\Enums\CapsStatus;
use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Models\Applicant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');
    Storage::fake('local');
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $this->applicant = Applicant::firstOrFail(); // seeded, submitted
});

it('records an application-fee payment against the applicant, not an invoice', function () {
    // Seeded applicant is already submitted; the payment endpoint still works
    // (fee can be settled late when an office overrides), so use a draft one.
    $this->applicant->update(['status' => 'draft']);

    $this->actingAs($this->applicant->user)->post('/apply/fees/pay')->assertRedirect();

    $payment = $this->applicant->payments()->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and((float) $payment->amount)->toBe(2000.0)
        ->and($payment->invoice_id)->toBeNull()
        ->and($payment->student_id)->toBeNull();

    expect($this->applicant->fresh()->hasPaidApplicationFee())->toBeTrue();

    // Paying twice is refused.
    $this->actingAs($this->applicant->user)->post('/apply/fees/pay')->assertSessionHasErrors('application');
});

it('lets an applicant upload a credential and replaces unverified re-uploads', function () {
    $file = UploadedFile::fake()->create('waec.pdf', 100, 'application/pdf');

    $this->actingAs($this->applicant->user)
        ->post('/apply/documents', ['type' => 'olevel_result', 'file' => $file])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = $this->applicant->documents()->firstOrFail();
    expect($document->status)->toBe(DocumentStatus::Pending);
    Storage::disk('local')->assertExists($document->path);

    // Re-uploading the same type replaces the pending document.
    $this->actingAs($this->applicant->user)
        ->post('/apply/documents', ['type' => 'olevel_result', 'file' => UploadedFile::fake()->create('waec-2.pdf', 90, 'application/pdf')]);

    expect($this->applicant->documents()->count())->toBe(1)
        ->and($this->applicant->documents()->first()->original_name)->toBe('waec-2.pdf');
    Storage::disk('local')->assertMissing($document->path);
});

it('lets the admissions office verify and reject documents with a note', function () {
    $this->actingAs($this->applicant->user)
        ->post('/apply/documents', ['type' => 'olevel_result', 'file' => UploadedFile::fake()->create('waec.pdf', 100, 'application/pdf')]);
    $this->actingAs($this->applicant->user)
        ->post('/apply/documents', ['type' => 'jamb_result', 'file' => UploadedFile::fake()->create('jamb.pdf', 100, 'application/pdf')]);

    [$olevel, $jamb] = $this->applicant->documents()->orderBy('id')->get();

    $this->actingAs($this->admin)
        ->patch("/admissions/documents/{$olevel->id}", ['action' => 'verify'])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch("/admissions/documents/{$jamb->id}", ['action' => 'reject', 'note' => 'Blurry scan, re-upload'])
        ->assertRedirect();

    expect($olevel->fresh()->status)->toBe(DocumentStatus::Verified)
        ->and($olevel->fresh()->verified_by_id)->toBe($this->admin->id)
        ->and($jamb->fresh()->status)->toBe(DocumentStatus::Rejected)
        ->and($jamb->fresh()->note)->toBe('Blurry scan, re-upload');
});

it('keeps applicants out of document review and other applicants\' files', function () {
    $this->actingAs($this->applicant->user)
        ->post('/apply/documents', ['type' => 'olevel_result', 'file' => UploadedFile::fake()->create('waec.pdf', 100, 'application/pdf')]);
    $document = $this->applicant->documents()->firstOrFail();

    // Applicants cannot review documents (admissions routes are role-gated).
    $this->actingAs($this->applicant->user)
        ->patch("/admissions/documents/{$document->id}", ['action' => 'verify'])
        ->assertForbidden();

    // Another applicant cannot download someone else's file.
    $other = User::factory()->create();
    $other->assignRole('applicant');
    Applicant::create([
        'user_id' => $other->id,
        'admission_cycle_id' => $this->applicant->admission_cycle_id,
        'application_no' => 'APP-2025-00099',
        'programme_id' => $this->applicant->programme_id,
        'entry_mode' => 'utme',
    ]);

    $this->actingAs($other)->get("/apply/documents/{$document->id}/download")->assertForbidden();

    // The owner can.
    $this->actingAs($this->applicant->user)->get("/apply/documents/{$document->id}/download")->assertOk();
});

it('tracks the JAMB CAPS status', function () {
    $this->actingAs($this->admin)
        ->patch("/admissions/applicants/{$this->applicant->id}/caps", ['caps_status' => 'recommended'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->applicant->fresh()->caps_status)->toBe(CapsStatus::Recommended);

    $this->actingAs($this->admin)
        ->patch("/admissions/applicants/{$this->applicant->id}/caps", ['caps_status' => 'not-a-status'])
        ->assertSessionHasErrors('caps_status');
});

it('renders the applicant detail page for the admissions office', function () {
    $this->actingAs($this->admin)
        ->get("/admissions/applicants/{$this->applicant->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admissions/applicant-detail')
            ->where('applicant.application_no', $this->applicant->application_no)
            ->where('applicationFee', 2000));
});
