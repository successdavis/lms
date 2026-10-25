<?php

use App\Enums\ClearanceItemStatus;
use App\Enums\ClearanceUnit;
use App\Enums\StudentStatus;
use App\Models\ClearanceItem;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');
    $this->student = Student::firstOrFail();
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $this->bursar = User::where('email', 'bursar@demo.edu.ng')->firstOrFail();
});

function item(Student $student, ClearanceUnit $unit): ClearanceItem
{
    return $student->clearanceRequest->items()->where('unit', $unit)->firstOrFail();
}

function hodOf(Department $department): User
{
    $user = User::factory()->create();
    $user->assignRole('hod');
    Staff::create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'staff_no' => 'HOD-'.$department->code,
        'type' => 'academic',
        'designation' => 'Professor',
    ]);

    return $user;
}

it('opens clearance only in the final year', function () {
    $this->actingAs($this->student->user)->post('/student/clearance')->assertSessionHasErrors('clearance');

    $student = moveToFinalYear($this->student);
    $this->actingAs($student->user)->post('/student/clearance')->assertSessionHasNoErrors();

    expect($student->clearanceRequest()->firstOrFail()->items()->count())->toBe(count(ClearanceUnit::cases()));
});

it('blocks the bursary from clearing a student with an outstanding balance', function () {
    $student = moveToFinalYear($this->student);
    $this->actingAs($student->user)->post('/student/fees/generate'); // unpaid invoice
    $this->actingAs($student->user)->post('/student/clearance');

    $bursary = item($student->fresh(), ClearanceUnit::Bursary);

    $this->actingAs($this->bursar)
        ->patch("/clearance/items/{$bursary->id}", ['action' => 'clear'])
        ->assertSessionHasErrors('clearance');
    expect($bursary->fresh()->status)->toBe(ClearanceItemStatus::Pending);

    $invoice = $student->invoices()->firstOrFail();
    $this->actingAs($student->user)->post("/student/fees/{$invoice->id}/pay");

    $this->actingAs($this->bursar)
        ->patch("/clearance/items/{$bursary->id}", ['action' => 'clear'])
        ->assertSessionHasNoErrors();
    expect($bursary->fresh()->status)->toBe(ClearanceItemStatus::Cleared);
});

it('scopes HOD sign-off to the student\'s own department', function () {
    $student = moveToFinalYear($this->student);
    $this->actingAs($student->user)->post('/student/clearance');
    $departmentItem = item($student->fresh(), ClearanceUnit::Department);

    $otherHod = hodOf(Department::where('code', 'GST')->firstOrFail());
    $this->actingAs($otherHod)
        ->patch("/clearance/items/{$departmentItem->id}", ['action' => 'clear'])
        ->assertSessionHasErrors('clearance');

    $ownHod = hodOf($student->programme->department);
    $this->actingAs($ownHod)->get('/clearance')->assertInertia(fn ($page) => $page->has('items', 1));
    $this->actingAs($ownHod)
        ->patch("/clearance/items/{$departmentItem->id}", ['action' => 'clear'])
        ->assertSessionHasNoErrors();
});

it('lets a rejected unit be resubmitted, and completes when every unit clears', function () {
    $student = moveToFinalYear($this->student);
    $this->actingAs($student->user)->post('/student/clearance');
    $library = item($student->fresh(), ClearanceUnit::Library);

    $this->actingAs($this->admin)
        ->patch("/clearance/items/{$library->id}", ['action' => 'reject', 'note' => 'Return 2 overdue books'])
        ->assertSessionHasNoErrors();
    expect($library->fresh()->status)->toBe(ClearanceItemStatus::Rejected);

    $this->actingAs($student->user)->post('/student/clearance/resubmit');
    expect($library->fresh()->status)->toBe(ClearanceItemStatus::Pending);

    foreach ($student->fresh()->clearanceRequest->items as $clearanceItem) {
        $this->actingAs($this->admin)->patch("/clearance/items/{$clearanceItem->id}", ['action' => 'clear']);
    }

    $request = $student->fresh()->clearanceRequest;
    expect($request->status)->toBe('cleared')->and($request->cleared_at)->not->toBeNull();
});

it('graduates cleared students and exports the NYSC mobilization list', function () {
    $student = moveToFinalYear($this->student);
    $student->update(['date_of_birth' => '2003-05-01']);
    giveApprovedSemester($student);
    $this->actingAs($student->user)->post('/student/clearance');
    foreach ($student->fresh()->clearanceRequest->items as $clearanceItem) {
        $this->actingAs($this->admin)->patch("/clearance/items/{$clearanceItem->id}", ['action' => 'clear']);
    }

    $this->actingAs($this->admin)
        ->post('/records/graduation', ['student_ids' => [$student->id], 'graduated_on' => '2025-10-15'])
        ->assertSessionHasNoErrors();

    $student->refresh();
    expect($student->status)->toBe(StudentStatus::Graduated)
        ->and($student->graduated_on->toDateString())->toBe('2025-10-15');

    $csv = $this->actingAs($this->admin)->get('/records/graduation/nysc?batch=2026+Batch+A');
    $csv->assertOk();
    $body = $csv->streamedContent();

    expect($body)->toContain('Matric No')
        ->toContain($student->matric_no)
        ->toContain('Mobilize')            // 22 at graduation
        ->toContain('First Class Honours');
    expect($student->fresh()->nysc_batch)->toBe('2026 Batch A');
});

it('refuses to graduate a student whose clearance is incomplete', function () {
    $student = moveToFinalYear($this->student);
    $this->actingAs($student->user)->post('/student/clearance');

    $this->actingAs($this->admin)
        ->post('/records/graduation', ['student_ids' => [$student->id], 'graduated_on' => '2025-10-15'])
        ->assertSessionHasErrors();

    expect($student->fresh()->status)->not->toBe(StudentStatus::Graduated);
});
