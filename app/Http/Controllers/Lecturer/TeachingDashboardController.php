<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseAllocation;
use App\Models\RegisteredCourse;
use App\Support\Dashboard\DashboardExport;
use App\Support\Dashboard\DashboardFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * A lecturer's own courses for a semester: how far scoring has got, where
 * the results sit in the approval chain, and how the class performed.
 */
class TeachingDashboardController extends Controller
{
    private const STAGES = [
        ResultStatus::Pending->value => 'Awaiting HOD',
        ResultStatus::HodApproved->value => 'Awaiting faculty board',
        ResultStatus::FacultyApproved->value => 'Awaiting senate',
        ResultStatus::SenateApproved->value => 'Released',
    ];

    public function show(Request $request): Response|HttpResponse
    {
        $staff = $request->user()->staff;
        abort_if($staff === null, 403, 'Your account has no staff record.');

        $filters = DashboardFilters::fromRequest($request);
        $semester = $filters->semester;

        $courses = collect();
        $grades = collect();

        if ($semester !== null) {
            $allocations = $staff->courseAllocations()
                ->where('semester_id', $semester->id)
                ->with('course:id,code,title,credit_units')
                ->get()
                ->sortBy(fn (CourseAllocation $a) => $a->course->code);

            foreach ($allocations as $allocation) {
                $rows = RegisteredCourse::query()
                    ->where('course_id', $allocation->course_id)
                    ->whereHas('courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
                    ->with('result')
                    ->get();

                $results = $rows->pluck('result')->filter(fn ($r) => $r !== null && $r->total_score !== null);
                $grades = $grades->concat($results->pluck('grade_letter')->filter());

                $courses->push([
                    'id' => $allocation->course_id,
                    'code' => $allocation->course->code,
                    'title' => $allocation->course->title,
                    'units' => $allocation->course->credit_units,
                    'is_coordinator' => $allocation->is_coordinator,
                    'registered' => $rows->count(),
                    'scored' => $results->count(),
                    'average' => $results->isEmpty() ? null : round((float) $results->avg('total_score'), 1),
                    'passRate' => $results->isEmpty() ? null : (int) round($results->where('is_passed', true)->count() / $results->count() * 100),
                    'stages' => collect(self::STAGES)->map(fn ($label, $status) => $results->filter(fn ($r) => $r->status->value === $status)->count())->all(),
                    'stage' => $this->stage($rows->count(), $results),
                ]);
            }
        }

        $stageTotals = collect(self::STAGES)->map(fn ($label, $status) => [
            'label' => $label,
            'value' => $courses->sum(fn ($c) => $c['stages'][$status]),
        ])->values();

        $data = [
            'semester' => $semester ? $semester->only('id', 'name') + ['is_current' => (bool) $semester->is_current] : null,
            'session' => $filters->session?->name,
            'totals' => [
                'courses' => $courses->count(),
                'registered' => $courses->sum('registered'),
                'scored' => $courses->sum('scored'),
                'released' => $stageTotals->last()['value'] ?? 0,
            ],
            'courses' => $courses->values(),
            'pipeline' => $stageTotals,
            'grades' => $grades->countBy()->sortKeys()->map(fn ($n, $letter) => ['label' => (string) $letter, 'value' => $n])->values(),
        ];

        if (in_array($request->query('format'), ['csv', 'pdf'], true)) {
            return $this->export($request->user()->name, $data)->download($request->query('format'));
        }

        return Inertia::render('lecturer/dashboard', $data + [
            'filters' => $filters->toArray(),
            'options' => ['sessions' => DashboardFilters::sessionOptions()],
        ]);
    }

    /** Where a course's results stand: the earliest stage any of them is at. */
    private function stage(int $registered, $results): string
    {
        if ($registered === 0) {
            return 'No students registered';
        }

        if ($results->isEmpty()) {
            return 'Not started';
        }

        if ($results->count() < $registered) {
            return 'Scoring in progress';
        }

        foreach (self::STAGES as $status => $label) {
            if ($results->contains(fn ($r) => $r->status->value === $status)) {
                return $label;
            }
        }

        return 'Released';
    }

    private function export(string $lecturer, array $data): DashboardExport
    {
        $export = new DashboardExport('Teaching dashboard', array_filter([
            'Lecturer' => $lecturer,
            'Session' => $data['session'],
            'Semester' => $data['semester']['name'] ?? null,
        ]));

        return $export
            ->table('Courses', ['Course', 'Title', 'Units', 'Registered', 'Scored', 'Average', 'Pass rate (%)', 'Stage'], $data['courses']->map(fn ($c) => [
                $c['code'], $c['title'], $c['units'], $c['registered'], $c['scored'], $c['average'], $c['passRate'], $c['stage'],
            ]))
            ->series('Results by approval stage', 'Stage', 'Results', $data['pipeline'])
            ->series('Grade distribution', 'Grade', 'Students', $data['grades']);
    }
}
