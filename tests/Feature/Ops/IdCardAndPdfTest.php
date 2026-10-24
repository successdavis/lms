<?php

use App\Enums\StudentStatus;
use App\Models\Applicant;
use App\Models\Course;
use App\Models\Student;
use App\Models\TranscriptRequest;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

require_once __DIR__.'/../Phase4/helpers.php';

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');
    Storage::fake('local');
    $this->student = Student::firstOrFail();
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
});

function assertPdf($response): void
{
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
}

it('offers every printable as a PDF from the same endpoint', function () {
    paySchoolFees($this->student);
    $this->actingAs($this->student->user)->post('/student/registration', [
        'course_ids' => Course::whereIn('code', ['CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112'])->pluck('id')->all(),
    ]);
    $payment = $this->student->payments()->firstOrFail();

    $this->actingAs($this->student->user)
        ->get('/student/print/course-form')
        ->assertInertia(fn ($page) => $page->where('pdfUrl', fn ($url) => str_contains($url, 'format=pdf')));

    assertPdf($this->actingAs($this->student->user)->get('/student/print/course-form?format=pdf'));
    assertPdf($this->actingAs($this->student->user)->get('/student/print/exam-card?format=pdf'));
    assertPdf($this->actingAs($this->student->user)->get("/student/print/receipts/{$payment->id}?format=pdf"));

    // PDFs keep the same gates as the pages.
    $other = User::factory()->create();
    $other->assignRole('student');
    Student::create(['user_id' => $other->id, 'programme_id' => $this->student->programme_id, 'level_id' => $this->student->level_id,
        'entry_session_id' => $this->student->entry_session_id, 'entry_mode' => 'utme']);
    $this->actingAs($other)->get("/student/print/receipts/{$payment->id}?format=pdf")->assertForbidden();
});

it('offers the admission letter as a PDF', function () {
    $applicant = Applicant::firstOrFail();
    $applicant->update(['status' => 'admitted', 'admitted_programme_id' => $applicant->programme_id, 'admitted_at' => now()]);

    assertPdf($this->actingAs($applicant->user)->get('/apply/print/admission-letter?format=pdf'));
});

it('requires a valid passport photo before printing the ID card', function () {
    $this->actingAs($this->student->user)->get('/student/id-card?format=pdf')->assertForbidden();

    $this->actingAs($this->student->user)
        ->post('/student/id-card/passport', ['passport' => UploadedFile::fake()->image('tiny.jpg', 100, 100)])
        ->assertSessionHasErrors('passport');

    $this->actingAs($this->student->user)
        ->post('/student/id-card/passport', ['passport' => UploadedFile::fake()->image('passport.jpg', 300, 400)])
        ->assertSessionHasNoErrors();

    $student = $this->student->fresh();
    Storage::disk('local')->assertExists($student->passport_path);

    $this->actingAs($student->user)
        ->get('/student/id-card')
        ->assertInertia(fn ($page) => $page->component('student/id-card')
            ->where('validUntil', 'September 2029') // 2025/2026 entry + 4-year B.Sc
            ->where('photoUrl', fn ($url) => $url !== null));

    assertPdf($this->actingAs($student->user)->get('/student/id-card?format=pdf'));
    $this->actingAs($student->user)->get('/student/id-card/passport')->assertOk();
});

it('verifies a student only through a signed link, showing live status', function () {
    $this->get("/verify/student/{$this->student->id}")->assertForbidden(); // unsigned

    $url = URL::signedRoute('verify.student', $this->student);
    $this->get($url.'x')->assertForbidden(); // tampered signature

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component('verify/student')
        ->where('student.matric_no', $this->student->matric_no)
        ->where('student.status', 'active'));

    $this->student->update(['status' => StudentStatus::Withdrawn]);
    $this->get($url)->assertInertia(fn ($page) => $page->where('student.status', 'withdrawn'));
});

it('verifies transcripts and embeds the verification QR in the PDF', function () {
    giveApprovedSemester($this->student);
    $this->actingAs($this->student->user)->post('/student/transcripts', [
        'destination' => 'local', 'delivery' => 'e_copy', 'recipient_name' => 'University of Ibadan PG School', 'recipient_email' => 'pg@ui.edu.ng',
    ]);
    $transcript = TranscriptRequest::firstOrFail();
    $this->actingAs($this->student->user)->post("/student/fees/{$transcript->invoice_id}/pay");

    $verify = URL::signedRoute('verify.transcript', $transcript);
    $this->get($verify)->assertInertia(fn ($page) => $page->where('transcript.issued', false));

    $this->actingAs($this->admin)->patch("/records/transcripts/{$transcript->id}", ['action' => 'process']);
    auth()->logout();

    $this->get($verify)->assertInertia(fn ($page) => $page
        ->component('verify/transcript')
        ->where('transcript.issued', true)
        ->where('student.cgpa', '5.00'));

    $pdf = $this->actingAs($this->admin)->get("/records/transcripts/{$transcript->id}/pdf");
    assertPdf($pdf);
    expect($pdf->getContent())->toContain('/Subtype /Image');
});
