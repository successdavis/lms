<?php

use App\Enums\InstitutionType;
use App\Events\StaffPromoted;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Promotion;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\PromotionDecided;
use App\Support\StaffRanks;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');
    $this->hr = User::where('email', 'hr@demo.edu.ng')->firstOrFail();
    $this->registrar = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $this->lecturer = Staff::where('staff_no', 'FUTD/AC/0001')->firstOrFail(); // Senior Lecturer since 2021-10-01
});

function hodFor(Department $department): User
{
    $user = User::factory()->create();
    $user->assignRole('hod');
    Staff::create(['user_id' => $user->id, 'department_id' => $department->id, 'staff_no' => 'HOD-'.$department->code, 'type' => 'academic', 'designation' => 'Professor']);

    return $user;
}

it('promotes up the ladder with a second approver, applying rank, grade level and step', function () {
    Event::fake([StaffPromoted::class]);
    Notification::fake();

    $this->actingAs($this->hr)
        ->post('/hr/promotions', ['staff_id' => $this->lecturer->id, 'effective_on' => '2025-10-01'])
        ->assertSessionHasNoErrors();

    $promotion = Promotion::firstOrFail();
    expect($promotion->to_designation)->toBe('Reader')->and($promotion->to_grade_level)->toBe(6);

    // The recommender cannot approve their own recommendation.
    $this->actingAs($this->hr)
        ->patch("/hr/promotions/{$promotion->id}", ['action' => 'approve'])
        ->assertSessionHasErrors('promotion');

    $this->actingAs($this->registrar)
        ->patch("/hr/promotions/{$promotion->id}", ['action' => 'approve'])
        ->assertSessionHasNoErrors();

    $this->lecturer->refresh();
    expect($this->lecturer->designation)->toBe('Reader')
        ->and($this->lecturer->grade_level)->toBe(6)
        ->and($this->lecturer->step)->toBe(1)
        ->and($this->lecturer->rank_since->toDateString())->toBe('2025-10-01');

    Event::assertDispatched(StaffPromoted::class, fn ($e) => $e->promotion->id === $promotion->id);
    Notification::assertSentTo($this->lecturer->user, PromotionDecided::class);
});

it('enforces the minimum years in rank', function () {
    $this->lecturer->update(['rank_since' => '2024-01-01']);

    $this->actingAs($this->hr)
        ->post('/hr/promotions', ['staff_id' => $this->lecturer->id, 'effective_on' => '2025-10-01'])
        ->assertSessionHasErrors('promotion');
});

it('refuses a second pending promotion and promotion past the top rank', function () {
    $this->actingAs($this->hr)->post('/hr/promotions', ['staff_id' => $this->lecturer->id, 'effective_on' => '2025-10-01']);
    $this->actingAs($this->hr)
        ->post('/hr/promotions', ['staff_id' => $this->lecturer->id, 'effective_on' => '2025-10-01'])
        ->assertSessionHasErrors('promotion');

    Promotion::query()->delete();
    $this->lecturer->update(['designation' => 'Professor']);
    $this->actingAs($this->hr)
        ->post('/hr/promotions', ['staff_id' => $this->lecturer->id, 'effective_on' => '2025-10-01'])
        ->assertSessionHasErrors('promotion');
});

it('lets an HOD recommend only their own department, and never decide', function () {
    $otherHod = hodFor(Department::where('code', 'GST')->firstOrFail());
    $this->actingAs($otherHod)
        ->post('/hr/promotions', ['staff_id' => $this->lecturer->id, 'effective_on' => '2025-10-01'])
        ->assertSessionHasErrors('promotion');

    $ownHod = hodFor($this->lecturer->department);
    $this->actingAs($ownHod)
        ->post('/hr/promotions', ['staff_id' => $this->lecturer->id, 'effective_on' => '2025-10-01'])
        ->assertSessionHasNoErrors();

    $promotion = Promotion::firstOrFail();
    $this->actingAs($ownHod)
        ->patch("/hr/promotions/{$promotion->id}", ['action' => 'approve'])
        ->assertSessionHasErrors('promotion');

    $this->actingAs($ownHod)->get('/hr/staff')->assertForbidden();
});

it('applies the annual step increment, capped at the top step', function () {
    $this->lecturer->update(['step' => config('lms.hr.max_step')]);
    $other = Staff::where('staff_no', 'FUTD/NA/0001')->firstOrFail();
    $other->update(['grade_level' => 8, 'step' => 2]);

    $this->actingAs($this->hr)->post('/hr/payroll/step-increment')->assertSessionHasNoErrors();

    expect($this->lecturer->fresh()->step)->toBe(config('lms.hr.max_step'))
        ->and($other->fresh()->step)->toBe(3);
});

it('exports payroll for active staff and audits the export', function () {
    $csv = $this->actingAs($this->hr)->get('/hr/payroll/export');
    $csv->assertOk();
    $body = $csv->streamedContent();

    expect($body)->toContain('IPPIS No')->toContain('IPP120045')->toContain('3012345678');
    expect(AuditLog::where('event', 'payroll.exported')->where('user_id', $this->hr->id)->exists())->toBeTrue();

    $this->actingAs($this->lecturer->user)->get('/hr/payroll/export')->assertForbidden();
});

it('validates NUBAN account numbers on the staff register', function () {
    $this->actingAs($this->hr)
        ->patch("/hr/staff/{$this->lecturer->id}", ['account_number' => '12345'])
        ->assertSessionHasErrors('account_number');
});

it('uses the CONPCASS ladder for polytechnics', function () {
    expect(StaffRanks::next(InstitutionType::Polytechnic, 'Senior Lecturer'))->toBe('Principal Lecturer')
        ->and(StaffRanks::next(InstitutionType::Polytechnic, 'Chief Lecturer'))->toBeNull()
        ->and(StaffRanks::next(InstitutionType::University, 'Senior Lecturer'))->toBe('Reader');
});

it('renders the HR pages', function () {
    $this->actingAs($this->hr)->get('/hr/staff')->assertOk()->assertInertia(fn ($page) => $page->component('hr/staff'));
    $this->actingAs($this->hr)->get('/hr/promotions')->assertOk()->assertInertia(fn ($page) => $page->component('hr/promotions')->where('canDecide', true));
});
