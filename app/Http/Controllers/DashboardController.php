<?php

namespace App\Http\Controllers;

use App\Enums\AcademicStanding;
use App\Enums\AllocationStatus;
use App\Enums\ApplicantStatus;
use App\Enums\InvoicePurpose;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ResultStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\ClassificationBand;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Level;
use App\Models\Payment;
use App\Models\Result;
use App\Models\Semester;
use App\Models\SemesterResult;
use App\Models\Student;
use App\Models\User;
use App\Support\Dashboard\DashboardExport;
use App\Support\Dashboard\DashboardFilters;
use App\Support\Dashboard\DashboardScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Institution analytics for staff. Each section is computed only for the
 * roles that may see it, over the departments the user's scope allows, for
 * the chosen session/semester and date range. ?format=csv|pdf downloads the
 * same figures.
 */
class DashboardController extends Controller
{
    /** Roles that get a section on this overview. */
    public const OVERVIEW_ROLES = ['exam-officer', 'dean', 'hod', 'bursar', 'admission-officer', 'student-affairs', 'registrar', 'super-admin'];

    private const ACTIVE = [StudentStatus::Active, StudentStatus::Probation, StudentStatus::Spillover];

    public function show(Request $request): Response|RedirectResponse|HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('student')) {
            return redirect()->route('student.dashboard');
        }

        if ($user->hasRole('applicant') || $user->getRoleNames()->isEmpty()) {
            return redirect()->route('applicant.show');
        }

        // Staff without an overview section get their own dashboard.
        if (! $user->hasAnyRole(self::OVERVIEW_ROLES)) {
            if ($user->hasRole('hr-officer')) {
                return redirect()->route('hr.dashboard', $request->query());
            }

            if ($user->hasRole('lecturer')) {
                return redirect()->route('lecturer.dashboard', $request->query());
            }
        }

        $can = fn (string ...$roles) => $user->hasAnyRole([...$roles, 'registrar', 'super-admin']);
        $filters = DashboardFilters::fromRequest($request);
        $scope = DashboardScope::forUser($user, $request);
        $session = $filters->session;

        $data = [
            'academics' => $can('exam-officer', 'dean', 'hod') ? $this->academics($filters->semester, $scope) : null,
            'finance' => $can('bursar') && $session ? $this->finance($session, $filters, $scope) : null,
            'admissions' => $can('admission-officer') && $session ? $this->admissions($session, $scope) : null,
            'hostels' => $can('student-affairs') && $session ? $this->hostels($session) : null,
        ];

        if (in_array($request->query('format'), ['csv', 'pdf'], true)) {
            return $this->export($data, $filters, $scope)->download($request->query('format'));
        }

        return Inertia::render('dashboard', $data + [
            'session' => $session?->name,
            'semester' => $filters->semester?->name,
            'range' => $filters->rangeLabel(),
            'filters' => $filters->toArray() + ['faculty_id' => $scope->facultyId, 'department_id' => $scope->departmentId],
            'scope' => $scope->toArray(),
            'options' => [
                'sessions' => DashboardFilters::sessionOptions(),
                'faculties' => $scope->options(),
            ],
        ]);
    }

    /** Limit a student query to the scope's departments. */
    private function scopeStudents(Builder $query, DashboardScope $scope): Builder
    {
        return $scope->isInstitution()
            ? $query
            : $query->whereHas('programme', fn ($q) => $q->whereIn('department_id', $scope->departmentIds));
    }

    private function academics(?Semester $semester, DashboardScope $scope): array
    {
        $students = fn () => $this->scopeStudents(Student::query(), $scope);
        $active = $students()->whereIn('status', self::ACTIVE)->count();

        $byLevel = $students()
            ->whereIn('status', self::ACTIVE)
            ->selectRaw('level_id, count(*) as total')
            ->groupBy('level_id')
            ->pluck('total', 'level_id');

        $levels = Level::query()->whereIn('id', $byLevel->keys())->orderBy('rank')->orderBy('code')->get()
            ->map(fn (Level $level) => ['label' => $level->name, 'value' => (int) $byLevel[$level->id]])
            ->values();

        // Department split, useful whenever the scope spans more than one.
        $byDepartment = collect();
        if ($scope->departmentIds === null || count($scope->departmentIds) > 1) {
            $byDepartment = $students()
                ->whereIn('status', self::ACTIVE)
                ->with('programme.department:id,name')
                ->get(['id', 'programme_id'])
                ->countBy(fn (Student $s) => $s->programme->department->name)
                ->sortDesc()
                ->map(fn ($total, $name) => ['label' => $name, 'value' => $total])
                ->values();
        }

        $registered = $semester
            ? CourseRegistration::query()
                ->where('semester_id', $semester->id)
                ->whereIn('student_id', $students()->select('id'))
                ->count()
            : 0;

        // The pipeline follows the course's department, like the approval queue.
        $pipeline = $semester
            ? Result::query()
                ->whereHas('registeredCourse.courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
                ->when(! $scope->isInstitution(), fn ($q) => $q->whereHas('registeredCourse.course', fn ($c) => $c->whereIn('department_id', $scope->departmentIds)))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        $stages = [
            ResultStatus::Pending->value => 'Awaiting HOD',
            ResultStatus::HodApproved->value => 'Awaiting faculty board',
            ResultStatus::FacultyApproved->value => 'Awaiting senate',
            ResultStatus::SenateApproved->value => 'Released',
        ];

        // Latest CGPA per student, banded on that student's own grade scale.
        $latest = SemesterResult::query()
            ->whereIn('id', SemesterResult::query()->selectRaw('max(id)')->groupBy('student_id'))
            ->whereIn('student_id', $students()->select('id'))
            ->with('student.programme')
            ->get();

        $bands = ClassificationBand::query()->orderBy('sort')->get()->groupBy('grade_scale_id');
        $classCounts = [];

        foreach ($latest as $snapshot) {
            $band = ($bands[$snapshot->student->programme->grade_scale_id] ?? collect())
                ->first(fn ($b) => (float) $snapshot->cgpa >= (float) $b->min_cgpa && (float) $snapshot->cgpa <= (float) $b->max_cgpa);
            $name = $band?->name ?? 'Below pass';
            $classCounts[$name] = ($classCounts[$name] ?? 0) + 1;
        }

        $classOrder = ClassificationBand::query()->orderBy('grade_scale_id')->orderBy('sort')->pluck('name')->unique()->push('Below pass');

        return [
            'activeStudents' => $active,
            'registered' => $registered,
            'onProbation' => $latest->where('standing', AcademicStanding::Probation)->count(),
            'byLevel' => $levels,
            'byDepartment' => $byDepartment,
            'pipeline' => collect($stages)->map(fn ($label, $status) => ['label' => $label, 'value' => (int) ($pipeline[$status] ?? 0)])->values(),
            'classes' => $classOrder
                ->filter(fn ($name) => isset($classCounts[$name]))
                ->map(fn ($name) => ['label' => $name, 'value' => $classCounts[$name]])
                ->values(),
        ];
    }

    private function finance(AcademicSession $session, DashboardFilters $filters, DashboardScope $scope): array
    {
        $scopedStudents = $this->scopeStudents(Student::query(), $scope)->select('id');

        $schoolFees = fn () => Invoice::query()
            ->where('academic_session_id', $session->id)
            ->where('purpose', InvoicePurpose::SchoolFees)
            ->when(! $scope->isInstitution(), fn ($q) => $q->whereIn('student_id', $scopedStudents));

        $invoiced = (float) $schoolFees()->sum('total');
        $collected = (float) $schoolFees()->sum('amount_paid');

        // Successful payments in the date range, bucketed in PHP so the
        // query stays portable across SQLite and MySQL. A scoped view only
        // counts its own students' payments (application fees have none).
        $payments = Payment::query()
            ->where('status', PaymentStatus::Successful)
            ->whereBetween('paid_at', [$filters->from, $filters->to])
            ->when(! $scope->isInstitution(), fn ($q) => $q->whereIn('student_id', $scopedStudents))
            ->get(['amount', 'paid_at']);

        $grouped = $payments->groupBy(fn (Payment $p) => $filters->bucketKey($p->paid_at));
        $series = collect($filters->buckets())->map(fn ($label, $key) => [
            'label' => $label,
            'value' => round((float) ($grouped[$key] ?? collect())->sum('amount'), 2),
        ]);

        return [
            'invoiced' => $invoiced,
            'collected' => $collected,
            'invoices' => $schoolFees()->count(),
            'fullyPaid' => $schoolFees()->where('status', InvoiceStatus::Paid)->count(),
            'outstanding' => round($invoiced - $collected, 2),
            'received' => round((float) $payments->sum('amount'), 2),
            'daily' => $filters->daily(),
            'byPeriod' => $series->values(),
        ];
    }

    private function admissions(AcademicSession $session, DashboardScope $scope): ?array
    {
        $cycle = AdmissionCycle::query()->where('academic_session_id', $session->id)->latest('id')->first();

        if ($cycle === null) {
            return null;
        }

        $counts = Applicant::query()
            ->where('admission_cycle_id', $cycle->id)
            ->when(! $scope->isInstitution(), fn ($q) => $q->whereHas('programme', fn ($p) => $p->whereIn('department_id', $scope->departmentIds)))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Funnel: each stage counts everyone who reached it or went further.
        $order = [
            ApplicantStatus::Submitted, ApplicantStatus::Screened, ApplicantStatus::Admitted,
            ApplicantStatus::Accepted, ApplicantStatus::Matriculated,
        ];
        $labels = ['Submitted', 'Screened', 'Offered admission', 'Accepted offer', 'Matriculated'];

        $funnel = collect($order)->map(function (ApplicantStatus $stage, int $i) use ($order, $counts, $labels) {
            $reached = collect(array_slice($order, $i))->sum(fn (ApplicantStatus $s) => (int) ($counts[$s->value] ?? 0));

            return ['label' => $labels[$i], 'value' => $reached];
        });

        return [
            'session' => $cycle->academicSession->name,
            'drafts' => (int) ($counts[ApplicantStatus::Draft->value] ?? 0),
            'funnel' => $funnel->values(),
        ];
    }

    /** Halls are shared across departments, so occupancy is never scoped. */
    private function hostels(AcademicSession $session): array
    {
        return Hostel::query()->where('is_active', true)->with('rooms')->orderBy('name')->get()
            ->map(function (Hostel $hostel) use ($session) {
                $capacity = $hostel->rooms->sum('capacity');
                $confirmed = $hostel->allocations()
                    ->where('academic_session_id', $session->id)
                    ->where('hostel_allocations.status', AllocationStatus::Confirmed)
                    ->count();

                return [
                    'name' => $hostel->name,
                    'capacity' => $capacity,
                    'occupied' => $hostel->rooms->sum(fn ($room) => $room->occupiedSpaces($session->id)),
                    'confirmed' => $confirmed,
                ];
            })
            ->all();
    }

    private function export(array $data, DashboardFilters $filters, DashboardScope $scope): DashboardExport
    {
        $export = new DashboardExport('Institution overview', array_filter([
            'Scope' => $scope->label,
            'Session' => $filters->session?->name,
            'Semester' => $filters->semester?->name,
            'Payments from' => $data['finance'] ? $filters->rangeLabel() : null,
        ]));

        if ($a = $data['academics']) {
            $export->table('Academics', ['Measure', 'Value'], [
                ['Active students', $a['activeStudents']],
                ['Registered this semester', $a['registered']],
                ['On academic probation', $a['onProbation']],
            ]);
            $export->series('Students by level', 'Level', 'Students', $a['byLevel']);
            if (count($a['byDepartment']) > 0) {
                $export->series('Students by department', 'Department', 'Students', $a['byDepartment']);
            }
            $export->series('Results approval pipeline', 'Stage', 'Course results', $a['pipeline']);
            $export->series('Students by current class', 'Class', 'Students', $a['classes']);
        }

        if ($f = $data['finance']) {
            $export->table('Finance', ['Measure', 'Value'], [
                ['School fees invoiced (NGN)', $f['invoiced']],
                ['School fees collected (NGN)', $f['collected']],
                ['Outstanding school fees (NGN)', $f['outstanding']],
                ['Payments received in range (NGN)', $f['received']],
                ['School-fees invoices', $f['invoices']],
                ['Invoices fully paid', $f['fullyPaid']],
            ]);
            $export->series('Payments received by '.($f['daily'] ? 'day' : 'month'), $f['daily'] ? 'Day' : 'Month', 'Amount (NGN)', $f['byPeriod']);
        }

        if ($ad = $data['admissions']) {
            $export->series("Admissions funnel ({$ad['session']})", 'Stage', 'Applicants', $ad['funnel']);
        }

        if (($h = $data['hostels']) !== null) {
            $export->table('Hostel occupancy', ['Hall', 'Bed spaces', 'Occupied', 'Confirmed', 'Reserved'], collect($h)->map(fn ($hall) => [
                $hall['name'], $hall['capacity'], $hall['occupied'], $hall['confirmed'], $hall['occupied'] - $hall['confirmed'],
            ]));
        }

        return $export;
    }
}
