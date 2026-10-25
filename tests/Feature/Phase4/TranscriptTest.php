<?php

use App\Enums\InvoicePurpose;
use App\Enums\TranscriptStatus;
use App\Models\Student;
use App\Models\TranscriptRequest;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');
    $this->student = Student::firstOrFail();
    $this->registrar = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
});

function requestTranscript($test, array $overrides = [])
{
    return $test->actingAs($test->student->user)->post('/student/transcripts', $overrides + [
        'destination' => 'international',
        'delivery' => 'hard_copy',
        'recipient_name' => 'University of Toronto, Graduate Admissions',
        'recipient_address' => '27 King\'s College Circle, Toronto, Canada',
    ]);
}

it('refuses a transcript request when there are no approved results', function () {
    requestTranscript($this)->assertSessionHasErrors('transcript');
    expect(TranscriptRequest::count())->toBe(0);
});

it('creates the request with a fee-schedule invoice that does not count as school fees', function () {
    giveApprovedSemester($this->student);

    requestTranscript($this)->assertRedirect()->assertSessionHasNoErrors();

    $transcript = TranscriptRequest::firstOrFail();
    expect($transcript->status)->toBe(TranscriptStatus::AwaitingPayment)
        ->and((float) $transcript->fee)->toBe(50000.0) // international hard copy
        ->and($transcript->invoice->purpose)->toBe(InvoicePurpose::Transcript);

    // The school-fees invoice for the session can still be generated.
    $this->actingAs($this->student->user)->post('/student/fees/generate')->assertSessionHasNoErrors();
    expect($this->student->invoices()->where('purpose', 'school_fees')->count())->toBe(1);
});

it('requires a recipient email for e-copies', function () {
    giveApprovedSemester($this->student);

    requestTranscript($this, ['delivery' => 'e_copy', 'recipient_address' => null])
        ->assertSessionHasErrors('recipient_email');
});

it('runs the full pipeline: pay, process, generate PDF, dispatch', function () {
    giveApprovedSemester($this->student);
    requestTranscript($this);
    $transcript = TranscriptRequest::firstOrFail();

    // Paying the transcript invoice moves the request into the records queue.
    $this->actingAs($this->student->user)->post("/student/fees/{$transcript->invoice_id}/pay")->assertRedirect();
    expect($transcript->fresh()->status)->toBe(TranscriptStatus::Paid);

    // The PDF is withheld until the request is being processed.
    $this->actingAs($this->registrar)->get("/records/transcripts/{$transcript->id}/pdf")->assertForbidden();

    // Dispatch is refused before processing.
    $this->actingAs($this->registrar)
        ->patch("/records/transcripts/{$transcript->id}", ['action' => 'dispatch', 'dispatch_reference' => 'DHL-1'])
        ->assertSessionHasErrors('transcript');

    $this->actingAs($this->registrar)
        ->patch("/records/transcripts/{$transcript->id}", ['action' => 'process'])
        ->assertSessionHasNoErrors();
    expect($transcript->fresh()->status)->toBe(TranscriptStatus::Processing);

    $pdf = $this->actingAs($this->registrar)->get("/records/transcripts/{$transcript->id}/pdf");
    $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs($this->registrar)
        ->patch("/records/transcripts/{$transcript->id}", ['action' => 'dispatch', 'dispatch_reference' => 'DHL-5512340987'])
        ->assertSessionHasNoErrors();

    $transcript->refresh();
    expect($transcript->status)->toBe(TranscriptStatus::Dispatched)
        ->and($transcript->dispatch_reference)->toBe('DHL-5512340987')
        ->and($transcript->dispatched_at)->not->toBeNull();
});

it('keeps students out of the records office', function () {
    $this->actingAs($this->student->user)->get('/records/transcripts')->assertForbidden();
});

it('builds the results page from the shared transcript builder', function () {
    giveApprovedSemester($this->student); // 75% everywhere -> all A

    $this->actingAs($this->student->user)
        ->get('/student/results')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('semesters', 1)
            ->where('cgpa', '5.00')
            ->where('classification', 'First Class Honours'));
});
