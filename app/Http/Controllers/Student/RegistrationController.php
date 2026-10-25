<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\RegistrationException;
use App\Http\Controllers\Controller;
use App\Models\ProgrammeCourse;
use App\Models\Semester;
use App\Services\Academics\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()->student()->with('programme')->firstOrFail();
        $semester = Semester::current();

        $curriculum = $semester
            ? ProgrammeCourse::query()
                ->where('programme_id', $student->programme_id)
                ->where('level_id', $student->level_id)
                ->where('semester_number', $semester->number)
                ->with('course:id,code,title,credit_units,semester_number')
                ->get()
                ->map(fn (ProgrammeCourse $pc) => [
                    'id' => $pc->course->id,
                    'code' => $pc->course->code,
                    'title' => $pc->course->title,
                    'credit_units' => $pc->course->credit_units,
                    'type' => $pc->type,
                ])
            : collect();

        $carryovers = $semester
            ? $student->outstandingCarryovers()
                ->filter(fn ($course) => $course->semester_number === $semester->number)
                ->map(fn ($course) => $course->only('id', 'code', 'title', 'credit_units'))
                ->values()
            : collect();

        $registration = $semester
            ? $student->courseRegistrations()
                ->where('semester_id', $semester->id)
                ->with('registeredCourses.course:id,code,title')
                ->first()
            : null;

        return Inertia::render('student/registration', [
            'semester' => $semester?->only('id', 'name'),
            'registrationOpen' => $semester?->isRegistrationOpen() ?? false,
            'curriculum' => $curriculum,
            'carryovers' => $carryovers,
            'registration' => $registration,
            'limits' => [
                'min' => $student->programme->min_units_per_semester,
                'max' => $student->programme->max_units_per_semester,
            ],
        ]);
    }

    public function store(Request $request, RegistrationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ]);

        $student = $request->user()->student()->firstOrFail();
        $semester = Semester::current();

        if ($semester === null) {
            return back()->withErrors(['registration' => 'No current semester is configured.']);
        }

        try {
            $service->register($student, $semester, $validated['course_ids']);
        } catch (RegistrationException $e) {
            return back()->withErrors(['registration' => $e->getMessage()]);
        }

        return back()->with('success', 'Course registration submitted.');
    }
}
