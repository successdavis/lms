<?php

namespace App\Http\Controllers\Applicant;

use App\Enums\ApplicantStatus;
use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Institution;
use App\Models\Payment;
use App\Models\Programme;
use App\Services\Admissions\AdmissionService;
use App\Services\Finance\Gateways\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                ->with(['programme:id,name,code', 'admittedProgramme:id,name,code', 'admissionList:id,name,published_at', 'documents'])
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
            'applicationFee' => $cycle ? (float) $cycle->application_fee : 0,
            'applicationFeePaid' => $applicant?->hasPaidApplicationFee() ?? false,
            'documentTypes' => ApplicantDocument::TYPES,
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

        if (! $applicant->hasPaidApplicationFee()) {
            return back()->withErrors(['application' => 'Pay the application fee before submitting.']);
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

    public function payFee(Request $request, GatewayManager $gateways): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $applicant = $this->ownApplicant($request);

        if ($applicant->hasPaidApplicationFee()) {
            return back()->withErrors(['application' => 'The application fee has already been paid.']);
        }

        $payment = Payment::create([
            'applicant_id' => $applicant->id,
            'gateway' => $gateways->defaultGateway(),
            'reference' => 'APPFEE-'.Str::upper(Str::random(12)),
            'amount' => $applicant->cycle->application_fee,
            'status' => PaymentStatus::Pending,
        ]);

        $redirectUrl = $gateways->driver($payment->gateway)->initialize($payment);

        if ($redirectUrl !== null) {
            return Inertia::location($redirectUrl);
        }

        return back()->with('success', 'Application fee paid.');
    }

    public function verifyFee(Request $request, Payment $payment, GatewayManager $gateways): RedirectResponse
    {
        $applicant = $this->ownApplicant($request);

        abort_unless($payment->applicant_id === $applicant->id, 403);

        $succeeded = $gateways->driver($payment->gateway)->verify($payment);

        return redirect()
            ->route('applicant.show')
            ->with($succeeded ? 'success' : 'error', $succeeded ? 'Application fee confirmed.' : 'Payment was not successful.');
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $applicant = $this->ownApplicant($request);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ApplicantDocument::TYPES))],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        // Replace an earlier unverified upload of the same type.
        $existing = $applicant->documents()
            ->where('type', $validated['type'])
            ->whereNot('status', DocumentStatus::Verified)
            ->first();

        if ($existing !== null) {
            Storage::disk('local')->delete($existing->path);
            $existing->delete();
        }

        $path = $request->file('file')->store("applicant-documents/{$applicant->id}", 'local');

        $applicant->documents()->create([
            'type' => $validated['type'],
            'path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return back()->with('success', 'Document uploaded for verification.');
    }

    public function downloadDocument(Request $request, ApplicantDocument $document): StreamedResponse
    {
        $applicant = $this->ownApplicant($request);

        abort_unless($document->applicant_id === $applicant->id, 403);

        return Storage::disk('local')->download($document->path, $document->original_name);
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
