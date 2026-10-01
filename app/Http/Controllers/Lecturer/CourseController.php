<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\RegisteredCourse;
use App\Models\Semester;
use App\Services\Academics\GradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $staff = $request->user()->staff()->firstOrFail();
        $semester = Semester::current();

        $allocations = $semester
            ? $staff->courseAllocations()
                ->where('semester_id', $semester->id)
                ->with('course:id,code,title,credit_units,ca_weight,exam_weight')
                ->get()
                ->map(function ($allocation) use ($semester) {
                    $registered = RegisteredCourse::query()
                        ->where('course_id', $allocation->course_id)
                        ->whereHas('courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
                        ->count();

                    return [
                        'course' => $allocation->course,
                        'is_coordinator' => $allocation->is_coordinator,
                        'registered_students' => $registered,
                    ];
                })
            : collect();

        return Inertia::render('lecturer/courses', [
            'semester' => $semester?->only('id', 'name'),
            'allocations' => $allocations,
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        $staff = $request->user()->staff()->firstOrFail();
        $semester = Semester::current();

        abort_if($semester === null, 404, 'No current semester.');
        $this->assertAllocated($staff->id, $course->id, $semester->id);

        $rows = RegisteredCourse::query()
            ->where('course_id', $course->id)
            ->whereHas('courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
            ->with(['courseRegistration.student.user:id,name', 'result'])
            ->get()
            ->map(fn (RegisteredCourse $rc) => [
                'registered_course_id' => $rc->id,
                'matric_no' => $rc->courseRegistration->student->matric_no,
                'name' => $rc->courseRegistration->student->user->name,
                'is_carryover' => $rc->is_carryover,
                'ca_score' => $rc->result?->ca_score,
                'exam_score' => $rc->result?->exam_score,
                'total_score' => $rc->result?->total_score,
                'grade_letter' => $rc->result?->grade_letter,
                'status' => $rc->result?->status ?? 'not_entered',
                'locked' => $rc->result !== null && $rc->result->status !== ResultStatus::Pending,
            ]);

        return Inertia::render('lecturer/scoresheet', [
            'course' => $course->only('id', 'code', 'title', 'credit_units', 'ca_weight', 'exam_weight'),
            'semester' => $semester->only('id', 'name'),
            'students' => $rows,
        ]);
    }

    public function storeScores(Request $request, Course $course, GradingService $grading): RedirectResponse
    {
        $staff = $request->user()->staff()->firstOrFail();
        $semester = Semester::current();

        abort_if($semester === null, 404, 'No current semester.');
        $this->assertAllocated($staff->id, $course->id, $semester->id);

        $validated = $request->validate([
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.registered_course_id' => ['required', 'integer', 'exists:registered_courses,id'],
            'scores.*.ca_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'scores.*.exam_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $errors = [];

        foreach ($validated['scores'] as $i => $row) {
            $registered = RegisteredCourse::query()
                ->whereKey($row['registered_course_id'])
                ->where('course_id', $course->id)
                ->whereHas('courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
                ->with('result')
                ->first();

            if ($registered === null) {
                $errors["scores.{$i}"] = 'Row does not belong to this course sheet.';

                continue;
            }

            // Approved results are locked; amendments need the formal flow.
            if ($registered->result !== null && $registered->result->status !== ResultStatus::Pending) {
                $errors["scores.{$i}"] = 'Result already approved and locked.';

                continue;
            }

            try {
                $grading->grade($registered, (float) $row['ca_score'], (float) $row['exam_score'], $staff->id);
            } catch (\InvalidArgumentException $e) {
                $errors["scores.{$i}"] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return back()->withErrors($errors);
        }

        return back()->with('success', 'Scores saved.');
    }

    private function assertAllocated(int $staffId, int $courseId, int $semesterId): void
    {
        $allocated = CourseAllocation::query()
            ->where('staff_id', $staffId)
            ->where('course_id', $courseId)
            ->where('semester_id', $semesterId)
            ->exists();

        abort_unless($allocated, 403, 'You are not allocated to this course for the current semester.');
    }
}
