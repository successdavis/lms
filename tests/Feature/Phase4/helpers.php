<?php

use App\Enums\RegistrationStatus;
use App\Enums\ResultStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Level;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Academics\GradingService;
use App\Services\Academics\ResultComputationService;
use App\Services\Finance\InvoiceGenerator;

/**
 * Give the student a senate-approved first semester (all six 100L courses at
 * the given score split) and its GPA snapshot.
 */
function giveApprovedSemester(Student $student, float $ca = 25, float $exam = 50): void
{
    $semester = Semester::where('number', 1)->firstOrFail();
    $registration = CourseRegistration::updateOrCreate(
        ['student_id' => $student->id, 'semester_id' => $semester->id],
        ['level_id' => $student->level_id, 'status' => RegistrationStatus::Approved, 'total_units' => 16],
    );

    $grading = new GradingService;
    foreach (Course::whereIn('code', ['CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112'])->get() as $course) {
        $registered = $registration->registeredCourses()->firstOrCreate(
            ['course_id' => $course->id],
            ['credit_units' => $course->credit_units],
        );
        $grading->grade($registered, $ca, $exam)->update(['status' => ResultStatus::SenateApproved]);
    }

    app(ResultComputationService::class)->computeSemester($student->fresh(), $semester);
}

function paySchoolFees(Student $student): void
{
    $generator = app(InvoiceGenerator::class);
    $invoice = $generator->generateFor($student, AcademicSession::current());
    $payment = $invoice->payments()->create([
        'student_id' => $student->id,
        'gateway' => 'bank',
        'reference' => 'TEST-'.uniqid(),
        'amount' => $invoice->total,
        'status' => 'successful',
        'paid_at' => now(),
    ]);
    $generator->applyPayment($payment);
}

function moveToFinalYear(Student $student): Student
{
    $student->update(['level_id' => Level::where('code', '400')->firstOrFail()->id]);

    return $student->fresh(['level', 'programme']);
}
