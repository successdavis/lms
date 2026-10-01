<?php

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\RegistrationException;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Academics\GradingService;
use App\Services\Academics\RegistrationService;
use App\Services\Finance\InvoiceGenerator;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->service = new RegistrationService();
    $this->student = Student::firstOrFail();
    $this->semester = Semester::where('number', 1)->firstOrFail();
    // Inside the demo semester's registration window.
    $this->travelTo('2025-10-20 10:00:00');

    $this->firstSemesterCourseIds = Course::whereIn('code', [
        'CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112',
    ])->pluck('id')->all(); // 16 units
});

function payAllInvoices(Student $student): void
{
    $generator = new InvoiceGenerator();
    $invoice = $generator->generateFor($student, AcademicSession::current());

    $payment = $invoice->payments()->create([
        'student_id' => $student->id,
        'gateway' => PaymentGateway::Paystack,
        'reference' => 'TEST-'.uniqid(),
        'amount' => $invoice->total,
        'status' => PaymentStatus::Successful,
        'paid_at' => now(),
    ]);

    $generator->applyPayment($payment);
}

it('rejects registration outside the registration window', function () {
    $this->travelTo('2026-01-15 10:00:00'); // past late-registration close

    $this->service->register($this->student, $this->semester, $this->firstSemesterCourseIds);
})->throws(RegistrationException::class, 'not open');

it('blocks registration until registration-blocking fees are paid', function () {
    $generator = new InvoiceGenerator();
    $generator->generateFor($this->student, AcademicSession::current()); // unpaid invoice

    $this->service->register($this->student, $this->semester, $this->firstSemesterCourseIds);
})->throws(RegistrationException::class, 'paid before registration');

it('registers courses once fees are cleared', function () {
    payAllInvoices($this->student);

    $registration = $this->service->register($this->student, $this->semester, $this->firstSemesterCourseIds);

    expect($registration->status)->toBe(RegistrationStatus::Submitted)
        ->and($registration->total_units)->toBe(16)
        ->and($registration->registeredCourses)->toHaveCount(6);
});

it('rejects a unit load below the programme minimum', function () {
    payAllInvoices($this->student);
    $few = Course::whereIn('code', ['CSC 101', 'MTH 101'])->pluck('id')->all(); // 6 units

    $this->service->register($this->student, $this->semester, $few);
})->throws(RegistrationException::class, 'below the minimum');

it('forces outstanding carryovers to be registered first', function () {
    payAllInvoices($this->student);

    // Fail CSC 101 in a previous session's first semester.
    $previousSession = AcademicSession::create(['name' => '2024/2025']);
    $previousSemester = $previousSession->semesters()->create([
        'number' => 1, 'name' => 'First Semester',
    ]);
    $registration = CourseRegistration::create([
        'student_id' => $this->student->id,
        'semester_id' => $previousSemester->id,
        'level_id' => $this->student->level_id,
        'status' => RegistrationStatus::Approved,
    ]);
    $csc101 = Course::where('code', 'CSC 101')->firstOrFail();
    $registered = $registration->registeredCourses()->create([
        'course_id' => $csc101->id,
        'credit_units' => $csc101->credit_units,
    ]);
    (new GradingService())->grade($registered, 10, 20); // 30 => F

    $withoutCarryover = Course::whereIn('code', [
        'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112',
    ])->pluck('id')->all();

    $this->service->register($this->student, $this->semester, $withoutCarryover);
})->throws(RegistrationException::class, 'Carryover courses must be registered first: CSC 101');

it('flags carryover line items when the failed course is included', function () {
    payAllInvoices($this->student);

    $previousSession = AcademicSession::create(['name' => '2024/2025']);
    $previousSemester = $previousSession->semesters()->create([
        'number' => 1, 'name' => 'First Semester',
    ]);
    $registration = CourseRegistration::create([
        'student_id' => $this->student->id,
        'semester_id' => $previousSemester->id,
        'level_id' => $this->student->level_id,
        'status' => RegistrationStatus::Approved,
    ]);
    $csc101 = Course::where('code', 'CSC 101')->firstOrFail();
    $registered = $registration->registeredCourses()->create([
        'course_id' => $csc101->id,
        'credit_units' => $csc101->credit_units,
    ]);
    (new GradingService())->grade($registered, 10, 20); // F

    $new = $this->service->register($this->student, $this->semester, $this->firstSemesterCourseIds);

    $line = $new->registeredCourses->firstWhere('course_id', $csc101->id);
    expect($line->is_carryover)->toBeTrue();
});
