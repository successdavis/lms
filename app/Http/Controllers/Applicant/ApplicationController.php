<?php

namespace App\Http\Controllers\Applicant;

use App\Enums\ApplicantStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\Institution;
use App\Models\Programme;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApplicationController extends Controller
{
    /**
     * The applicant's single page: the application form while drafting, a
     * status tracker once submitted, and the offer actions when admitted.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Students and staff have no business on the application form.
        if ($user->hasAnyRole(['student', 'lecturer', 'hod', 'dean', 'registrar', 'bursar', 'exam-officer', 'admission-officer', 'super-admin'])) {
            return redirect()->route('dashboard');
        }

        $cycle = AdmissionCycle::current();

        $applicant = $cycle
            ? Applicant::query()
                ->where('user_id', $user->id)
                ->where('admission_cycle_id', $cycle->id)
                ->with(['programme:id,name,code', 'admittedProgramme:id,name,code', 'admissionList:id,name,published_at'])
                ->first()
            : null;

        return Inertia::render('applicant/application', [
            'cycle' => $cycle ? [
                'session' => $cycle->academicSession->name,
                'open' => $cycle->isOpen(),
                'closes_at' => $cycle->closes_at?->toDayDateTimeString(),
            ] : null,
            'applicant' => $applicant,
            'programmes' => Programme::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, AdmissionService $admissions): RedirectResponse
    {
        $cycle = AdmissionCycle::current();

        if ($cycle === null || ! $cycle->isOpen()) {
            return back()->withErrors(['application' => 'Applications are not open at the moment.']);
        }

        $validated = $request->validate([
            'programme_id' => ['required', 'exists:programmes,id'],
            'jamb_reg_no' => ['required', 'string', 'max:20'],
            'utme_score' => ['required', 'integer', 'min:0', 'max:400'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:20'],
            'state_of_origin' => ['required', 'string', 'max:50'],
            'lga_of_origin' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'o_level_results' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        if (! $user->hasAnyRole(['applicant'])) {
            $user->assignRole('applicant');
        }

        $applicant = Applicant::query()
            ->where('user_id', $user->id)
            ->where('admission_cycle_id', $cycle->id)
            ->first();

        if ($applicant !== null && $applicant->status !== ApplicantStatus::Draft) {
            return back()->withErrors(['application' => 'Your application has been submitted and can no longer be edited.']);
        }

        Applicant::updateOrCreate(
            ['user_id' => $user->id, 'admission_cycle_id' => $cycle->id],
            $validated + [
                'application_no' => $applicant->application_no ?? $admissions->nextApplicationNumber($cycle->id),
            ],
        );

        return back()->with('success', 'Application saved as draft.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $applicant = $this->ownApplicant($request);

        if ($applicant->status !== ApplicantStatus::Draft) {
            return back()->withErrors(['application' => 'This application was already submitted.']);
        }

        if (! $applicant->cycle->isOpen()) {
            return back()->withErrors(['application' => 'The application window has closed.']);
        }

        $applicant->update(['status' => ApplicantStatus::Submitted, 'submitted_at' => now()]);

        return back()->with('success', "Application {$applicant->application_no} submitted. Await screening.");
    }

    public function accept(Request $request, AdmissionService $admissions): RedirectResponse
    {
        $applicant = $this->ownApplicant($request);

        try {
            $admissions->accept($applicant);
        } catch (RuntimeException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('success', 'Offer accepted. You will be matriculated by the admissions office.');
    }

    public function admissionLetter(Request $request): Response
    {
        $applicant = $this->ownApplicant($request);

        abort_unless(in_array($applicant->status, [
            ApplicantStatus::Admitted, ApplicantStatus::Accepted, ApplicantStatus::Matriculated,
        ], true), 403, 'No admission offer to print.');

        $programme = $applicant->admittedProgramme ?? $applicant->programme;

        return Inertia::render('applicant/print/admission-letter', [
            'institution' => Institution::current()?->only('name', 'short_name', 'motto', 'address'),
            'applicant' => [
                'name' => $applicant->user->name,
                'application_no' => $applicant->application_no,
                'jamb_reg_no' => $applicant->jamb_reg_no,
            ],
            'programme' => $programme->only('name', 'code', 'award'),
            'department' => $programme->department->name,
            'faculty' => $programme->department->faculty->name,
            'session' => $applicant->cycle->academicSession->name,
            'list' => $applicant->admissionList?->name,
            'admitted_on' => $applicant->admitted_at?->toFormattedDateString(),
            'accepted' => $applicant->status !== ApplicantStatus::Admitted,
        ]);
    }

    private function ownApplicant(Request $request): Applicant
    {
        return Applicant::query()
            ->where('user_id', $request->user()->id)
            ->with(['cycle.academicSession', 'programme.department.faculty', 'admittedProgramme.department.faculty', 'admissionList'])
            ->latest('id')
            ->firstOrFail();
    }
}
