<?php

namespace App\Services\Academics;

use App\Enums\AcademicStanding;
use App\Models\ClassificationBand;
use App\Models\CourseRegistration;
use App\Models\GradeScale;
use App\Models\Semester;
use App\Models\SemesterResult;
use App\Models\Student;
use RuntimeException;

class ResultComputationService
{
    /**
     * Compute (or recompute) a student's GPA/CGPA snapshot for a semester.
     *
     * GPA  = TCP / TNU for the semester, where TNU counts every registered
     *        unit (failed courses drag the average — NUC/NBTE standard).
     * CGPA = cumulative TCP / cumulative TNU across all prior semesters.
     */
    public function computeSemester(Student $student, Semester $semester): SemesterResult
    {
        $registration = CourseRegistration::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->with('registeredCourses.result')
            ->first();

        if ($registration === null) {
            throw new RuntimeException('Student has no course registration for this semester.');
        }

        $totalUnits = 0;
        $totalQualityPoints = 0.0;

        foreach ($registration->registeredCourses as $registered) {
            if ($registered->result === null || $registered->result->total_score === null) {
                throw new RuntimeException(
                    "Course {$registered->course->code} has no graded result yet."
                );
            }

            $totalUnits += $registered->credit_units;
            $totalQualityPoints += (float) $registered->result->quality_points;
        }

        if ($totalUnits === 0) {
            throw new RuntimeException('No registered units to compute.');
        }

        $previous = $this->previousSnapshot($student, $semester);

        $cumulativeUnits = ($previous?->cumulative_units ?? 0) + $totalUnits;
        $cumulativeQualityPoints = (float) ($previous?->cumulative_quality_points ?? 0) + $totalQualityPoints;

        $gpa = $this->truncate($totalQualityPoints / $totalUnits);
        $cgpa = $this->truncate($cumulativeQualityPoints / $cumulativeUnits);

        $scale = $student->programme->gradeScale;
        $standing = $this->determineStanding($scale, $cgpa, $previous?->standing);

        return SemesterResult::updateOrCreate(
            ['student_id' => $student->id, 'semester_id' => $semester->id],
            [
                'total_units' => $totalUnits,
                'total_quality_points' => round($totalQualityPoints, 2),
                'gpa' => $gpa,
                'cumulative_units' => $cumulativeUnits,
                'cumulative_quality_points' => round($cumulativeQualityPoints, 2),
                'cgpa' => $cgpa,
                'standing' => $standing,
            ],
        );
    }

    /**
     * Map a CGPA to its final award classification on the programme's scale
     * (First Class ... Pass for NUC, Distinction ... Pass for NBTE).
     */
    public function classify(GradeScale $scale, float $cgpa): ?ClassificationBand
    {
        return $scale->classificationBands
            ->first(fn (ClassificationBand $band) => $cgpa >= (float) $band->min_cgpa
                && $cgpa <= (float) $band->max_cgpa);
    }

    /**
     * Probation when CGPA drops below the scale's threshold; withdrawal when a
     * student already on probation stays below it (NBTE two-consecutive rule),
     * or falls below the scale's hard withdrawal CGPA where one is set.
     */
    public function determineStanding(GradeScale $scale, float $cgpa, ?AcademicStanding $previous): AcademicStanding
    {
        if ($cgpa >= (float) $scale->probation_cgpa) {
            return AcademicStanding::Good;
        }

        if ($previous === AcademicStanding::Probation || $previous === AcademicStanding::Withdrawal) {
            return AcademicStanding::Withdrawal;
        }

        if ($scale->withdrawal_cgpa !== null && $cgpa < (float) $scale->withdrawal_cgpa) {
            return AcademicStanding::Withdrawal;
        }

        return AcademicStanding::Probation;
    }

    private function previousSnapshot(Student $student, Semester $semester): ?SemesterResult
    {
        return SemesterResult::query()
            ->where('student_id', $student->id)
            ->whereHas('semester', function ($query) use ($semester) {
                $query->where(function ($q) use ($semester) {
                    $q->where('starts_on', '<', $semester->starts_on)
                        ->orWhere(function ($q2) use ($semester) {
                            // Fall back to id ordering when dates are not set.
                            $q2->whereNull('starts_on')->where('id', '<', $semester->id);
                        });
                });
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Nigerian institutions typically truncate (not round) GPA to 2 d.p.
     */
    private function truncate(float $value): float
    {
        return floor($value * 100) / 100;
    }
}
