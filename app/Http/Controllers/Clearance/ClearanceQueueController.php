<?php

namespace App\Http\Controllers\Clearance;

use App\Enums\ClearanceItemStatus;
use App\Http\Controllers\Controller;
use App\Models\ClearanceItem;
use App\Services\Records\ClearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * One queue for every signing unit: each officer sees only the pending items
 * they are allowed to sign, with the facts that would block clearance.
 */
class ClearanceQueueController extends Controller
{
    public function index(Request $request, ClearanceService $clearance): Response
    {
        $user = $request->user();

        $items = ClearanceItem::query()
            ->where('status', ClearanceItemStatus::Pending)
            ->with(['clearanceRequest.student.user:id,name', 'clearanceRequest.student.programme.department', 'clearanceRequest.student.level'])
            ->oldest()
            ->get()
            ->filter(fn (ClearanceItem $item) => $clearance->canReview($user, $item))
            ->values()
            ->map(fn (ClearanceItem $item) => [
                'id' => $item->id,
                'unit' => $item->unit->value,
                'label' => $item->unit->label(),
                'student' => [
                    'name' => $item->clearanceRequest->student->user->name,
                    'matric_no' => $item->clearanceRequest->student->matric_no,
                    'programme' => $item->clearanceRequest->student->programme->name,
                    'level' => $item->clearanceRequest->student->level->name,
                ],
                'blockers' => $clearance->blockers($item),
            ]);

        return Inertia::render('clearance/queue', ['items' => $items]);
    }

    public function update(Request $request, ClearanceItem $item, ClearanceService $clearance): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['clear', 'reject'])],
            'note' => ['required_if:action,reject', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $clearance->review($request->user(), $item, $validated['action'], $validated['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['clearance' => $e->getMessage()]);
        }

        return back()->with('success', 'Clearance '.($validated['action'] === 'clear' ? 'granted' : 'rejected').'.');
    }
}
