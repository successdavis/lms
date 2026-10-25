<?php

namespace App\Http\Controllers\Admissions;

use App\Enums\ApplicantStatus;
use App\Enums\CapsStatus;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Programme;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantController extends Controller
{
    public function index(Request $request): Response
    {
        $cycle = AdmissionCycle::current();

        $filters = [
            'status' => $request->string('status')->toString(),
            'programme' => $request->integer('programme') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $applicants = $cycle
            ? Applicant::query()
                ->where('admission_cycle_id', $cycle->id)
                ->with(['user:id,name,email', 'programme:id,code', 'admissionList:id,name'])
                ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
                ->when($filters['programme'], fn ($q, $programme) => $q->where('programme_id', $programme))
                ->when($filters['search'], function ($q, $search) {
                    $q->where(function ($q) use ($search) {
                        $q->where('application_no', 'like', "%{$search}%")
                            ->orWhere('jamb_reg_no', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('aggregate_score')
                ->paginate(25)
                ->withQueryString()
            : null;

        return Inertia::render('admissions/applicants', [
            'cycle' => $cycle ? [
                'id' => $cycle->id,
                'session' => $cycle->academicSession->name,
                'utme_weight' => $cycle->utme_weight,
                'post_utme_weight' => $cycle->post_utme_weight,
                'default_cutoff' => $cycle->default_cutoff,
            ] : null,
            'applicants' => $applicants,
            'filters' => $filters,
            'programmes' => Programme::query()->orderBy('code')->get(['id', 'code', 'name']),
            'counts' => $cycle
                ? Applicant::query()
                    ->where('admission_cycle_id', $cycle->id)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status')
                : collect(),
        ]);
    }

    public function show(Applicant $applicant): Response
    {
        $applicant->load([
            'user:id,name,email',
            'programme:id,name,code',
            'admittedProgramme:id,name,code',
            'admissionList:id,name',
            'documents.verifiedBy:id,name',
            'payments' => fn ($q) => $q->latest(),
        ]);

        return Inertia::render('admissions/applicant-detail', [
            'applicant' => $applicant,
            'documentTypes' => ApplicantDocument::TYPES,
            'capsStatuses' => array_map(fn ($c) => $c->value, CapsStatus::cases()),
            'applicationFee' => (float) $applicant->cycle->application_fee,
            'applicationFeePaid' => $applicant->hasPaidApplicationFee(),
        ]);
    }

    public function updateCaps(Request $request, Applicant $applicant): RedirectResponse
    {
        $validated = $request->validate([
            'caps_status' => ['required', Rule::enum(CapsStatus::class)],
        ]);

        $applicant->update(['caps_status' => $validated['caps_status']]);

        return back()->with('success', "CAPS status updated for {$applicant->application_no}.");
    }

    public function updateDocument(Request $request, ApplicantDocument $document): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['verify', 'reject'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $document->update([
            'status' => $validated['action'] === 'verify'
                ? DocumentStatus::Verified
                : DocumentStatus::Rejected,
            'note' => $validated['note'] ?? null,
            'verified_by_id' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return back()->with('success', 'Document '.($validated['action'] === 'verify' ? 'verified' : 'rejected').'.');
    }

    public function downloadDocument(ApplicantDocument $document): StreamedResponse
    {
        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function updateScore(Request $request, Applicant $applicant): RedirectResponse
    {
        $validated = $request->validate([
            'post_utme_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (! in_array($applicant->status, [ApplicantStatus::Submitted, ApplicantStatus::Screened], true)) {
            return back()->withErrors(['score' => 'Scores can only be entered for submitted applications.']);
        }

        $applicant->update([
            'post_utme_score' => $validated['post_utme_score'],
            // A changed score invalidates any previous screening.
            'status' => ApplicantStatus::Submitted,
            'aggregate_score' => null,
        ]);

        return back()->with('success', "Post-UTME score saved for {$applicant->application_no}.");
    }

    public function screen(AdmissionService $admissions): RedirectResponse
    {
        $cycle = AdmissionCycle::current();

        if ($cycle === null) {
            return back()->withErrors(['screen' => 'No active admission cycle.']);
        }

        $count = $admissions->screenCycle($cycle->id);

        return back()->with('success', "{$count} applicant(s) screened (aggregates computed).");
    }
}
