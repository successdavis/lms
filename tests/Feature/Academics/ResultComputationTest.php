<?php

use App\Enums\AcademicStanding;
use App\Enums\RegistrationStatus;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\GradeScale;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Academics\GradingService;
use App\Services\Academics\ResultComputationService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->grading = new GradingService();
    $this->computer = new ResultComputationService();
    $this->student = Student::firstOrFail();
    $this->semester = Semester::where('number', 1)->firstOrFail();
});

/**
 * Register and grade courses for the student, bypassing registration rules.
 *
 * @param  array<string, array{float, float}>  $scores  course code => [ca, exam]
 */
function gradeCourses(Student $student, Semester $semester, array $scores, GradingService $grading): CourseRegistration
{
    $registration = CourseRegistration::updateOrCreate(
        ['student_id' => $student->id, 'semester_id' => $semester->id],
        ['level_id' => $student->level_id, 'status' => RegistrationStatus::Approved, 'total_units' => 0],
    );

    foreach ($scores as $code => [$ca, $exam]) {
        $course = Course::where('code', $code)->firstOrFail();
        $registered = $registration->registeredCourses()->firstOrCreate(
            ['course_id' => $course->id],
            ['credit_units' => $course->credit_units],
        );
        $grading->grade($registered, $ca, $exam);
    }

    return $registration;
}

it('computes the NUC worked example: GPA 4.46 on 13 units', function () {
    // Mirrors the standard worked example: 58 quality points over 13 units.
    gradeCourses($this->student, $this->semester, [
        'CSC 101' => [25, 53], // 78 A -> 15 QP (3 units)
        'MTH 101' => [20, 44], // 64 B -> 12 QP (3 units)
        'PHY 101' => [21, 50], // 71 A -> 15 QP (3 units)
        'GST 111' => [28, 52], // 80 A -> 10 QP (2 units)
        'GST 112' => [15, 40], // 55 C ->  6 QP (2 units)
    ], $this->grading);

    $snapshot = $this->computer->computeSemester($this->student, $this->semester);

    expect($snapshot->total_units)->toBe(13)
        ->and((float) $snapshot->total_quality_points)->toBe(58.0)
        ->and((float) $snapshot->gpa)->toBe(4.46)
        ->and((float) $snapshot->cgpa)->toBe(4.46)
        ->and($snapshot->standing)->toBe(AcademicStanding::Good);
});

it('accumulates CGPA across semesters from running totals', function () {
    gradeCourses($this->student, $this->semester, [
        'CSC 101' => [25, 53],
        'MTH 101' => [20, 44],
        'PHY 101' => [21, 50],
        'GST 111' => [28, 52],
        'GST 112' => [15, 40],
    ], $this->grading);
    $this->computer->computeSemester($this->student, $this->semester);

    $second = Semester::where('number', 2)->firstOrFail();
    gradeCourses($this->student, $second, [
        'CSC 102' => [20, 42], // 62 B -> 12 QP (3 units)
        'MTH 102' => [10, 32], // 42 E ->  3 QP (3 units)
        'GST 121' => [22, 48], // 70 A -> 10 QP (2 units)
    ], $this->grading);

    $snapshot = $this->computer->computeSemester($this->student, $second);

    // Semester 2: 25 QP / 8 units = 3.12 (truncated).
    // CGPA: (58 + 25) / (13 + 8) = 83/21 = 3.952... -> 3.95.
    expect((float) $snapshot->gpa)->toBe(3.12)
        ->and($snapshot->cumulative_units)->toBe(21)
        ->and((float) $snapshot->cgpa)->toBe(3.95);
});

it('includes failed units in the divisor (F drags the GPA)', function () {
    gradeCourses($this->student, $this->semester, [
        'CSC 101' => [10, 20], // 30 F -> 0 QP, but 3 units still count
        'MTH 101' => [25, 53], // 78 A -> 15 QP
        'PHY 101' => [25, 53], // 78 A -> 15 QP
        'GST 111' => [25, 53], // 78 A -> 10 QP
        'GST 112' => [25, 53], // 78 A -> 10 QP
    ], $this->grading);

    $snapshot = $this->computer->computeSemester($this->student, $this->semester);

    // 50 QP / 13 units (including the failed 3) = 3.84
    expect((float) $snapshot->gpa)->toBe(3.84);
});

it('classifies CGPA per NUC bands including the 2.40 lower bound of 2:2', function (float $cgpa, string $class) {
    $scale = GradeScale::where('slug', 'nuc-5-point')->firstOrFail();

    expect($this->computer->classify($scale, $cgpa)?->name)->toBe($class);
})->with([
    'First Class' => [4.50, 'First Class Honours'],
    '2:1 upper edge' => [4.49, 'Second Class Honours (Upper Division)'],
    '2:2 at exactly 2.40' => [2.40, 'Second Class Honours (Lower Division)'],
    'Third below 2.40' => [2.39, 'Third Class Honours'],
    'Pass' => [1.00, 'Pass'],
]);

it('classifies polytechnic CGPA per NBTE bands', function (float $cgpa, string $class) {
    $scale = GradeScale::where('slug', 'nbte-4-point')->firstOrFail();

    expect($this->computer->classify($scale, $cgpa)?->name)->toBe($class);
})->with([
    'Distinction' => [3.50, 'Distinction'],
    'Upper Credit' => [3.00, 'Upper Credit'],
    'Lower Credit' => [2.50, 'Lower Credit'],
    'Pass' => [2.00, 'Pass'],
]);

it('applies university probation and withdrawal thresholds', function () {
    $nuc = GradeScale::where('slug', 'nuc-5-point')->firstOrFail();

    expect($this->computer->determineStanding($nuc, 1.50, null))->toBe(AcademicStanding::Good)
        ->and($this->computer->determineStanding($nuc, 1.20, null))->toBe(AcademicStanding::Probation)
        ->and($this->computer->determineStanding($nuc, 0.90, null))->toBe(AcademicStanding::Withdrawal)
        ->and($this->computer->determineStanding($nuc, 1.20, AcademicStanding::Probation))->toBe(AcademicStanding::Withdrawal);
});

it('applies NBTE two-consecutive-semesters withdrawal rule', function () {
    $nbte = GradeScale::where('slug', 'nbte-4-point')->firstOrFail();

    expect($this->computer->determineStanding($nbte, 2.00, null))->toBe(AcademicStanding::Good)
        ->and($this->computer->determineStanding($nbte, 1.90, null))->toBe(AcademicStanding::Probation)
        // No hard withdrawal CGPA: first bad semester is probation however low.
        ->and($this->computer->determineStanding($nbte, 0.50, null))->toBe(AcademicStanding::Probation)
        ->and($this->computer->determineStanding($nbte, 1.90, AcademicStanding::Probation))->toBe(AcademicStanding::Withdrawal);
});
