<?php

namespace App\Services\Academics;

use App\Models\GradeScale;
use App\Models\GradeScaleBand;
use App\Models\RegisteredCourse;
use App\Models\Result;
use InvalidArgumentException;

class GradingService
{
    /**
     * Resolve a total score (0-100) to a band on the given scale.
     */
    public function resolve(GradeScale $scale, float $score): GradeScaleBand
    {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException("Score {$score} is outside 0-100.");
        }

        $band = $scale->bands
            ->first(fn (GradeScaleBand $band) => $score >= (float) $band->min_score
                && $score <= (float) $band->max_score);

        if ($band === null) {
            throw new InvalidArgumentException(
                "No band on scale [{$scale->slug}] covers score {$score}."
            );
        }

        return $band;
    }

    /**
     * Grade a registered course from its CA and exam scores, snapshotting the
     * resolved letter/point on the result so later scale changes can't alter it.
     */
    public function grade(RegisteredCourse $registeredCourse, float $caScore, float $examScore, ?int $enteredById = null): Result
    {
        $course = $registeredCourse->course;

        if ($caScore < 0 || $caScore > $course->ca_weight) {
            throw new InvalidArgumentException(
                "CA score {$caScore} exceeds the course's CA weight of {$course->ca_weight}."
            );
        }

        if ($examScore < 0 || $examScore > $course->exam_weight) {
            throw new InvalidArgumentException(
                "Exam score {$examScore} exceeds the course's exam weight of {$course->exam_weight}."
            );
        }

        $scale = $registeredCourse->courseRegistration->student->programme->gradeScale;
        $total = round($caScore + $examScore, 2);
        $band = $this->resolve($scale, $total);

        return Result::updateOrCreate(
            ['registered_course_id' => $registeredCourse->id],
            [
                'ca_score' => $caScore,
                'exam_score' => $examScore,
                'total_score' => $total,
                'grade_letter' => $band->letter,
                'grade_point' => $band->point,
                'quality_points' => round((float) $band->point * $registeredCourse->credit_units, 2),
                'is_passed' => $band->is_pass,
                'entered_by_id' => $enteredById,
            ],
        );
    }
}
