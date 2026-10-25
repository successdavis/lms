<?php

namespace App\Http\Controllers\Hr;

use App\Enums\StaffType;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LeaveRequest;
use App\Models\Promotion;
use App\Models\Staff;
use App\Services\Hr\PromotionService;
use App\Support\Dashboard\DashboardExport;
use App\Support\Dashboard\DashboardFilters;
use App\Support\Dashboard\DashboardScope;
use App\Support\StaffRanks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Establishment overview for HR and the registry: headcount by department,
 * rank and grade level; payroll readiness; who is due for promotion; and
 * leave and promotion activity over the chosen date range.
 */
class HrDashboardController extends Controller
{
    public function show(Request $request, PromotionService $promotions): Response|HttpResponse
    {
        $filters = DashboardFilters::fromRequest($request);
        $scope = DashboardScope::forUser($request->user(), $request);
        $today = Carbon::today();

        $staffQuery = fn (): Builder => Staff::query()
            ->where('is_active', true)
            ->when(! $scope->isInstitution(), fn ($q) => $q->whereIn('department_id', $scope->departmentIds));

        $staff = $staffQuery()->with(['user:id,name', 'department:id,name'])->get();
        $staffIds = $staffQuery()->select('id');

        // Rank order: the institution's ladder first, then any other titles.
        $ladder = StaffRanks::ladder(Institution::current()?->type);
        $byRank = $staff->where('type', StaffType::Academic)
            ->countBy(fn (Staff $s) => $s->designation ?: 'Not set')
            ->sortBy(fn ($n, $rank) => ($i = array_search($rank, $ladder, true)) === false ? 100 : $i)
            ->map(fn ($n, $rank) => ['label' => $rank, 'value' => $n])
            ->values();

        $byGradeLevel = $staff->filter(fn (Staff $s) => $s->grade_level !== null)
            ->countBy('grade_level')
            ->sortKeys()
            ->map(fn ($n, $gl) => ['label' => 'GL '.str_pad((string) $gl, 2, '0', STR_PAD_LEFT), 'value' => $n])
            ->values();

        $byDepartment = $staff->countBy(fn (Staff $s) => $s->department?->name ?? 'No department')
            ->sortDesc()
            ->map(fn ($n, $name) => ['label' => $name, 'value' => $n])
            ->values();

        $pending = Promotion::query()->where('status', 'pending')->whereIn('staff_id', $staffIds)->pluck('staff_id');
        $minYears = (float) config('lms.hr.min_years_in_rank');
        $type = Institution::current()?->type;

        // Due: enough years in rank, a rank to move to, and no case open.
        $due = $staff
            ->filter(fn (Staff $s) => ! $pending->contains($s->id)
                && $promotions->yearsInRank($s, $today) >= $minYears
                && ($s->type !== StaffType::Academic || StaffRanks::next($type, $s->designation) !== null))
            ->map(fn (Staff $s) => [
                'id' => $s->id,
                'name' => $s->user->name,
                'staff_no' => $s->staff_no,
                'designation' => $s->designation,
                'department' => $s->department?->name,
                'years' => round($promotions->yearsInRank($s, $today), 1),
                'next' => $s->type === StaffType::Academic ? StaffRanks::next($type, $s->designation) : 'GL '.((int) $s->grade_level + 1),
            ])
            ->sortByDesc('years')
            ->values();

        $onLeaveToday = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereIn('staff_id', $staffIds)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->count();

        // Approved leave overlapping the range, in working days by type.
        $leave = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereIn('staff_id', $staffIds)
            ->whereDate('starts_on', '<=', $filters->to)
            ->whereDate('ends_on', '>=', $filters->from)
            ->get(['type', 'days']);

        $decided = Promotion::query()
            ->whereIn('staff_id', $staffIds)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereBetween('decided_at', [$filters->from, $filters->to])
            ->pluck('status')
            ->countBy();

        $data = [
            'totals' => [
                'active' => $staff->count(),
                'academic' => $staff->where('type', StaffType::Academic)->count(),
                'nonAcademic' => $staff->where('type', StaffType::NonAcademic)->count(),
                'payrollReady' => $staff->filter(fn (Staff $s) => $s->ippis_number && $s->account_number && $s->grade_level)->count(),
                'pendingPromotions' => $pending->count(),
                'onLeaveToday' => $onLeaveToday,
                'due' => $due->count(),
            ],
            'byDepartment' => $scope->departmentIds !== null && count($scope->departmentIds) === 1 ? [] : $byDepartment,
            'byRank' => $byRank,
            'byGradeLevel' => $byGradeLevel,
            'leaveByType' => $leave->groupBy(fn (LeaveRequest $l) => ucfirst($l->type->value))
                ->map(fn ($group, $type) => ['label' => $type, 'value' => (int) $group->sum('days')])
                ->sortByDesc('value')
                ->values(),
            'promotionsDecided' => [
                ['label' => 'Approved', 'value' => (int) ($decided['approved'] ?? 0)],
                ['label' => 'Not approved', 'value' => (int) ($decided['rejected'] ?? 0)],
            ],
            'due' => $due->take(10)->values(),
        ];

        if (in_array($request->query('format'), ['csv', 'pdf'], true)) {
            return $this->export($data, $due, $filters, $scope)->download($request->query('format'));
        }

        return Inertia::render('hr/dashboard', $data + [
            'minYears' => $minYears,
            'range' => $filters->rangeLabel(),
            'filters' => $filters->toArray() + ['faculty_id' => $scope->facultyId, 'department_id' => $scope->departmentId],
            'scope' => $scope->toArray(),
            'options' => ['faculties' => $scope->options()],
        ]);
    }

    private function export(array $data, $due, DashboardFilters $filters, DashboardScope $scope): DashboardExport
    {
        $t = $data['totals'];
        $export = new DashboardExport('HR dashboard', [
            'Scope' => $scope->label,
            'Leave and promotions' => $filters->rangeLabel(),
        ]);

        $export->table('Establishment', ['Measure', 'Staff'], [
            ['Active staff', $t['active']],
            ['Academic', $t['academic']],
            ['Non-academic', $t['nonAcademic']],
            ['Payroll details complete', $t['payrollReady']],
            ['On leave today', $t['onLeaveToday']],
            ['Promotion cases pending', $t['pendingPromotions']],
            ['Due for promotion', $t['due']],
        ]);

        if (count($data['byDepartment']) > 0) {
            $export->series('Staff by department', 'Department', 'Staff', $data['byDepartment']);
        }

        return $export
            ->series('Academic staff by rank', 'Rank', 'Staff', $data['byRank'])
            ->series('Staff by grade level', 'Grade level', 'Staff', $data['byGradeLevel'])
            ->series('Approved leave in range', 'Type', 'Working days', $data['leaveByType'])
            ->series('Promotion decisions in range', 'Decision', 'Cases', $data['promotionsDecided'])
            ->table('Due for promotion', ['Staff No', 'Name', 'Designation', 'Department', 'Years in rank', 'Next'], $due->map(fn ($s) => [
                $s['staff_no'], $s['name'], $s['designation'], $s['department'], $s['years'], $s['next'],
            ]));
    }
}
