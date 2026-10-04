<?php

namespace App\Http\Controllers\Admissions;

use App\Enums\ApplicantStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\Programme;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
