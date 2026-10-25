<?php

use App\Enums\InvoicePurpose;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Payment;
use App\Models\RegisteredCourse;
use App\Models\Result;
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
    $this->travelTo('2025-10-20 10:00:00');
    $this->student = Student::firstOrFail();
    $this->lecturer = User::where('email', 'lecturer@demo.edu.ng')->firstOrFail();
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();

    // Pay fees and register so the lecturer has a score sheet.
    $this->actingAs($this->student->user)->post('/student/fees/generate');
    $invoice = $this->student->invoices()->firstOrFail();
    $this->actingAs($this->student->user)->post("/student/fees/{$invoice->id}/pay");
    $this->actingAs($this->student->user)->post('/student/registration', [
        'course_ids' => Course::whereIn('code', ['CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112'])->pluck('id')->all(),
    ]);
    $this->course = Course::where('code', 'CSC 101')->firstOrFail();
    $this->registered = RegisteredCourse::where('course_id', $this->course->id)->firstOrFail();
});

function scoreAs($test, float $ca, float $exam): void
{
    $test->actingAs($test->lecturer)->post("/lecturer/courses/{$test->course->id}/scores", [
        'scores' => [['registered_course_id' => $test->registered->id, 'ca_score' => $ca, 'exam_score' => $exam]],
    ]);
}

it('records who changed a score, with old and new values', function () {
    scoreAs($this, 25, 50); // 75 A
    scoreAs($this, 20, 40); // amended to 60 B

    $result = Result::firstOrFail();
    $log = AuditLog::where('auditable_type', Result::class)
        ->where('auditable_id', $result->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($log->user_id)->toBe($this->lecturer->id)
        ->and((float) $log->old_values['total_score'])->toBe(75.0)
        ->and((float) $log->new_values['total_score'])->toBe(60.0)
        ->and($log->new_values['grade_letter'])->toBe('B');
});

it('records bulk result approvals as one entry naming every result', function () {
    scoreAs($this, 25, 50);

    $this->actingAs($this->admin)->post("/staff/approvals/{$this->course->id}", ['stage' => 'hod']);

    $log = AuditLog::where('event', 'results.hod_approved')->firstOrFail();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->new_values['result_ids'])->toBe([Result::firstOrFail()->id])
        ->and($log->description)->toContain('CSC 101');
});

it('records payments settled by a webhook as system actions', function () {
    config(['services.paystack.secret' => 'sk_test']);
    $invoice = app(InvoiceGenerator::class)
        ->charge($this->student, AcademicSession::current(), InvoicePurpose::Transcript, 'TRN', 'Transcript Fee', 5000, 'test');
    $payment = $invoice->payments()->create([
        'student_id' => $this->student->id, 'gateway' => 'paystack', 'reference' => 'WH-AUDIT', 'amount' => 5000, 'status' => 'pending',
    ]);

    auth()->logout();
    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'WH-AUDIT', 'amount' => 500000, 'channel' => 'card']]);
    $this->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test'), 'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $log = AuditLog::where('auditable_type', Payment::class)->where('auditable_id', $payment->id)->where('event', 'updated')->firstOrFail();
    expect($log->user_id)->toBeNull()->and($log->new_values['status'])->toBe('successful');
});

it('cannot be edited or deleted', function () {
    $log = AuditLog::firstOrFail();
    $original = $log->event;

    $log->update(['event' => 'tampered']);
    $log->delete();

    expect(AuditLog::find($log->id))->not->toBeNull()
        ->and(AuditLog::find($log->id)->event)->toBe($original);
});

it('is visible to the registry only', function () {
    $this->actingAs($this->lecturer)->get('/admin/audit')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/audit?type=Result')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/audit'));
});
