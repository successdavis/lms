<?php

namespace App\Services\Admissions;

use App\Enums\ApplicantStatus;
use App\Models\AdmissionList;
use App\Models\Applicant;
use App\Models\Student;
use App\Services\Academics\MatricNumberGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdmissionService
{
    public function __construct(private MatricNumberGenerator $matricNumbers) {}

    /**
     * Composite screening score on a 0-100 scale:
     * (UTME/400) x utme_weight + (Post-UTME/100) x post_utme_weight.
     * Weights are configurable per admission cycle (commonly 60/40).
     */
    public function computeAggregate(Applicant $applicant): float
    {
        $cycle = $applicant->cycle;

        if ($applicant->utme_score === null || $applicant->post_utme_score === null) {
            throw new RuntimeException('Both UTME and Post-UTME scores are required for screening.');
        }

        return round(
            ($applicant->utme_score / 400) * $cycle->utme_weight
            + ((float) $applicant->post_utme_score / 100) * $cycle->post_utme_weight,
            2,
        );
    }

    /**
     * Screen every submitted applicant that has a Post-UTME score: compute
     * the aggregate and move them to `screened`. Returns how many were screened.
     */
    public function screenCycle(int $cycleId): int
    {
        $applicants = Applicant::query()
            ->where('admission_cycle_id', $cycleId)
            ->where('status', ApplicantStatus::Submitted)
            ->whereNotNull('utme_score')
            ->whereNotNull('post_utme_score')
            ->with('cycle')
            ->get();

        foreach ($applicants as $applicant) {
            $applicant->update([
                'aggregate_score' => $this->computeAggregate($applicant),
                'status' => ApplicantStatus::Screened,
            ]);
        }

        return $applicants->count();
    }

    /**
     * Place screened applicants on an admission list. Only applicants in the
     * list's own cycle, already screened, and (unless $ignoreCutoff) meeting
     * the cycle's default cutoff are admitted; the rest are reported back.
     *
     * @param  array<int>  $applicantIds
     * @return array{admitted: int, skipped: array<string>}
     */
    public function admit(AdmissionList $list, array $applicantIds, bool $ignoreCutoff = false): array
    {
        $applicants = Applicant::query()
            ->whereIn('id', $applicantIds)
            ->with('cycle')
            ->get();

        $admitted = 0;
        $skipped = [];

        foreach ($applicants as $applicant) {
            if ($applicant->admission_cycle_id !== $list->admission_cycle_id) {
                $skipped[] = "{$applicant->application_no}: belongs to a different admission cycle.";

                continue;
            }

            if ($applicant->status !== ApplicantStatus::Screened) {
                $skipped[] = "{$applicant->application_no}: not screened (status {$applicant->status->value}).";

                continue;
            }

            $cutoff = (float) $applicant->cycle->default_cutoff;

            if (! $ignoreCutoff && (float) $applicant->aggregate_score < $cutoff) {
                $skipped[] = "{$applicant->application_no}: aggregate {$applicant->aggregate_score} below cutoff {$cutoff}.";

                continue;
            }

            $applicant->update([
                'status' => ApplicantStatus::Admitted,
                'admission_list_id' => $list->id,
                'admitted_programme_id' => $applicant->admitted_programme_id ?? $applicant->programme_id,
                'admitted_at' => now(),
            ]);
            $admitted++;
        }

        return ['admitted' => $admitted, 'skipped' => $skipped];
    }

    public function accept(Applicant $applicant): Applicant
    {
        if ($applicant->status !== ApplicantStatus::Admitted) {
            throw new RuntimeException('Only admitted applicants can accept an offer.');
        }

        $applicant->update([
            'status' => ApplicantStatus::Accepted,
            'accepted_at' => now(),
        ]);

        return $applicant;
    }

    /**
     * Convert an accepted applicant into a student: create the student record
     * on the admitted programme's entry level, generate a matriculation number,
     * and swap the user's role from applicant to student. The acceptance fee
     * (a one-off, registration-blocking fee type) lands on the student's first
     * session invoice, which gates course registration.
     */
    public function matriculate(Applicant $applicant): Student
    {
        if ($applicant->status !== ApplicantStatus::Accepted) {
            throw new RuntimeException('Only applicants who accepted their offer can be matriculated.');
        }

        return DB::transaction(function () use ($applicant) {
            $programme = $applicant->admittedProgramme ?? $applicant->programme;
            $sessionId = $applicant->cycle->academic_session_id;

            $student = Student::create([
                'user_id' => $applicant->user_id,
                'programme_id' => $programme->id,
                'level_id' => $programme->entry_level_id,
                'entry_session_id' => $sessionId,
                'entry_mode' => $applicant->entry_mode,
                'jamb_reg_no' => $applicant->jamb_reg_no,
                'gender' => $applicant->gender,
                'date_of_birth' => $applicant->date_of_birth,
                'phone' => $applicant->phone,
                'state_of_origin' => $applicant->state_of_origin,
                'lga_of_origin' => $applicant->lga_of_origin,
                'address' => $applicant->address,
            ]);

            $sequence = Student::query()
                ->where('entry_session_id', $sessionId)
                ->whereHas('programme', fn ($q) => $q->where('department_id', $programme->department_id))
                ->count();

            $student->update([
                'matric_no' => $this->matricNumbers->generate($student->fresh(['programme.department.faculty', 'entrySession']), $sequence),
            ]);

            $applicant->update([
                'status' => ApplicantStatus::Matriculated,
                'matriculated_at' => now(),
                'student_id' => $student->id,
            ]);

            $user = $applicant->user;
            $user->removeRole('applicant');
            $user->assignRole('student');

            return $student;
        });
    }

    /**
     * Matriculate every accepted applicant on a list.
     *
     * @return array{matriculated: int, skipped: array<string>}
     */
    public function matriculateList(AdmissionList $list): array
    {
        $matriculated = 0;
        $skipped = [];

        /** @var Collection<int, Applicant> $applicants */
        $applicants = $list->applicants()->where('status', ApplicantStatus::Accepted)->get();

        foreach ($applicants as $applicant) {
            try {
                $this->matriculate($applicant);
                $matriculated++;
            } catch (RuntimeException $e) {
                $skipped[] = "{$applicant->application_no}: {$e->getMessage()}";
            }
        }

        return ['matriculated' => $matriculated, 'skipped' => $skipped];
    }

    public function nextApplicationNumber(int $cycleId): string
    {
        $count = Applicant::query()->where('admission_cycle_id', $cycleId)->count() + 1;

        return 'APP-'.now()->format('Y').'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
