<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\RegisteredCourse;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\GradingService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->student = Student::firstOrFail();
    $this->lecturerUser = User::where('email', 'lecturer@demo.edu.ng')->firstOrFail();
    $this->adminUser = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $this->travelTo('2025-10-20 10:00:00');

    // Pay fees and register all first-semester courses through the portal.
    $this->actingAs($this->student->user)->post('/student/fees/generate');
    $invoice = $this->student->invoices()->firstOrFail();
    $this->actingAs($this->student->user)->post("/student/fees/{$invoice->id}/pay");
    $this->actingAs($this->student->user)->post('/student/registration', [
        'course_ids' => Course::whereIn('code', [
            'CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112',
        ])->pluck('id')->all(),
    ]);
});

function enterScores($test, string $courseCode, float $ca, float $exam): Course
{
    $course = Course::where('code', $courseCode)->firstOrFail();
    $registered = RegisteredCourse::where('course_id', $course->id)->firstOrFail();

    $test->actingAs($test->lecturerUser)
        ->post("/lecturer/courses/{$course->id}/scores", [
            'scores' => [[
                'registered_course_id' => $registered->id,
                'ca_score' => $ca,
                'exam_score' => $exam,
            ]],
        ])
        ->assertRedirect();

    return $course;
}

it('lets an allocated lecturer enter scores', function () {
    enterScores($this, 'CSC 101', 25, 50);

    $result = Result::firstOrFail();
    expect((float) $result->total_score)->toBe(75.0)
        ->and($result->grade_letter)->toBe('A')
        ->and($result->status)->toBe(ResultStatus::Pending);
});

it('rejects score entry for a course the lecturer is not allocated to', function () {
    $course = Course::where('code', 'PHY 101')->firstOrFail(); // not allocated to demo lecturer
    $registered = RegisteredCourse::where('course_id', $course->id)->firstOrFail();

    $this->actingAs($this->lecturerUser)
        ->post("/lecturer/courses/{$course->id}/scores", [
            'scores' => [[
                'registered_course_id' => $registered->id,
                'ca_score' => 20,
                'exam_score' => 40,
            ]],
        ])
        ->assertForbidden();
});

it('walks a result through HOD, faculty and senate approval and snapshots the GPA', function () {
    // Grade every registered course (admin holds super-admin, can approve each stage).
    foreach (['CSC 101', 'MTH 101'] as $code) {
        enterScores($this, $code, 25, 50); // 75 -> A
    }

    // The demo lecturer is only allocated 2 courses; grade the rest directly.
    $grading = new GradingService;
    foreach (['PHY 101', 'STA 111', 'GST 111', 'GST 112'] as $code) {
        $course = Course::where('code', $code)->firstOrFail();
        $registered = RegisteredCourse::where('course_id', $course->id)->firstOrFail();
        $grading->grade($registered, 20, 40); // 60 -> B
    }

    foreach (Course::whereIn('code', ['CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112'])->get() as $course) {
        foreach (['hod', 'faculty', 'senate'] as $stage) {
            $this->actingAs($this->adminUser)
                ->post("/staff/approvals/{$course->id}", ['stage' => $stage])
                ->assertRedirect();
        }
    }

    expect(Result::where('status', ResultStatus::SenateApproved)->count())->toBe(6);

    // GPA snapshot: CSC101+MTH101 A (5x3x2=30 QP), others B (4 points):
    // PHY 3u + STA 3u = 24 QP, GST 2u x2 = 16 QP -> total 70 QP / 16 units = 4.37
    $snapshot = $this->student->semesterResults()->first();
    expect($snapshot)->not->toBeNull()
        ->and((float) $snapshot->gpa)->toBe(4.37);
});

it('locks approved results against lecturer edits', function () {
    $course = enterScores($this, 'CSC 101', 25, 50);
    $registered = RegisteredCourse::where('course_id', $course->id)->firstOrFail();

    foreach (['hod', 'faculty', 'senate'] as $stage) {
        $this->actingAs($this->adminUser)->post("/staff/approvals/{$course->id}", ['stage' => $stage]);
    }

    $this->actingAs($this->lecturerUser)
        ->post("/lecturer/courses/{$course->id}/scores", [
            'scores' => [[
                'registered_course_id' => $registered->id,
                'ca_score' => 10,
                'exam_score' => 20,
            ]],
        ])
        ->assertSessionHasErrors();

    expect((float) Result::firstOrFail()->total_score)->toBe(75.0);
});

it('hides results from the student until senate approval', function () {
    enterScores($this, 'CSC 101', 25, 50);

    $this->actingAs($this->student->user)
        ->get('/student/results')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('student/results')
            ->has('semesters', 0));

    $course = Course::where('code', 'CSC 101')->firstOrFail();
    foreach (['hod', 'faculty', 'senate'] as $stage) {
        $this->actingAs($this->adminUser)->post("/staff/approvals/{$course->id}", ['stage' => $stage]);
    }

    $this->actingAs($this->student->user)
        ->get('/student/results')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('student/results')
            ->has('semesters', 1));
});
