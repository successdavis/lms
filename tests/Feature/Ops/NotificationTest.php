<?php

use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\Course;
use App\Models\RegisteredCourse;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AdmissionOffered;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\ClearanceUpdated;
use App\Notifications\Matriculated;
use App\Notifications\PaymentReceived;
use App\Notifications\ResultsPublished;
use App\Services\Academics\GradingService;
use App\Services\Messaging\SmsSender;
use Database\Seeders\DemoSeeder;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

require_once __DIR__.'/../Phase4/helpers.php';

beforeEach(function () {
    $this->seed([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class, DemoSeeder::class]);
    $this->travelTo('2025-10-20 10:00:00');
    $this->student = Student::firstOrFail();
    $this->admin = User::where('email', 'admin@demo.edu.ng')->firstOrFail();
    $this->applicant = Applicant::firstOrFail();
});

it('sends a payment receipt whenever a payment succeeds, for students and applicants', function () {
    Notification::fake();

    $this->actingAs($this->student->user)->post('/student/fees/generate');
    $invoice = $this->student->invoices()->firstOrFail();
    $this->actingAs($this->student->user)->post("/student/fees/{$invoice->id}/pay");

    Notification::assertSentTo($this->student->user, PaymentReceived::class, fn ($n) => str_contains($n->title, '155,000.00'));

    $this->applicant->update(['status' => 'draft']);
    $this->actingAs($this->applicant->user)->post('/apply/fees/pay');

    Notification::assertSentTo($this->applicant->user, PaymentReceived::class, fn ($n) => $n->url === '/apply');
});

it('announces results only on final approval', function () {
    Notification::fake();
    paySchoolFees($this->student);
    $this->actingAs($this->student->user)->post('/student/registration', [
        'course_ids' => Course::whereIn('code', ['CSC 101', 'MTH 101', 'PHY 101', 'STA 111', 'GST 111', 'GST 112'])->pluck('id')->all(),
    ]);
    $course = Course::where('code', 'CSC 101')->firstOrFail();
    $registered = RegisteredCourse::where('course_id', $course->id)->firstOrFail();
    (new GradingService)->grade($registered, 25, 50);

    $this->actingAs($this->admin)->post("/staff/approvals/{$course->id}", ['stage' => 'hod']);
    $this->actingAs($this->admin)->post("/staff/approvals/{$course->id}", ['stage' => 'faculty']);
    Notification::assertNotSentTo($this->student->user, ResultsPublished::class);

    $this->actingAs($this->admin)->post("/staff/approvals/{$course->id}", ['stage' => 'senate']);
    Notification::assertSentTo($this->student->user, ResultsPublished::class, fn ($n) => str_contains($n->title, 'CSC 101'));
});

it('tells admitted applicants when their list is published, then welcomes them on matriculation', function () {
    Notification::fake();

    $this->actingAs($this->admin)->patch("/admissions/applicants/{$this->applicant->id}/score", ['post_utme_score' => 70]);
    $this->actingAs($this->admin)->post('/admissions/screen');
    $this->actingAs($this->admin)->post('/admissions/lists', ['name' => 'Merit List']);
    $list = AdmissionCycle::current()->lists()->firstOrFail();
    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/admit", ['mode' => 'auto']);

    // Unpublished list: no offer notice yet.
    Notification::assertNotSentTo($this->applicant->user, AdmissionOffered::class);

    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/publish");
    Notification::assertSentTo($this->applicant->user, AdmissionOffered::class);

    $this->actingAs($this->applicant->user)->post('/apply/accept');
    $this->actingAs($this->admin)->post("/admissions/lists/{$list->id}/matriculate");
    Notification::assertSentTo($this->applicant->user, Matriculated::class, fn ($n) => str_contains($n->body, 'FUTD/25/CSC/0002'));
});

it('tells the student why a clearance unit rejected them', function () {
    Notification::fake();
    $student = moveToFinalYear($this->student);
    $this->actingAs($student->user)->post('/student/clearance');
    $library = $student->fresh()->clearanceRequest->items()->where('unit', 'library')->firstOrFail();

    $this->actingAs($this->admin)->patch("/clearance/items/{$library->id}", ['action' => 'reject', 'note' => 'Return 2 overdue books']);

    Notification::assertSentTo($student->user, ClearanceUpdated::class, fn ($n) => str_contains($n->body, 'Return 2 overdue books'));
});

it('delivers SMS through the configured sender in international format', function () {
    $sent = [];
    $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
    {
        public function __construct(private array &$sent) {}

        public function send(string $to, string $message): void
        {
            $this->sent[] = compact('to', 'message');
        }
    });

    $this->student->update(['phone' => '0803 123 4567']);
    $this->student->user->notify(new PaymentReceived('Paid', 'Body', '/student/fees', 'Payment of N5,000 received.'));

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['to'])->toBe('2348031234567')
        ->and($sent[0]['message'])->toContain('Payment of N5,000 received.');

    // Notices without SMS text, or users without a phone, send no SMS.
    $this->admin->notify(new PaymentReceived('Paid', 'Body', null, 'text'));
    expect($sent)->toHaveCount(1);

    config(['services.sms.enabled' => false]);
    expect((new PaymentReceived('a', 'b', null, 'c'))->via($this->student->user))->not->toContain(SmsChannel::class);
});

it('keeps an in-app inbox that marks notices read when opened', function () {
    $this->student->user->notify(new PaymentReceived('Payment received', 'We got it.', '/student/fees'));
    $this->student->user->notify(new PaymentReceived('Second', 'Another.', '/student/fees'));

    $this->actingAs($this->student->user)
        ->get('/notifications')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('notifications')
            ->has('notifications.data', 2)
            ->where('auth.unread_notifications', 2));

    $first = $this->student->user->notifications()->firstOrFail();
    $this->actingAs($this->student->user)->get("/notifications/{$first->id}")->assertRedirect('/student/fees');
    expect($this->student->user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->student->user)->post('/notifications/read-all');
    expect($this->student->user->unreadNotifications()->count())->toBe(0);

    // Someone else's notice is not reachable.
    $this->actingAs($this->admin)->get("/notifications/{$first->id}")->assertNotFound();
});
