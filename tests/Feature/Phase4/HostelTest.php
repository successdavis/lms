<?php

use App\Enums\AllocationStatus;
use App\Enums\InvoicePurpose;
use App\Enums\InvoiceStatus;
use App\Models\AcademicSession;
use App\Models\Hostel;
use App\Models\HostelAllocation;
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
    $this->student = Student::firstOrFail(); // male
    $this->maleHall = Hostel::where('gender', 'male')->firstOrFail();
    $this->femaleHall = Hostel::where('gender', 'female')->firstOrFail();
});

function anotherMaleStudent(Student $like): Student
{
    $user = User::factory()->create();
    $user->assignRole('student');

    return Student::create([
        'user_id' => $user->id,
        'programme_id' => $like->programme_id,
        'level_id' => $like->level_id,
        'entry_session_id' => AcademicSession::current()->id,
        'entry_mode' => 'utme',
        'gender' => 'male',
    ]);
}

it('requires school fees to be paid before booking', function () {
    $this->actingAs($this->student->user)
        ->post("/student/hostel/{$this->maleHall->id}")
        ->assertSessionHasErrors('hostel');
});

it('refuses a hostel for the other gender', function () {
    paySchoolFees($this->student);

    $this->actingAs($this->student->user)
        ->post("/student/hostel/{$this->femaleHall->id}")
        ->assertSessionHasErrors('hostel');
});

it('reserves a bed, raises a hostel invoice, and confirms it on payment', function () {
    paySchoolFees($this->student);

    $this->actingAs($this->student->user)
        ->post("/student/hostel/{$this->maleHall->id}")
        ->assertSessionHasNoErrors();

    $allocation = HostelAllocation::firstOrFail();
    expect($allocation->status)->toBe(AllocationStatus::Reserved)
        ->and($allocation->invoice->purpose)->toBe(InvoicePurpose::Hostel)
        ->and((float) $allocation->invoice->total)->toBe(35000.0);

    $this->actingAs($this->student->user)->post("/student/fees/{$allocation->invoice_id}/pay");

    expect($allocation->fresh()->status)->toBe(AllocationStatus::Confirmed)
        ->and($allocation->fresh()->expires_at)->toBeNull();

    // A second booking in the same session is refused.
    $this->actingAs($this->student->user)
        ->post("/student/hostel/{$this->maleHall->id}")
        ->assertSessionHasErrors('hostel');
});

it('fills rooms in order and reports a full hostel', function () {
    $tiny = Hostel::create(['name' => 'Annex', 'gender' => 'male', 'fee' => 10000]);
    $tiny->rooms()->create(['number' => 'X1', 'capacity' => 1]);

    paySchoolFees($this->student);
    $this->actingAs($this->student->user)->post("/student/hostel/{$tiny->id}")->assertSessionHasNoErrors();

    $second = anotherMaleStudent($this->student);
    paySchoolFees($second);
    $this->actingAs($second->user)->post("/student/hostel/{$tiny->id}")->assertSessionHasErrors('hostel');
});

it('releases lapsed reservations and refuses payment on their invoices', function () {
    $tiny = Hostel::create(['name' => 'Annex', 'gender' => 'male', 'fee' => 10000]);
    $tiny->rooms()->create(['number' => 'X1', 'capacity' => 1]);

    paySchoolFees($this->student);
    $this->actingAs($this->student->user)->post("/student/hostel/{$tiny->id}");
    $lapsed = HostelAllocation::firstOrFail();

    $this->travel(config('lms.hostel.reservation_hours') + 1)->hours();

    // Someone else can now take the freed bed.
    $second = anotherMaleStudent($this->student);
    paySchoolFees($second);
    $this->actingAs($second->user)->post("/student/hostel/{$tiny->id}")->assertSessionHasNoErrors();

    expect($lapsed->fresh()->status)->toBe(AllocationStatus::Cancelled)
        ->and($lapsed->invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);

    $this->actingAs($this->student->user)
        ->post("/student/fees/{$lapsed->invoice_id}/pay")
        ->assertSessionHasErrors('fees');
});

it('lets student affairs add rooms and see occupancy', function () {
    $affairs = User::where('email', 'studentaffairs@demo.edu.ng')->firstOrFail();

    $this->actingAs($affairs)
        ->post("/hostels/{$this->maleHall->id}/rooms", ['prefix' => 'B', 'from' => 1, 'to' => 5, 'capacity' => 6])
        ->assertSessionHasNoErrors();

    expect($this->maleHall->rooms()->where('number', 'like', 'B%')->count())->toBe(5);

    $this->actingAs($affairs)->get('/hostels')->assertOk();
    $this->actingAs($this->student->user)->get('/hostels')->assertForbidden();
});
