<?php

use App\Enums\InvoiceStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->student = Student::firstOrFail();
    $this->studentUser = $this->student->user;
    $this->travelTo('2025-10-20 10:00:00'); // inside the registration window
});

it('blocks non-students from the student portal', function () {
    $admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();

    $this->actingAs($admin)->get('/student/dashboard')->assertForbidden();
});

it('renders the student dashboard', function () {
    $this->actingAs($this->studentUser)
        ->get('/student/dashboard')
        ->assertOk();
});

it('generates an invoice and pays via the demo gateway', function () {
    $this->actingAs($this->studentUser)
        ->post('/student/fees/generate')
        ->assertRedirect();

    $invoice = $this->student->invoices()->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and((float) $invoice->total)->toBe(155000.0); // school 95k + acceptance 50k + ICT 10k

    $this->actingAs($this->studentUser)
        ->post("/student/fees/{$invoice->id}/pay")
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('registers courses through the portal once fees are paid', function () {
    $this->actingAs($this->studentUser)->post('/student/fees/generate');
    $invoice = $this->student->invoices()->firstOrFail();
    $this->actingAs($this->studentUser)->post("/student/fees/{$invoice->id}/pay");

    $courseIds = Course::whereIn('code', [
        'CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112',
    ])->pluck('id')->all();

    $this->actingAs($this->studentUser)
        ->post('/student/registration', ['course_ids' => $courseIds])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->student->courseRegistrations()->count())->toBe(1);
});

it('rejects registration with unpaid fees and shows the error', function () {
    $this->actingAs($this->studentUser)->post('/student/fees/generate');

    $courseIds = Course::whereIn('code', [
        'CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112',
    ])->pluck('id')->all();

    $this->actingAs($this->studentUser)
        ->post('/student/registration', ['course_ids' => $courseIds])
        ->assertSessionHasErrors('registration');
});

it('prevents a student from paying another student\'s invoice', function () {
    $this->actingAs($this->studentUser)->post('/student/fees/generate');
    $invoice = $this->student->invoices()->firstOrFail();

    // Second student
    $otherUser = User::factory()->create();
    $otherUser->assignRole('student');
    Student::create([
        'user_id' => $otherUser->id,
        'programme_id' => $this->student->programme_id,
        'level_id' => $this->student->level_id,
        'entry_session_id' => AcademicSession::current()->id,
        'entry_mode' => 'utme',
    ]);

    $this->actingAs($otherUser)
        ->post("/student/fees/{$invoice->id}/pay")
        ->assertForbidden();
});
