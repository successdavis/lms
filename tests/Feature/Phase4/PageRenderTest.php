<?php

use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');
});

it('renders every Phase 4 page for its role', function (string $email, string $url, string $component) {
    $student = Student::firstOrFail();
    giveApprovedSemester($student); // so record-driven pages have data

    $this->actingAs(User::where('email', $email)->firstOrFail())
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
})->with([
    'student transcripts' => ['student@demo.edu.ng', '/student/transcripts', 'student/transcripts'],
    'student clearance' => ['student@demo.edu.ng', '/student/clearance', 'student/clearance'],
    'student hostel' => ['student@demo.edu.ng', '/student/hostel', 'student/hostel'],
    'student siwes' => ['student@demo.edu.ng', '/student/siwes', 'student/siwes'],
    'records transcripts' => ['admin@demo.edu.ng', '/records/transcripts', 'records/transcripts'],
    'records graduation' => ['admin@demo.edu.ng', '/records/graduation', 'records/graduation'],
    'clearance queue' => ['librarian@demo.edu.ng', '/clearance', 'clearance/queue'],
    'hostel admin' => ['studentaffairs@demo.edu.ng', '/hostels', 'hostels/admin'],
    'siwes placements' => ['siwes@demo.edu.ng', '/siwes/placements', 'siwes/placements'],
    'staff leave' => ['lecturer@demo.edu.ng', '/staff/leave', 'staff/leave'],
    'leave approvals' => ['admin@demo.edu.ng', '/staff/leave/approvals', 'staff/leave-approvals'],
]);

it('shows the new office sections in the sidebar props', function () {
    $this->actingAs(User::where('email', 'lecturer@demo.edu.ng')->firstOrFail())
        ->get('/staff/leave')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.is_staff', true));
});
