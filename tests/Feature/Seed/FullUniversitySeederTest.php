<?php

use App\Enums\ApplicantStatus;
use App\Enums\ResultStatus;
use App\Models\Applicant;
use App\Models\Course;
use App\Models\Result;
use App\Models\Semester;
use App\Models\SemesterResult;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\ResultComputationService;
use App\Services\Admissions\AdmissionService;
use Database\Seeders\FullUniversitySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Late in a second semester: results in flight, next admissions open.
    $this->travelTo('2026-10-25 10:00:00');
    Storage::fake('local'); // the seeder writes applicants' credential scans
    $this->seed(FullUniversitySeeder::class);
});

it('builds a decade-old university with every module populated', function () {
    expect(Student::whereIn('status', ['active', 'probation', 'spillover'])->count())->toBeGreaterThanOrEqual(300)
        ->and(Student::where('status', 'graduated')->count())->toBeGreaterThan(300)
        ->and(DB::table('academic_sessions')->count())->toBe(13)
        ->and(DB::table('academic_sessions')->where('is_current', true)->value('name'))->toBe('2025/2026')
        ->and(Semester::current()->number)->toBe(2);

    foreach ([
        'staff', 'course_allocations', 'results', 'semester_results', 'invoices', 'payments', 'fee_structures',
        'applicants', 'applicant_documents', 'admission_lists', 'transcript_requests', 'clearance_requests',
        'hostel_allocations', 'siwes_placements', 'promotions', 'leave_requests', 'notifications', 'audit_logs',
    ] as $table) {
        expect(DB::table($table)->count())->toBeGreaterThan(0, "{$table} is empty");
    }

    // Current-semester results sit at every stage of the approval chain.
    expect(Result::query()->distinct()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(['faculty_approved', 'hod_approved', 'pending', 'senate_approved']);

    // Every demo login exists with its role.
    foreach ([
        'admin@demo.edu.ng' => 'super-admin', 'registrar@demo.edu.ng' => 'registrar', 'bursar@demo.edu.ng' => 'bursar',
        'hod@demo.edu.ng' => 'hod', 'dean@demo.edu.ng' => 'dean', 'lecturer@demo.edu.ng' => 'lecturer',
        'student@demo.edu.ng' => 'student', 'applicant@demo.edu.ng' => 'applicant', 'hr@demo.edu.ng' => 'hr-officer',
        'examofficer@demo.edu.ng' => 'exam-officer', 'admissions@demo.edu.ng' => 'admission-officer',
        'librarian@demo.edu.ng' => 'librarian', 'studentaffairs@demo.edu.ng' => 'student-affairs', 'siwes@demo.edu.ng' => 'siwes-coordinator',
    ] as $email => $role) {
        expect(User::where('email', $email)->firstOrFail()->hasRole($role))->toBeTrue("{$email} lacks {$role}");
    }
});

it('stores GPA snapshots the app itself would compute', function () {
    $computer = app(ResultComputationService::class);

    foreach (SemesterResult::query()->inRandomOrder()->limit(60)->get() as $snapshot) {
        DB::beginTransaction();
        $fresh = $computer->computeSemester($snapshot->student, $snapshot->semester);
        DB::rollBack();

        expect((float) $fresh->gpa)->toBe((float) $snapshot->gpa)
            ->and((float) $fresh->cgpa)->toBe((float) $snapshot->cgpa)
            ->and($fresh->standing)->toBe($snapshot->standing);
    }
});

it('lets the registry carry a course through to Senate on the seeded data', function () {
    $admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $semester = Semester::current();
    $course = Course::where('code', 'CSC 102')->firstOrFail(); // fully scored, awaiting HOD

    foreach (['hod', 'faculty', 'senate'] as $stage) {
        $this->actingAs($admin)->post("/staff/approvals/{$course->id}", ['stage' => $stage])->assertSessionHasNoErrors();
    }

    $released = Result::query()
        ->whereHas('registeredCourse', fn ($q) => $q->where('course_id', $course->id)
            ->whereHas('courseRegistration', fn ($r) => $r->where('semester_id', $semester->id)))
        ->get();
    expect($released)->not->toBeEmpty()
        ->and($released->every(fn (Result $r) => $r->status === ResultStatus::SenateApproved))->toBeTrue();
});

it('matriculates an accepted applicant without clashing with seeded numbers', function () {
    $applicant = Applicant::where('status', ApplicantStatus::Accepted)->firstOrFail();

    $student = app(AdmissionService::class)->matriculate($applicant);

    expect($student->matric_no)->toEndWith('/0001')
        ->and($student->matric_no)->toContain('/26/');
});

it('refuses to seed over existing data', function () {
    $this->seed(FullUniversitySeeder::class);
})->throws(RuntimeException::class, 'expects an empty database');
