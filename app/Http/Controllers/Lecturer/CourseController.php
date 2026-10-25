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

    /**
     * Bulk score upload from a CSV with header: matric_no, ca_score, exam_score.
     * Rows are matched to this semester's registered students by matric number;
     * every bad row is reported with its line number, good rows still save.
     */
    public function storeCsv(Request $request, Course $course, GradingService $grading): RedirectResponse
    {
        $staff = $request->user()->staff()->firstOrFail();
        $semester = Semester::current();

        abort_if($semester === null, 404, 'No current semester.');
        $this->assertAllocated($staff->id, $course->id, $semester->id);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);

        $required = ['matric_no', 'ca_score', 'exam_score'];
        if (array_diff($required, $header) !== []) {
            fclose($handle);

            return back()->withErrors(['file' => 'CSV header must contain: matric_no, ca_score, exam_score.']);
        }

        $registered = RegisteredCourse::query()
            ->where('course_id', $course->id)
            ->whereHas('courseRegistration', fn ($q) => $q->where('semester_id', $semester->id))
            ->with(['courseRegistration.student:id,matric_no,programme_id', 'result'])
            ->get()
            ->keyBy(fn (RegisteredCourse $rc) => $rc->courseRegistration->student->matric_no);

        $errors = [];
        $saved = 0;
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($row === [null] || $row === ['']) {
                continue; // blank line
            }

            $data = array_combine($header, array_pad(array_map('trim', $row), count($header), ''));
            $matric = $data['matric_no'] ?? '';
            $rc = $registered->get($matric);

            if ($rc === null) {
                $errors["csv.{$line}"] = "Line {$line}: {$matric} is not registered for {$course->code} this semester.";

                continue;
            }

            if ($rc->result !== null && $rc->result->status !== ResultStatus::Pending) {
                $errors["csv.{$line}"] = "Line {$line}: {$matric} result already approved and locked.";

                continue;
            }

            if (! is_numeric($data['ca_score']) || ! is_numeric($data['exam_score'])) {
                $errors["csv.{$line}"] = "Line {$line}: CA and exam scores must be numeric.";

                continue;
            }

            try {
                $grading->grade($rc, (float) $data['ca_score'], (float) $data['exam_score'], $staff->id);
                $saved++;
            } catch (\InvalidArgumentException $e) {
                $errors["csv.{$line}"] = "Line {$line}: {$e->getMessage()}";
            }
        }

        fclose($handle);

        if ($errors !== []) {
            return back()
                ->with($saved > 0 ? 'success' : 'error', "{$saved} score(s) saved; ".count($errors).' row(s) rejected.')
                ->withErrors($errors);
        }

        return back()->with('success', "{$saved} score(s) saved from CSV.");
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
