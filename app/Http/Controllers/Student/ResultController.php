<?php

namespace App\Http\Controllers\Student;

use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseRegistration;
use App\Services\Academics\ResultComputationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    /**
     * Semester-by-semester results. Students only ever see courses whose
     * results have final (senate/academic board) approval.
     */
    public function index(Request $request, ResultComputationService $computer): Response
    {
        $student = $request->user()->student()->with('programme.gradeScale.classificationBands')->firstOrFail();

        $registrations = CourseRegistration::query()
            ->where('student_id', $student->id)
            ->with([
                'semester.academicSession',
                'registeredCourses.course:id,code,title',
                'registeredCourses.result',
            ])
            ->get()
            ->sortBy('semester.id')
            ->values();

        $snapshots = $student->semesterResults()->get()->keyBy('semester_id');

        $semesters = $registrations->map(function (CourseRegistration $registration) use ($snapshots) {
            $approved = $registration->registeredCourses
                ->filter(fn ($rc) => $rc->result?->status === ResultStatus::SenateApproved)
                ->map(fn ($rc) => [
                    'code' => $rc->course->code,
                    'title' => $rc->course->title,
                    'credit_units' => $rc->credit_units,
                    'is_carryover' => $rc->is_carryover,
                    'total_score' => $rc->result->total_score,
                    'grade_letter' => $rc->result->grade_letter,
                    'grade_point' => $rc->result->grade_point,
                    'quality_points' => $rc->result->quality_points,
                    'is_passed' => $rc->result->is_passed,
                ])
                ->values();

            $snapshot = $snapshots->get($registration->semester_id);

            return [
                'semester' => $registration->semester->name,
                'session' => $registration->semester->academicSession->name,
                'courses' => $approved,
                'gpa' => $snapshot?->gpa,
                'cgpa' => $snapshot?->cgpa,
                'standing' => $snapshot?->standing,
            ];
        })->filter(fn ($row) => count($row['courses']) > 0)->values();

        $latest = $student->semesterResults()->latest('id')->first();
        $classification = $latest
            ? $computer->classify($student->programme->gradeScale, (float) $latest->cgpa)?->name
            : null;

        return Inertia::render('student/results', [
            'semesters' => $semesters,
            'cgpa' => $latest?->cgpa,
            'classification' => $classification,
        ]);
    }
}
