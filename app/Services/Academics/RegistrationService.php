<?php

namespace App\Services\Academics;

use App\Enums\RegistrationStatus;
use App\Exceptions\RegistrationException;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Institution;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    /**
     * Register a student's courses for a semester, enforcing:
     *  - the registration window,
     *  - the fee gate (registration-blocking invoices paid to the minimum %),
     *  - carryovers-first (outstanding failed courses offered this semester
     *    must be included before new courses),
     *  - the programme's min/max credit unit load,
     *  - prerequisites (must have passed each prerequisite course).
     *
     * @param  array<int>  $courseIds
     */
    public function register(Student $student, Semester $semester, array $courseIds): CourseRegistration
    {
        if (! $semester->isRegistrationOpen()) {
            throw new RegistrationException('Course registration is not open for this semester.');
        }

        $this->assertFeesCleared($student, $semester);

        $courses = Course::query()->whereIn('id', $courseIds)->get();

        if ($courses->count() !== count(array_unique($courseIds))) {
            throw new RegistrationException('One or more selected courses do not exist.');
        }

        $carryovers = $student->outstandingCarryovers()
            ->filter(fn (Course $course) => $course->semester_number === $semester->number);

        $missingCarryovers = $carryovers->reject(fn (Course $course) => $courses->contains('id', $course->id));
        if ($missingCarryovers->isNotEmpty()) {
            throw new RegistrationException(
                'Carryover courses must be registered first: '
                .$missingCarryovers->pluck('code')->join(', ')
            );
        }

        $programme = $student->programme;
        $totalUnits = $courses->sum('credit_units');

        if ($totalUnits > $programme->max_units_per_semester) {
            throw new RegistrationException(
                "Total units ({$totalUnits}) exceed the maximum of {$programme->max_units_per_semester}."
            );
        }

        if ($totalUnits < $programme->min_units_per_semester) {
            throw new RegistrationException(
                "Total units ({$totalUnits}) are below the minimum of {$programme->min_units_per_semester}."
            );
        }

        $this->assertPrerequisitesMet($student, $courses);

        $carryoverIds = $carryovers->pluck('id')->all();

        return DB::transaction(function () use ($student, $semester, $courses, $totalUnits, $carryoverIds) {
            $registration = CourseRegistration::updateOrCreate(
                ['student_id' => $student->id, 'semester_id' => $semester->id],
                [
                    'level_id' => $student->level_id,
                    'status' => RegistrationStatus::Submitted,
                    'total_units' => $totalUnits,
                    'submitted_at' => now(),
                ],
            );

            $registration->registeredCourses()->delete();

            foreach ($courses as $course) {
                $registration->registeredCourses()->create([
                    'course_id' => $course->id,
                    'credit_units' => $course->credit_units,
                    'is_carryover' => in_array($course->id, $carryoverIds, true),
                ]);
            }

            return $registration->load('registeredCourses');
        });
    }

    private function assertFeesCleared(Student $student, Semester $semester): void
    {
        $minPercent = Institution::current()?->min_fee_percent_for_registration ?? 100;

        $blockingInvoices = $student->invoices()
            ->where('academic_session_id', $semester->academic_session_id)
            ->whereHas('items.feeType', fn ($q) => $q->where('blocks_registration', true))
            ->get();

        foreach ($blockingInvoices as $invoice) {
            if ($invoice->percentPaid() < $minPercent) {
                throw new RegistrationException(
                    "Invoice {$invoice->number} must be at least {$minPercent}% paid before registration."
                );
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     */
    private function assertPrerequisitesMet(Student $student, $courses): void
    {
        $passedCourseIds = \App\Models\Result::query()
            ->where('is_passed', true)
            ->whereHas('registeredCourse.courseRegistration', fn ($q) => $q->where('student_id', $student->id))
            ->with('registeredCourse:id,course_id')
            ->get()
            ->pluck('registeredCourse.course_id')
            ->all();

        foreach ($courses as $course) {
            $unmet = $course->prerequisites->reject(
                fn (Course $prerequisite) => in_array($prerequisite->id, $passedCourseIds, true)
            );

            if ($unmet->isNotEmpty()) {
                throw new RegistrationException(
                    "{$course->code} requires passing: {$unmet->pluck('code')->join(', ')}"
                );
            }
        }
    }
}
