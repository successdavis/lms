<?php

use App\Enums\ApplicantStatus;
use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\Programme;
use App\Models\Student;
use App\Models\User;
use App\Services\Admissions\AdmissionService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00'); // inside the application window
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $this->applicant = Applicant::firstOrFail(); // seeded, submitted, UTME 281
});

function freshApplicantUser(): User
{
    $user = User::factory()->create();

    return $user;
}

it('lets a new user start and submit an application', function () {
    $user = freshApplicantUser();
    $programme = Programme::firstOrFail();

    $this->actingAs($user)->post('/apply', [
        'programme_id' => $programme->id,
        'jamb_reg_no' => '202541000001AA',
        'utme_score' => 250,
        'gender' => 'male',
        'date_of_birth' => '2007-01-01',
        'phone' => '08020000000',
        'state_of_origin' => 'Lagos',
        'lga_of_origin' => 'Ikeja',
        'address' => '1 Allen Avenue, Ikeja',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($user->fresh()->hasRole('applicant'))->toBeTrue();

    $applicant = Applicant::where('user_id', $user->id)->firstOrFail();
    expect($applicant->status)->toBe(ApplicantStatus::Draft);

    // The cycle charges a N2,000 application fee: submission is gated on it.
    $this->actingAs($user)->post('/apply/submit')->assertSessionHasErrors('application');
    $this->actingAs($user)->post('/apply/fees/pay')->assertRedirect(); // demo gateway: instant success

    $this->actingAs($user)->post('/apply/submit')->assertRedirect()->assertSessionHasNoErrors();

    expect($applicant->fresh()->status)->toBe(ApplicantStatus::Submitted);

    // Submitted applications are locked against edits.
    $this->actingAs($user)->post('/apply', [
        'programme_id' => $programme->id,
        'jamb_reg_no' => 'CHANGED',
        'utme_score' => 399,
        'gender' => 'male',
        'date_of_birth' => '2007-01-01',
        'phone' => '08020000000',
        'state_of_origin' => 'Lagos',
        'lga_of_origin' => 'Ikeja',
        'address' => '1 Allen Avenue, Ikeja',
    ])->assertSessionHasErrors('application');
});

it('computes the screening aggregate with the cycle weights', function () {
    // UTME 281/400 x 60 + Post-UTME 70/100 x 40 = 42.15 + 28 = 70.15
    $this->actingAs($this->admin)
        ->patch("/admissions/applicants/{$this->applicant->id}/score", ['post_utme_score' => 70])
        ->assertRedirect();

    $this->actingAs($this->admin)->post('/admissions/screen')->assertRedirect();

    $applicant = $this->applicant->fresh();
    expect($applicant->status)->toBe(ApplicantStatus::Screened)
        ->and((float) $applicant->aggregate_score)->toBe(70.15);
});

it('blocks students and applicants from the admissions office', function () {
    $student = Student::firstOrFail();
    $this->actingAs($student->user)->get('/admissions/applicants')->assertForbidden();
    $this->actingAs($this->applicant->user)->get('/admissions/applicants')->assertForbidden();
});

it('admits screened applicants above the cutoff onto a list', function () {
    $this->actingAs($this->admin)
        ->patch("/admissions/applicants/{$this->applicant->id}/score", ['post_utme_score' => 70]);
    $this->actingAs($this->admin)->post('/admissions/screen');

    $this->actingAs($this->admin)->post('/admissions/lists', ['name' => 'Merit List'])->assertRedirect();
    $list = AdmissionCycle::current()->lists()->firstOrFail();

    $this->actingAs($this->admin)
        ->post("/admissions/lists/{$list->id}/admit", ['mode' => 'auto'])
        ->assertRedirect();

    $applicant = $this->applicant->fresh();
    expect($applicant->status)->toBe(ApplicantStatus::Admitted)
        ->and($applicant->admission_list_id)->toBe($list->id)
        ->and($applicant->admitted_programme_id)->toBe($applicant->programme_id);
});

it('skips screened applicants below the cutoff in auto mode', function () {
    // Post-UTME 10: aggregate = 42.15 + 4 = 46.15, below the 50 cutoff.
    $this->actingAs($this->admin)
        ->patch("/admissions/applicants/{$this->applicant->id}/score", ['post_utme_score' => 10]);
    $this->actingAs($this->admin)->post('/admissions/screen');

    $this->actingAs($this->admin)->post('/admissions/lists', ['name' => 'Merit List']);
    $list = AdmissionCycle::current()->lists()->firstOrFail();

    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/admit", ['mode' => 'auto']);

    expect($this->applicant->fresh()->status)->toBe(ApplicantStatus::Screened);

    // Supplementary admission with ignore_cutoff takes them in manually.
    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/admit", [
        'mode' => 'manual',
        'applicant_ids' => [$this->applicant->id],
        'ignore_cutoff' => true,
    ]);

    expect($this->applicant->fresh()->status)->toBe(ApplicantStatus::Admitted);
});

it('walks the full pipeline: accept offer, matriculate, becomes a student', function () {
    $this->actingAs($this->admin)
        ->patch("/admissions/applicants/{$this->applicant->id}/score", ['post_utme_score' => 70]);
    $this->actingAs($this->admin)->post('/admissions/screen');
    $this->actingAs($this->admin)->post('/admissions/lists', ['name' => 'Merit List']);
    $list = AdmissionCycle::current()->lists()->firstOrFail();
    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/admit", ['mode' => 'auto']);

    // Applicant accepts the offer and can print the admission letter.
    $this->actingAs($this->applicant->user)->post('/apply/accept')->assertRedirect()->assertSessionHasNoErrors();
    expect($this->applicant->fresh()->status)->toBe(ApplicantStatus::Accepted);

    $this->actingAs($this->applicant->user)
        ->get('/apply/print/admission-letter')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applicant/print/admission-letter')
            ->where('accepted', true));

    // Office matriculates the list.
    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/matriculate")->assertRedirect();

    $applicant = $this->applicant->fresh();
    expect($applicant->status)->toBe(ApplicantStatus::Matriculated)
        ->and($applicant->student_id)->not->toBeNull();

    $student = Student::find($applicant->student_id);
    // Demo student FUTD/25/CSC/0001 exists, so the next CSC student is 0002.
    expect($student->matric_no)->toBe('FUTD/25/CSC/0002')
        ->and($student->level_id)->toBe($student->programme->entry_level_id)
        ->and($student->jamb_reg_no)->toBe($applicant->jamb_reg_no);

    $user = $applicant->user->fresh();
    expect($user->hasRole('student'))->toBeTrue()
        ->and($user->hasRole('applicant'))->toBeFalse();

    // The new student lands on the student dashboard.
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('student.dashboard'));
});

it('refuses to matriculate before the offer is accepted', function () {
    $this->actingAs($this->admin)
        ->patch("/admissions/applicants/{$this->applicant->id}/score", ['post_utme_score' => 70]);
    $this->actingAs($this->admin)->post('/admissions/screen');
    $this->actingAs($this->admin)->post('/admissions/lists', ['name' => 'Merit List']);
    $list = AdmissionCycle::current()->lists()->firstOrFail();
    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/admit", ['mode' => 'auto']);

    expect(fn () => app(AdmissionService::class)->matriculate($this->applicant->fresh()))
        ->toThrow(RuntimeException::class);
});

it('closes applications outside the window', function () {
    $this->travelTo('2026-02-01 10:00:00'); // window closed 2025-12-31

    $user = freshApplicantUser();
    $this->actingAs($user)->post('/apply', [
        'programme_id' => Programme::firstOrFail()->id,
        'jamb_reg_no' => '202541000002BB',
        'utme_score' => 300,
        'gender' => 'female',
        'date_of_birth' => '2006-06-06',
        'phone' => '08010000000',
        'state_of_origin' => 'Kano',
        'lga_of_origin' => 'Dala',
        'address' => '2 Zoo Road, Kano',
    ])->assertSessionHasErrors('application');
});
