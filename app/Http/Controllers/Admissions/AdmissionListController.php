<?php

namespace App\Http\Controllers\Admissions;

use App\Enums\ApplicantStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Models\AdmissionList;
use App\Models\Applicant;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionListController extends Controller
{
    public function index(): Response
    {
        $cycle = AdmissionCycle::current();

        $lists = $cycle
            ? $cycle->lists()
                ->withCount([
                    'applicants',
                    'applicants as accepted_count' => fn ($q) => $q->where('status', ApplicantStatus::Accepted),
                    'applicants as matriculated_count' => fn ($q) => $q->where('status', ApplicantStatus::Matriculated),
                ])
                ->get()
            : collect();

        return Inertia::render('admissions/lists', [
            'cycle' => $cycle ? [
                'id' => $cycle->id,
                'session' => $cycle->academicSession->name,
                'default_cutoff' => $cycle->default_cutoff,
            ] : null,
            'lists' => $lists,
            'awaitingAdmission' => $cycle
                ? Applicant::query()
                    ->where('admission_cycle_id', $cycle->id)
                    ->where('status', ApplicantStatus::Screened)
                    ->count()
                : 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cycle = AdmissionCycle::current();

        if ($cycle === null) {
            return back()->withErrors(['list' => 'No active admission cycle.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $cycle->lists()->create([
            'name' => $validated['name'],
            'sort' => $cycle->lists()->count(),
        ]);

        return back()->with('success', 'Admission list created.');
    }

    public function publish(AdmissionList $list): RedirectResponse
    {
        $list->update(['published_at' => now()]);

        return back()->with('success', "{$list->name} published — applicants can now see their offers.");
    }

    /**
     * Admit applicants onto a list. mode=auto takes every screened applicant
     * at or above the cycle's cutoff; mode=manual takes the given ids
     * (cutoff still enforced unless ignore_cutoff, e.g. supplementary lists).
     */
    public function admit(Request $request, AdmissionList $list, AdmissionService $admissions): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:auto,manual'],
            'applicant_ids' => ['required_if:mode,manual', 'array'],
            'applicant_ids.*' => ['integer'],
            'ignore_cutoff' => ['boolean'],
        ]);

        $ids = $validated['mode'] === 'auto'
            ? Applicant::query()
                ->where('admission_cycle_id', $list->admission_cycle_id)
                ->where('status', ApplicantStatus::Screened)
                ->where('aggregate_score', '>=', $list->cycle->default_cutoff)
                ->pluck('id')
                ->all()
            : $validated['applicant_ids'];

        $result = $admissions->admit($list, $ids, (bool) ($validated['ignore_cutoff'] ?? false));

        $message = "{$result['admitted']} applicant(s) admitted to {$list->name}.";

        if ($result['skipped'] !== []) {
            return back()->with('success', $message)->withErrors(
                collect($result['skipped'])->mapWithKeys(fn ($reason, $i) => ["admit.{$i}" => $reason])->all()
            );
        }

        return back()->with('success', $message);
    }

    public function matriculate(AdmissionList $list, AdmissionService $admissions): RedirectResponse
    {
        $result = $admissions->matriculateList($list);

        $message = "{$result['matriculated']} student(s) matriculated from {$list->name}.";

        if ($result['skipped'] !== []) {
            return back()->with('success', $message)->withErrors(
                collect($result['skipped'])->mapWithKeys(fn ($reason, $i) => ["matriculate.{$i}" => $reason])->all()
            );
        }

        return back()->with('success', $message);
    }
}
