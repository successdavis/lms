<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Records\ClearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ClearanceController extends Controller
{
    public function show(Request $request): Response
    {
        $student = $request->user()->student()->with(['level', 'programme'])->firstOrFail();
        $clearance = $student->clearanceRequest()->with('items.reviewedBy:id,name')->first();

        return Inertia::render('student/clearance', [
            'eligible' => $student->isInFinalYear(),
            'clearance' => $clearance ? [
                'status' => $clearance->status,
                'cleared_at' => $clearance->cleared_at?->toFormattedDateString(),
                'items' => $clearance->items->map(fn ($item) => [
                    'unit' => $item->unit->value,
                    'label' => $item->unit->label(),
                    'status' => $item->status->value,
                    'note' => $item->note,
                    'reviewed_by' => $item->reviewedBy?->name,
                    'reviewed_at' => $item->reviewed_at?->toDayDateTimeString(),
                ]),
            ] : null,
        ]);
    }

    public function start(Request $request, ClearanceService $clearance): RedirectResponse
    {
        $student = $request->user()->student()->firstOrFail();

        try {
            $clearance->start($student);
        } catch (RuntimeException $e) {
            return back()->withErrors(['clearance' => $e->getMessage()]);
        }

        return back()->with('success', 'Clearance started. Each unit will review and sign off.');
    }

    public function resubmit(Request $request, ClearanceService $clearance): RedirectResponse
    {
        $student = $request->user()->student()->firstOrFail();
        $clearanceRequest = $student->clearanceRequest()->firstOrFail();

        $count = $clearance->resubmit($clearanceRequest);

        return back()->with('success', "{$count} unit(s) sent back for review.");
    }
}
