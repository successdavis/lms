<?php

namespace App\Services\Records;

use App\Enums\ResultStatus;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Services\Academics\ResultComputationService;

/**
 * Assembles a student's academic record from senate/academic-board approved
 * results only. Used for both the on-screen results page and the official
 * transcript, so the two can never disagree.
 */
class TranscriptBuilder
{
    public function __construct(private ResultComputationService $computer) {}

    /**
     * @return array{
     *     semesters: list<array{semester: string, session: string, courses: list<array<string, mixed>>, total_units: int, gpa: ?string, cgpa: ?string, standing: mixed}>,
     *     cgpa: ?string,
     *     cumulative_units: int,
     *     classification: ?string,
     *     grade_key: list<array{letter: string, range: string, point: string}>,
     * }
     */
    public function build(Student $student): array
    {
        $student->loadMissing('programme.gradeScale.bands', 'programme.gradeScale.classificationBands');

        $registrations = CourseRegistration::query()
            ->where('student_id', $student->id)
            ->with([
                'semester.academicSession',
                'registeredCourses.course:id,code,title',
                'registeredCourses.result',
            ])
            ->get()
            ->sortBy(fn (CourseRegistration $r) => [$r->semester->academicSession->name, $r->semester->number])
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
                ->values()
                ->all();

            $snapshot = $snapshots->get($registration->semester_id);

            return [
                'semester' => $registration->semester->name,
                'session' => $registration->semester->academicSession->name,
                'courses' => $approved,
                'total_units' => array_sum(array_column($approved, 'credit_units')),
                'gpa' => $snapshot?->gpa,
                'cgpa' => $snapshot?->cgpa,
                'standing' => $snapshot?->standing,
            ];
        })->filter(fn ($row) => count($row['courses']) > 0)->values()->all();

        $latest = $student->semesterResults()->latest('id')->first();
        $scale = $student->programme->gradeScale;

        return [
            'semesters' => $semesters,
            'cgpa' => $latest?->cgpa,
            'cumulative_units' => (int) ($latest?->cumulative_units ?? 0),
            'classification' => $latest ? $this->computer->classify($scale, (float) $latest->cgpa)?->name : null,
            'grade_key' => $scale->bands->map(fn ($band) => [
                'letter' => $band->letter,
                'range' => rtrim(rtrim((string) $band->min_score, '0'), '.').'–'.floor((float) $band->max_score),
                'point' => (string) $band->point,
            ])->all(),
        ];
    }
}
