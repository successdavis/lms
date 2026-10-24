<?php

namespace App\Http\Controllers\Staff;

use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $staff = $request->user()->staff()->firstOrFail();

        return Inertia::render('staff/leave', [
            'requests' => $staff->leaveRequests()->with('reviewedBy:id,name')->latest()->get(),
            'entitlement' => config('lms.leave.annual_days'),
            'used' => $this->annualDaysUsed($staff, (int) now()->year),
            'types' => array_map(fn ($t) => $t->value, LeaveType::cases()),
            'pendingApprovals' => $this->approvalQueue($request)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $staff = $request->user()->staff()->firstOrFail();
        $start = Carbon::parse($validated['starts_on']);
        $days = $this->workingDays($start, Carbon::parse($validated['ends_on']));

        if ($days === 0) {
            return back()->withErrors(['ends_on' => 'The selected period has no working days.']);
        }

        if ($validated['type'] === LeaveType::Annual->value) {
            $remaining = config('lms.leave.annual_days') - $this->annualDaysUsed($staff, $start->year);

            if ($days > $remaining) {
                return back()->withErrors(['ends_on' => "Only {$remaining} annual leave day(s) remain for {$start->year}."]);
            }
        }

        $staff->leaveRequests()->create($validated + ['days' => $days]);

        return back()->with('success', "Leave request for {$days} working day(s) submitted.");
    }

    public function approvals(Request $request): Response
    {
        return Inertia::render('staff/leave-approvals', [
            'requests' => $this->approvalQueue($request)
                ->with(['staff.user:id,name', 'staff.department:id,name'])
                ->oldest()
                ->get(),
        ]);
    }

    public function review(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($this->approvalQueue($request)->whereKey($leave->id)->exists(), 403);

        if ($validated['action'] === 'approve' && $leave->type === LeaveType::Annual) {
            $remaining = config('lms.leave.annual_days')
                - $this->annualDaysUsed($leave->staff, $leave->starts_on->year, exceptId: $leave->id);

            if ($leave->days > $remaining) {
                return back()->withErrors(['leave' => "Approving would exceed the annual entitlement ({$remaining} day(s) left)."]);
            }
        }

        $leave->update([
            'status' => $validated['action'] === 'approve' ? 'approved' : 'rejected',
            'note' => $validated['note'] ?? null,
            'reviewed_by_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Leave request '.($validated['action'] === 'approve' ? 'approved' : 'rejected').'.');
    }

    /**
     * Pending requests this user may decide: registrar/super-admin see all;
     * an HOD sees their department's staff, never their own request.
     */
    private function approvalQueue(Request $request)
    {
        $user = $request->user();
        $query = LeaveRequest::query()->where('status', 'pending');

        if ($user->hasAnyRole(['registrar', 'super-admin'])) {
            return $query->whereHas('staff', fn ($q) => $q->where('user_id', '!=', $user->id));
        }

        if ($user->hasRole('hod') && $user->staff?->department_id !== null) {
            return $query->whereHas('staff', fn ($q) => $q
                ->where('department_id', $user->staff->department_id)
                ->where('user_id', '!=', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Annual days already committed (approved or pending) in a calendar year.
     */
    private function annualDaysUsed(Staff $staff, int $year, ?int $exceptId = null): int
    {
        return (int) $staff->leaveRequests()
            ->where('type', LeaveType::Annual)
            ->whereIn('status', ['pending', 'approved'])
            ->whereYear('starts_on', $year)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum('days');
    }

    private function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days += $day->isWeekend() ? 0 : 1;
        }

        return $days;
    }
}
