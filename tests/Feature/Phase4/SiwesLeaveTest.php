<?php

use App\Enums\SiwesStatus;
use App\Models\LeaveRequest;
use App\Models\SiwesPlacement;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00'); // a Monday
    $this->student = Student::firstOrFail();
    $this->coordinator = User::where('email', 'siwes@demo.edu.ng')->firstOrFail();
    $this->lecturer = User::where('email', 'lecturer@demo.edu.ng')->firstOrFail();
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
});

function placement(array $overrides = []): array
{
    return $overrides + [
        'employer_name' => 'Nigerian National Petroleum Company',
        'employer_address' => 'NNPC Towers, Central Business District, Abuja',
        'industry_supervisor_name' => 'Engr. Chika Nwosu',
        'industry_supervisor_phone' => '08031234567',
        'starts_on' => '2025-11-03',
        'ends_on' => '2026-03-02', // ~17 weeks
    ];
}

it('rejects a SIWES placement shorter than the minimum duration', function () {
    $this->actingAs($this->student->user)
        ->post('/student/siwes', placement(['ends_on' => '2025-12-01']))
        ->assertSessionHasErrors('ends_on');
});

it('approves then grades a placement only after it ends', function () {
    $this->actingAs($this->student->user)->post('/student/siwes', placement())->assertSessionHasNoErrors();
    $placement = SiwesPlacement::firstOrFail();

    $this->actingAs($this->coordinator)
        ->patch("/siwes/placements/{$placement->id}", ['action' => 'approve'])
        ->assertSessionHasNoErrors();
    expect($placement->fresh()->status)->toBe(SiwesStatus::Approved);

    // Approved placements are locked against student edits.
    $this->actingAs($this->student->user)
        ->post('/student/siwes', placement(['employer_name' => 'Changed Ltd']))
        ->assertSessionHasErrors('siwes');

    $scores = ['logbook_score' => 30, 'report_score' => 25, 'visit_score' => 15];
    $this->actingAs($this->coordinator)
        ->patch("/siwes/placements/{$placement->id}/grade", $scores)
        ->assertSessionHasErrors('siwes'); // still on attachment

    $this->travelTo('2026-03-10 09:00:00');
    $this->actingAs($this->coordinator)
        ->patch("/siwes/placements/{$placement->id}/grade", $scores)
        ->assertSessionHasNoErrors();

    $placement->refresh();
    expect($placement->status)->toBe(SiwesStatus::Graded)
        ->and((float) $placement->total_score)->toBe(70.0)
        ->and($placement->is_passed)->toBeTrue();
});

it('counts only working days and enforces the annual entitlement', function () {
    // Wed 22 Oct to Tue 28 Oct 2025 = 5 working days.
    $this->actingAs($this->lecturer)
        ->post('/staff/leave', ['type' => 'annual', 'starts_on' => '2025-10-22', 'ends_on' => '2025-10-28'])
        ->assertSessionHasNoErrors();
    expect(LeaveRequest::firstOrFail()->days)->toBe(5);

    // 26 more working days would exceed the 30-day entitlement (5 already committed).
    $this->actingAs($this->lecturer)
        ->post('/staff/leave', ['type' => 'annual', 'starts_on' => '2025-11-03', 'ends_on' => '2025-12-08'])
        ->assertSessionHasErrors('ends_on');

    // Sick leave is not drawn from the annual entitlement.
    $this->actingAs($this->lecturer)
        ->post('/staff/leave', ['type' => 'sick', 'starts_on' => '2025-11-03', 'ends_on' => '2025-12-08'])
        ->assertSessionHasNoErrors();
});

it('routes leave approval to the department HOD or registry, never the requester', function () {
    $this->actingAs($this->lecturer)
        ->post('/staff/leave', ['type' => 'casual', 'starts_on' => '2025-10-23', 'ends_on' => '2025-10-24']);
    $leave = LeaveRequest::firstOrFail();

    $lecturerStaff = Staff::where('user_id', $this->lecturer->id)->firstOrFail();

    // An HOD of another department cannot decide it.
    $otherHod = User::factory()->create();
    $otherHod->assignRole('hod');
    Staff::create(['user_id' => $otherHod->id, 'department_id' => null, 'staff_no' => 'HOD-X', 'type' => 'academic']);
    $this->actingAs($otherHod)->patch("/staff/leave/{$leave->id}", ['action' => 'approve'])->assertForbidden();

    // The lecturer's own HOD can.
    $ownHod = User::factory()->create();
    $ownHod->assignRole('hod');
    Staff::create(['user_id' => $ownHod->id, 'department_id' => $lecturerStaff->department_id, 'staff_no' => 'HOD-CSC', 'type' => 'academic']);
    $this->actingAs($ownHod)->get('/staff/leave/approvals')->assertInertia(fn ($page) => $page->has('requests', 1));
    $this->actingAs($ownHod)->patch("/staff/leave/{$leave->id}", ['action' => 'approve'])->assertSessionHasNoErrors();

    expect($leave->fresh()->status)->toBe('approved')
        ->and($leave->fresh()->reviewed_by_id)->toBe($ownHod->id);
});

it('keeps students off the staff leave pages', function () {
    $this->actingAs($this->student->user)->get('/staff/leave')->assertNotFound();
    $this->actingAs($this->student->user)->get('/siwes/placements')->assertForbidden();
});
