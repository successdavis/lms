<?php

use App\Models\Applicant;
use App\Models\Hostel;
use App\Models\Invoice;
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
    $this->student = Student::firstOrFail();
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
});

it('gives the registry every section with live figures', function () {
    paySchoolFees($this->student);
    $paid = (int) Invoice::firstOrFail()->total; // whole naira; JSON drops the .0

    $hall = Hostel::where('gender', 'male')->firstOrFail();
    $this->actingAs($this->student->user)->post("/student/hostel/{$hall->id}")->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('academics.activeStudents', 1)
            ->where('finance.invoices', 1)
            ->where('finance.fullyPaid', 1)
            ->where('finance.collected', $paid)
            ->where('finance.outstanding', 0)
            ->where('finance.received', $paid)
            ->where('finance.daily', false)
            ->has('finance.byPeriod', 6)
            ->where('finance.byPeriod.0.label', 'May 2025')
            ->where('finance.byPeriod.5.label', 'Oct 2025')
            ->where('finance.byPeriod.5.value', $paid)
            ->where('finance.byPeriod.4.value', 0)
            ->where('hostels', fn ($hostels) => collect($hostels)->firstWhere('name', $hall->name)['occupied'] === 1
                && collect($hostels)->firstWhere('name', $hall->name)['confirmed'] === 0)
            ->has('admissions.funnel', 5));
});

it('counts the admissions funnel as reached-or-beyond', function () {
    Applicant::query()->update(['status' => 'admitted']);
    $admitted = Applicant::count();

    $this->actingAs($this->admin)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('admissions.funnel.0', ['label' => 'Submitted', 'value' => $admitted])
            ->where('admissions.funnel.1.value', $admitted)
            ->where('admissions.funnel.2', ['label' => 'Offered admission', 'value' => $admitted])
            ->where('admissions.funnel.3.value', 0)
            ->where('admissions.funnel.4.value', 0));
});

it('shows the result pipeline and degree classes', function () {
    giveApprovedSemester($this->student, 25, 50); // 75 = A in all six courses

    $this->actingAs($this->admin)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('academics.registered', 1)
            ->where('academics.pipeline.0', ['label' => 'Awaiting HOD', 'value' => 0])
            ->where('academics.pipeline.3', ['label' => 'Released', 'value' => 6])
            ->where('academics.classes', [['label' => 'First Class Honours', 'value' => 1]]));
});

it('scopes each section to the roles that own it', function (string $email, array $visible) {
    $user = User::where('email', $email)->firstOrFail();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(function (AssertableInertia $page) use ($visible) {
            foreach (['academics', 'finance', 'admissions', 'hostels'] as $section) {
                in_array($section, $visible, true)
                    ? $page->whereNot($section, null)
                    : $page->where($section, null);
            }
        });
})->with([
    'bursar' => ['bursar@demo.edu.ng', ['finance']],
    'student affairs' => ['studentaffairs@demo.edu.ng', ['hostels']],
    'librarian' => ['librarian@demo.edu.ng', []],
]);

it('sends students, lecturers and HR officers to their own dashboards', function () {
    $this->actingAs($this->student->user)->get('/dashboard')->assertRedirect(route('student.dashboard'));
    $this->actingAs(User::where('email', 'lecturer@demo.edu.ng')->firstOrFail())->get('/dashboard')->assertRedirect(route('lecturer.dashboard'));
    $this->actingAs(User::where('email', 'hr@demo.edu.ng')->firstOrFail())->get('/dashboard')->assertRedirect(route('hr.dashboard'));
});
