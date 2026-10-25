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
use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Level;
use App\Models\Payment;
use App\Models\Result;
use App\Models\Semester;
use App\Models\SemesterResult;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Institution analytics for staff. Each section is computed only for the
 * roles that may see it.
 */
class DashboardController extends Controller
{
    public function show(): Response
    {
        /** @var User $user */
        $user = request()->user();
        $can = fn (string ...$roles) => $user->hasAnyRole([...$roles, 'registrar', 'super-admin']);

        $session = AcademicSession::current();
        $semester = Semester::current();

        return Inertia::render('dashboard', [
            'session' => $session?->name,
            'semester' => $semester?->name,
            'academics' => $can('exam-officer', 'dean', 'hod') ? $this->academics($semester) : null,
            'finance' => $can('bursar') && $session ? $this->finance($session) : null,
            'admissions' => $can('admission-officer') ? $this->admissions() : null,
            'hostels' => $can('student-affairs') && $session ? $this->hostels($session) : null,
        ]);
    }

    private function academics(?Semester $semester): array
    {
        $activeStatuses = [StudentStatus::Active, StudentStatus::Probation, StudentStatus::Spillover];
        $active = Student::query()->whereIn('status', $activeStatuses)->count();

        $byLevel = Student::query()
            ->whereIn('status', $activeStatuses)
            ->selectRaw('level_id, count(*) as total')
            ->groupBy('level_id')
            ->pluck('total', 'level_id');

        $levels = Level::query()->whereIn('id', $byLevel->keys())->orderBy('rank')->orderBy('code')->get()
            ->map(fn (Level $level) => ['label' => $level->name, 'value' => (int) $byLevel[$level->id]])
            ->values();

        $registered = $semester ? CourseRegistration::query()->where('semester_id', $semester->id)->count() : 0;

        $pipeline = $semester
            ? Result::query()
                ->whereHas('registeredCourse.courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
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
            'pipeline' => collect($stages)->map(fn ($label, $status) => ['label' => $label, 'value' => (int) ($pipeline[$status] ?? 0)])->values(),
            'classes' => $classOrder
                ->filter(fn ($name) => isset($classCounts[$name]))
                ->map(fn ($name) => ['label' => $name, 'value' => $classCounts[$name]])
                ->values(),
        ];
    }

    private function finance(AcademicSession $session): array
    {
        $schoolFees = Invoice::query()->where('academic_session_id', $session->id)->where('purpose', InvoicePurpose::SchoolFees);
        $invoiced = (float) (clone $schoolFees)->sum('total');
        $collected = (float) (clone $schoolFees)->sum('amount_paid');

        // Last six calendar months of successful payments, grouped in PHP so
        // the query stays portable across SQLite and MySQL.
        $start = Carbon::now()->startOfMonth()->subMonths(5);
        $payments = Payment::query()
            ->where('status', PaymentStatus::Successful)
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $p) => $p->paid_at->format('Y-m'));

        $months = collect(range(0, 5))->map(function (int $i) use ($start, $payments) {
            $month = $start->copy()->addMonths($i);

            return [
                'label' => $month->format('M Y'),
                'value' => round((float) ($payments[$month->format('Y-m')] ?? collect())->sum('amount'), 2),
            ];
        });

        return [
            'invoiced' => $invoiced,
            'collected' => $collected,
            'invoices' => (clone $schoolFees)->count(),
            'fullyPaid' => (clone $schoolFees)->where('status', InvoiceStatus::Paid)->count(),
            'outstanding' => round($invoiced - $collected, 2),
            'byMonth' => $months->values(),
        ];
    }

    private function admissions(): ?array
    {
        $cycle = AdmissionCycle::current();

        if ($cycle === null) {
            return null;
        }

        $counts = Applicant::query()
            ->where('admission_cycle_id', $cycle->id)
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
}
