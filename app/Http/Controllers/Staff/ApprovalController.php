<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Academics\ResultComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Result approval queues. The pipeline mirrors Nigerian practice:
 * pending (lecturer) -> hod_approved -> faculty_approved -> senate_approved.
 * HODs move pending results of their department's courses forward; deans move
 * hod_approved results; registrar/super-admin give final (senate) approval,
 * which also computes GPA/CGPA snapshots for students whose semester is
 * fully approved.
 */
class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $semester = Semester::current();
        $user = $request->user();

        $queue = collect();

        if ($semester !== null) {
            $results = Result::query()
                ->whereNot('status', ResultStatus::SenateApproved)
                ->whereHas('registeredCourse.courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
                ->with('registeredCourse.course.department')
                ->get();

            // HODs only see their own department's courses.
            if ($user->hasRole('hod') && ! $user->hasAnyRole(['registrar', 'super-admin', 'dean'])) {
                $departmentId = $user->staff?->department_id;
                $results = $results->filter(
                    fn (Result $r) => $r->registeredCourse->course->department_id === $departmentId
                );
            }

            $queue = $results
                ->groupBy(fn (Result $r) => $r->registeredCourse->course_id)
                ->map(function ($group) {
                    $course = $group->first()->registeredCourse->course;

                    return [
                        'course_id' => $course->id,
                        'code' => $course->code,
                        'title' => $course->title,
                        'department' => $course->department->name,
                        'counts' => $group->countBy(fn (Result $r) => $r->status->value),
                        'total' => $group->count(),
                    ];
                })
                ->values();
        }

        return Inertia::render('staff/approvals', [
            'semester' => $semester?->only('id', 'name'),
            'queue' => $queue,
            'canSenateApprove' => $user->hasAnyRole(['registrar', 'super-admin']),
        ]);
    }

    public function approve(Request $request, Course $course, ResultComputationService $computer): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'in:hod,faculty,senate'],
        ]);

        $semester = Semester::current();
        abort_if($semester === null, 404, 'No current semester.');

        $user = $request->user();

        [$from, $to] = match ($validated['stage']) {
            'hod' => [ResultStatus::Pending, ResultStatus::HodApproved],
            'faculty' => [ResultStatus::HodApproved, ResultStatus::FacultyApproved],
            'senate' => [ResultStatus::FacultyApproved, ResultStatus::SenateApproved],
        };

        $allowed = match ($validated['stage']) {
            'hod' => $user->hasAnyRole(['hod', 'registrar', 'super-admin'])
                && (! $user->hasRole('hod') || $user->hasAnyRole(['registrar', 'super-admin'])
                    || $user->staff?->department_id === $course->department_id),
            'faculty' => $user->hasAnyRole(['dean', 'registrar', 'super-admin']),
            'senate' => $user->hasAnyRole(['registrar', 'super-admin']),
        };

        abort_unless($allowed, 403);

        $affectedStudentIds = DB::transaction(function () use ($course, $semester, $from, $to) {
            $results = Result::query()
                ->where('status', $from)
                ->whereHas('registeredCourse', fn ($q) => $q->where('course_id', $course->id))
                ->whereHas('registeredCourse.courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
                ->with('registeredCourse.courseRegistration:id,student_id')
                ->get();

            Result::query()->whereIn('id', $results->pluck('id'))->update(['status' => $to->value]);

            return $results->map(fn (Result $r) => $r->registeredCourse->courseRegistration->student_id)->unique();
        });

        // After final approval, snapshot GPA/CGPA for students whose whole
        // semester is now senate-approved.
        if ($to === ResultStatus::SenateApproved) {
            foreach ($affectedStudentIds as $studentId) {
                $student = Student::find($studentId);

                $pending = Result::query()
                    ->whereNot('status', ResultStatus::SenateApproved)
                    ->whereHas('registeredCourse.courseRegistration', fn ($q) => $q
                        ->where('student_id', $studentId)
                        ->where('semester_id', $semester->id))
                    ->exists();

                if ($student !== null && ! $pending) {
                    try {
                        $computer->computeSemester($student, $semester);
                    } catch (\RuntimeException) {
                        // Some courses not yet graded — snapshot waits.
                    }
                }
            }
        }

        return back()->with('success', 'Results moved to '.$to->value.'.');
    }
}
