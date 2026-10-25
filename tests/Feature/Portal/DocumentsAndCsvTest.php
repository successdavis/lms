<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->student = Student::firstOrFail();
    $this->lecturerUser = User::where('email', 'lecturer@demo.edu.ng')->firstOrFail();
    $this->travelTo('2025-10-20 10:00:00');
});

function registerWithFullPayment($test): void
{
    $test->actingAs($test->student->user)->post('/student/fees/generate');
    $invoice = $test->student->invoices()->firstOrFail();
    $test->actingAs($test->student->user)->post("/student/fees/{$invoice->id}/pay");
    $test->actingAs($test->student->user)->post('/student/registration', [
        'course_ids' => Course::whereIn('code', [
            'CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112',
        ])->pluck('id')->all(),
    ]);
}

it('prints the course form after registration', function () {
    registerWithFullPayment($this);

    $this->actingAs($this->student->user)
        ->get('/student/print/course-form')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('student/print/course-form')
            ->where('registration.total_units', 16)
            ->has('registration.courses', 6));
});

it('refuses the exam card before registration', function () {
    $this->actingAs($this->student->user)->get('/student/print/exam-card')->assertForbidden();
});

it('refuses the exam card while blocking fees are only part-paid', function () {
    // Allow registration at 70% but the exam card still demands 100%.
    Institution::current()->update(['min_fee_percent_for_registration' => 70]);

    $this->actingAs($this->student->user)->post('/student/fees/generate');
    $invoice = $this->student->invoices()->firstOrFail();

    // Record a 70% bank payment via the bursary.
    $bursar = User::where('email', 'bursar@demo.edu.ng')->firstOrFail();
    $this->actingAs($bursar)->post('/bursary/payments', [
        'invoice_number' => $invoice->number,
        'amount' => round((float) $invoice->total * 0.7, 2),
        'reference' => 'TELLER-70PCT',
    ]);

    $this->actingAs($this->student->user)->post('/student/registration', [
        'course_ids' => Course::whereIn('code', [
            'CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112',
        ])->pluck('id')->all(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->student->user)->get('/student/print/exam-card')->assertForbidden();
});

it('prints the exam card once fees are fully paid and courses registered', function () {
    registerWithFullPayment($this);

    $this->actingAs($this->student->user)
        ->get('/student/print/exam-card')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('student/print/exam-card')
            ->has('courses', 6));
});

it('prints a receipt for an own successful payment only', function () {
    registerWithFullPayment($this);
    $payment = $this->student->payments()->firstOrFail();

    $this->actingAs($this->student->user)
        ->get("/student/print/receipts/{$payment->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('student/print/receipt')
            ->where('payment.reference', $payment->reference));

    // Another student cannot print it.
    $otherUser = User::factory()->create();
    $otherUser->assignRole('student');
    Student::create([
        'user_id' => $otherUser->id,
        'programme_id' => $this->student->programme_id,
        'level_id' => $this->student->level_id,
        'entry_session_id' => AcademicSession::current()->id,
        'entry_mode' => 'utme',
    ]);

    $this->actingAs($otherUser)->get("/student/print/receipts/{$payment->id}")->assertForbidden();
});

it('uploads scores from CSV, saving valid rows and reporting bad ones', function () {
    registerWithFullPayment($this);

    $csv = "matric_no,ca_score,exam_score\n"
        ."{$this->student->matric_no},25,50\n"
        ."FAKE/00/XXX/9999,20,40\n";

    $file = UploadedFile::fake()->createWithContent('scores.csv', $csv);
    $course = Course::where('code', 'CSC 101')->firstOrFail();

    $this->actingAs($this->lecturerUser)
        ->post("/lecturer/courses/{$course->id}/scores/csv", ['file' => $file])
        ->assertRedirect()
        ->assertSessionHasErrors();

    $result = Result::firstOrFail();
    expect((float) $result->total_score)->toBe(75.0)
        ->and($result->grade_letter)->toBe('A');
});

it('rejects a CSV with a wrong header', function () {
    registerWithFullPayment($this);

    $file = UploadedFile::fake()->createWithContent('scores.csv', "reg_no,ca,exam\nX,1,2\n");
    $course = Course::where('code', 'CSC 101')->firstOrFail();

    $this->actingAs($this->lecturerUser)
        ->post("/lecturer/courses/{$course->id}/scores/csv", ['file' => $file])
        ->assertSessionHasErrors('file');
});
