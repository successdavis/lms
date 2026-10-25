<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Staff;
use App\Services\Hr\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PromotionController extends Controller
{
    public function index(Request $request, PromotionService $promotions): Response
    {
        $user = $request->user();
        $asOf = Carbon::today();

        $candidates = Staff::query()
            ->where('is_active', true)
            ->with(['user:id,name', 'department:id,name'])
            ->orderBy('staff_no')
            ->get()
            ->filter(fn (Staff $staff) => $promotions->canRecommend($user, $staff))
            ->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->user->name,
                'staff_no' => $staff->staff_no,
                'department' => $staff->department?->name,
                'designation' => $staff->designation,
                'grade_level' => $staff->grade_level,
                'years_in_rank' => round($promotions->yearsInRank($staff, $asOf), 1),
                'proposal' => $promotions->proposal($staff),
            ])
            ->values();

        return Inertia::render('hr/promotions', [
            'candidates' => $candidates,
            'minYears' => config('lms.hr.min_years_in_rank'),
            'canDecide' => $user->hasAnyRole(['hr-officer', 'registrar', 'super-admin']),
            'promotions' => Promotion::query()
                ->with(['staff.user:id,name', 'staff.department:id,name', 'recommendedBy:id,name', 'decidedBy:id,name'])
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(Request $request, PromotionService $promotions): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'effective_on' => ['required', 'date'],
            'to_grade_level' => ['nullable', 'integer', 'min:1', 'max:17'],
            'justification' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $promotions->recommend(
                $request->user(),
                Staff::findOrFail($validated['staff_id']),
                Carbon::parse($validated['effective_on']),
                $validated['to_grade_level'] ?? null,
                $validated['justification'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['promotion' => $e->getMessage()]);
        }

        return back()->with('success', 'Promotion recommended for approval.');
    }

    public function update(Request $request, Promotion $promotion, PromotionService $promotions): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $promotions->decide($request->user(), $promotion, $validated['action'] === 'approve', $validated['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['promotion' => $e->getMessage()]);
        }

        return back()->with('success', 'Promotion '.($validated['action'] === 'approve' ? 'approved and applied.' : 'rejected.'));
    }
}
