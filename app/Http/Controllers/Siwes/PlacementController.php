<?php

namespace App\Http\Controllers\Siwes;

use App\Enums\SiwesStatus;
use App\Http\Controllers\Controller;
use App\Models\SiwesPlacement;
use App\Notifications\SiwesUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SIWES coordinator: approve placements, then grade the attachment from the
 * ITF logbook (/40), the student's report (/40) and supervision visits (/20).
 */
class PlacementController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';

        return Inertia::render('siwes/placements', [
            'placements' => SiwesPlacement::query()
                ->with(['student.user:id,name', 'student.programme:id,code', 'academicSession:id,name', 'reviewedBy:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->oldest()
                ->get(),
            'status' => $status,
            'passMark' => config('lms.siwes.pass_mark'),
        ]);
    }

    public function update(Request $request, SiwesPlacement $placement): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
        ]);

        if ($placement->status !== SiwesStatus::Pending) {
            return back()->withErrors(['siwes' => 'Only pending placements can be approved or rejected.']);
        }

        $placement->update([
            'status' => $validated['action'] === 'approve' ? SiwesStatus::Approved : SiwesStatus::Rejected,
            'reviewed_by_id' => $request->user()->id,
        ]);

        $placement->student->user->notify(SiwesUpdated::for($placement->fresh()));

        return back()->with('success', 'Placement '.($validated['action'] === 'approve' ? 'approved' : 'rejected').'.');
    }

    public function grade(Request $request, SiwesPlacement $placement): RedirectResponse
    {
        $validated = $request->validate([
            'logbook_score' => ['required', 'numeric', 'min:0', 'max:40'],
            'report_score' => ['required', 'numeric', 'min:0', 'max:40'],
            'visit_score' => ['required', 'numeric', 'min:0', 'max:20'],
        ]);

        if (! in_array($placement->status, [SiwesStatus::Approved, SiwesStatus::Graded], true)) {
            return back()->withErrors(['siwes' => 'Only approved placements can be graded.']);
        }

        if ($placement->ends_on->isFuture()) {
            return back()->withErrors(['siwes' => 'Grade the attachment after it ends on '.$placement->ends_on->toFormattedDateString().'.']);
        }

        $total = round($validated['logbook_score'] + $validated['report_score'] + $validated['visit_score'], 2);

        $placement->update($validated + [
            'total_score' => $total,
            'is_passed' => $total >= config('lms.siwes.pass_mark'),
            'status' => SiwesStatus::Graded,
            'reviewed_by_id' => $request->user()->id,
        ]);

        $placement->student->user->notify(SiwesUpdated::for($placement->fresh()));

        return back()->with('success', "SIWES graded: {$total}/100 (".($total >= config('lms.siwes.pass_mark') ? 'pass' : 'fail').').');
    }
}
