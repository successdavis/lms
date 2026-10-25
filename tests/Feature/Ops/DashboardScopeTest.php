<?php

use App\Enums\StaffType;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Programme;
use App\Models\Promotion;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/../Phase4/helpers.php';

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');

    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $this->cscStudent = Student::firstOrFail();
    $this->csc = Department::where('code', 'CSC')->firstOrFail();
    $this->gst = Department::where('code', 'GST')->firstOrFail();

    // A second faculty with one student, outside the demo HOD/Dean's reach.
    $this->science = Faculty::create(['code' => 'FSC', 'name' => 'Faculty of Science']);
    $this->physics = Department::create(['faculty_id' => $this->science->id, 'code' => 'PHY', 'name' => 'Physics']);
    $cscProgramme = Programme::where('code', 'BSC-CSC')->firstOrFail();
    $programme = $cscProgramme->replicate()->fill(['code' => 'BSC-PHY', 'name' => 'B.Sc Physics', 'department_id' => $this->physics->id]);
    $programme->save();

    $user = User::factory()->create();
    $user->assignRole('student');
    $this->phyStudent = Student::create([
        'user_id' => $user->id,
        'programme_id' => $programme->id,
        'level_id' => $this->cscStudent->level_id,
        'entry_session_id' => AcademicSession::current()->id,
        'entry_mode' => 'utme',
        'gender' => 'female',
    ]);
});

function staffWithRole(string $role, ?Department $department): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Staff::create([
        'user_id' => $user->id,
        'department_id' => $department?->id,
        'staff_no' => 'T/'.$user->id,
        'type' => StaffType::Academic,
        'designation' => 'Professor',
    ]);

    return $user->fresh();
}

it('holds an HOD to their own department', function () {
    $hod = staffWithRole('hod', $this->csc);

    // Asking for another department changes nothing.
    $this->actingAs($hod)->get('/dashboard?department_id='.$this->physics->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.label', 'Computer Science')
            ->where('scope.locked', true)
            ->where('options.faculties', [])
            ->where('academics.activeStudents', 1)
            ->where('academics.byDepartment', [])
            ->where('finance', null));
});

it('counts an HOD\'s pipeline by the department offering each course', function () {
    giveApprovedSemester($this->cscStudent); // 4 CSC-department courses + 2 GST

    $this->actingAs(staffWithRole('hod', $this->csc))->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('academics.pipeline.3', ['label' => 'Released', 'value' => 4]));

    $this->actingAs(staffWithRole('dean', $this->csc))->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('academics.pipeline.3', ['label' => 'Released', 'value' => 6]));
});

it('holds a Dean to their faculty and lets them pick one of its departments', function () {
    $dean = staffWithRole('dean', $this->csc);
    $sict = $this->csc->faculty->name;

    $this->actingAs($dean)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.label', $sict)
            ->where('scope.faculty_locked', true)
            ->has('options.faculties', 1)
            ->where('options.faculties.0.name', $sict)
            ->where('academics.activeStudents', 1)
            ->where('academics.byDepartment', [['label' => 'Computer Science', 'value' => 1]]));

    $this->actingAs($dean)->get('/dashboard?department_id='.$this->gst->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.label', 'General Studies')
            ->where('academics.activeStudents', 0));

    // A department in another faculty falls back to the whole faculty.
    $this->actingAs($dean)->get('/dashboard?department_id='.$this->physics->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.label', $sict)
            ->where('academics.activeStudents', 1));
});

it('shows nothing to an HOD with no department on record', function () {
    $this->actingAs(staffWithRole('hod', null))->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.label', 'No department on your staff record')
            ->where('academics.activeStudents', 0));
});

it('lets the registry narrow every section to a faculty or department', function () {
    paySchoolFees($this->phyStudent);
    $paid = (int) Invoice::where('student_id', $this->phyStudent->id)->firstOrFail()->total;

    $this->actingAs($this->admin)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.institution', true)
            ->has('options.faculties', 2)
            ->where('academics.activeStudents', 2)
            ->has('academics.byDepartment', 2)
            ->where('finance.collected', $paid));

    $this->actingAs($this->admin)->get('/dashboard?faculty_id='.$this->csc->faculty_id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('academics.activeStudents', 1)
            ->where('finance.collected', 0)
            ->where('finance.received', 0));

    $this->actingAs($this->admin)->get('/dashboard?department_id='.$this->physics->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.label', 'Physics')
            ->where('academics.activeStudents', 1)
            ->where('finance.collected', $paid)
            ->where('finance.received', $paid));
});

it('filters by session and semester', function () {
    $next = AcademicSession::create(['name' => '2026/2027', 'starts_on' => '2026-10-01', 'ends_on' => '2027-09-30']);
    $first = $next->semesters()->create(['name' => 'First Semester', 'number' => 1]);
    $second = $next->semesters()->create(['name' => 'Second Semester', 'number' => 2]);
    paySchoolFees($this->cscStudent);

    $this->actingAs($this->admin)->get('/dashboard?session_id='.$next->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session', '2026/2027')
            ->where('filters.semester_id', $first->id)
            ->where('finance.invoices', 0)
            ->where('admissions', null)
            ->where('options.sessions.0.name', '2026/2027'));

    $this->actingAs($this->admin)->get("/dashboard?session_id={$next->id}&semester_id={$second->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('semester', 'Second Semester'));

    // A semester from another session is ignored.
    $this->actingAs($this->admin)->get("/dashboard?semester_id={$second->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('session', AcademicSession::current()->name)
            ->whereNot('filters.semester_id', $second->id));
});

it('charts payments by day for short ranges and caps long ones', function () {
    paySchoolFees($this->cscStudent);
    $paid = (int) Invoice::firstOrFail()->total;

    $this->actingAs($this->admin)->get('/dashboard?from=2025-10-01&to=2025-10-20')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('finance.daily', true)
            ->has('finance.byPeriod', 20)
            ->where('finance.byPeriod.19', ['label' => '20 Oct', 'value' => $paid])
            ->where('range', '1 Oct 2025 – 20 Oct 2025'));

    // Payments outside the range are left out.
    $this->actingAs($this->admin)->get('/dashboard?from=2025-01-01&to=2025-09-30')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('finance.received', 0));

    $this->actingAs($this->admin)->get('/dashboard?from=2015-01-01&to=2025-10-20')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('finance.byPeriod', 24));

    // Reversed or malformed dates fall back sensibly.
    $this->actingAs($this->admin)->get('/dashboard?from=2025-10-20&to=2025-10-01')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.from', '2025-10-01')->where('filters.to', '2025-10-20'));
    $this->actingAs($this->admin)->get('/dashboard?from=yesterday')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('finance.byPeriod', 6));
});

it('exports the overview as CSV and PDF with the same scope', function () {
    paySchoolFees($this->cscStudent);

    $csv = $this->actingAs($this->admin)->get('/dashboard?format=csv&department_id='.$this->csc->id);
    $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $body = $csv->streamedContent();

    expect($body)->toContain('"Institution overview"')
        ->toContain('Scope,"Computer Science"')
        ->toContain('"Students by level"')
        ->toContain('"Payments received by month"')
        ->toContain('"Hostel occupancy"');

    $pdf = $this->actingAs($this->admin)->get('/dashboard?format=pdf');
    $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF')
        ->and(AuditLog::where('event', 'dashboard.exported')->count())->toBe(2);

    // An HOD's export carries their department, whatever they ask for.
    $hodCsv = $this->actingAs(staffWithRole('hod', $this->csc))->get('/dashboard?format=csv&department_id='.$this->physics->id)->streamedContent();
    expect($hodCsv)->toContain('Scope,"Computer Science"')->not->toContain('Finance');
});

it('gives lecturers a dashboard of their own courses', function () {
    $lecturer = User::where('email', 'lecturer@demo.edu.ng')->firstOrFail();

    $this->actingAs($lecturer)->get('/lecturer/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('lecturer/dashboard')
            ->where('totals', ['courses' => 2, 'registered' => 0, 'scored' => 0, 'released' => 0])
            ->where('courses.0.code', 'CSC 101')
            ->where('courses.0.stage', 'No students registered'));

    giveApprovedSemester($this->cscStudent, 25, 50); // 75 = A, senate-approved

    $this->actingAs($lecturer)->get('/lecturer/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('totals', ['courses' => 2, 'registered' => 2, 'scored' => 2, 'released' => 2])
            ->where('courses.0.average', 75)
            ->where('courses.0.passRate', 100)
            ->where('courses.0.stage', 'Released')
            ->where('pipeline.3', ['label' => 'Released', 'value' => 2])
            ->where('grades', [['label' => 'A', 'value' => 2]]));

    $csv = $this->actingAs($lecturer)->get('/lecturer/dashboard?format=csv')->streamedContent();
    expect($csv)->toContain('"Teaching dashboard"')->toContain('"CSC 101"')->toContain('"Grade distribution"');

    $this->actingAs($lecturer)->get('/lecturer/dashboard?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

    // Another semester has no allocations.
    $second = AcademicSession::current()->semesters()->where('number', 2)->firstOrFail();
    $this->actingAs($lecturer)->get('/lecturer/dashboard?semester_id='.$second->id)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('totals.courses', 0)->where('semester.name', $second->name));
});

it('keeps the teaching dashboard to teaching staff', function () {
    $this->actingAs($this->cscStudent->user)->get('/lecturer/dashboard')->assertForbidden();

    $noRecord = User::factory()->create();
    $noRecord->assignRole('lecturer');
    $this->actingAs($noRecord)->get('/lecturer/dashboard')->assertForbidden();
});

it('gives HR an establishment dashboard', function () {
    $hr = User::where('email', 'hr@demo.edu.ng')->firstOrFail();
    $lecturer = Staff::where('staff_no', 'FUTD/AC/0001')->firstOrFail(); // Senior Lecturer since Oct 2021

    LeaveRequest::create([
        'staff_id' => Staff::where('staff_no', 'FUTD/NA/0001')->firstOrFail()->id,
        'type' => 'annual', 'starts_on' => '2025-10-15', 'ends_on' => '2025-10-21', 'days' => 5, 'status' => 'approved',
    ]);

    $this->actingAs($hr)->get('/hr/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('hr/dashboard')
            ->where('totals.active', Staff::where('is_active', true)->count())
            ->where('totals.academic', 1)
            ->where('totals.payrollReady', 1)
            ->where('totals.onLeaveToday', 1)
            ->where('totals.pendingPromotions', 0)
            ->where('totals.due', 1)
            ->where('due.0.staff_no', 'FUTD/AC/0001')
            ->where('due.0.next', 'Reader')
            ->where('byRank', [['label' => 'Senior Lecturer', 'value' => 1]])
            ->where('leaveByType', [['label' => 'Annual', 'value' => 5]]));

    // An open case takes the lecturer off the "due" list.
    Promotion::create([
        'staff_id' => $lecturer->id, 'from_designation' => 'Senior Lecturer', 'to_designation' => 'Reader',
        'from_grade_level' => 5, 'to_grade_level' => 6, 'effective_on' => '2025-10-01',
        'recommended_by_id' => $hr->id, 'status' => 'pending',
    ]);

    $this->actingAs($hr)->get('/hr/dashboard?department_id='.$this->csc->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope.label', 'Computer Science')
            ->where('totals.active', 1)
            ->where('totals.pendingPromotions', 1)
            ->where('totals.due', 0)
            ->where('byDepartment', []));

    $csv = $this->actingAs($hr)->get('/hr/dashboard?format=csv')->streamedContent();
    expect($csv)->toContain('"HR dashboard"')->toContain('"Staff by grade level"')->toContain('"Due for promotion"');
    $this->actingAs($hr)->get('/hr/dashboard?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs(User::where('email', 'lecturer@demo.edu.ng')->firstOrFail())->get('/hr/dashboard')->assertForbidden();
});
