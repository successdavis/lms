<?php

namespace App\Services\Hr;

use App\Enums\StaffType;
use App\Events\StaffPromoted;
use App\Models\Institution;
use App\Models\Promotion;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\PromotionDecided;
use App\Support\StaffRanks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PromotionService
{
    /**
     * HR/registry may recommend anyone; an HOD only staff in their department.
     */
    public function canRecommend(User $user, Staff $staff): bool
    {
        if ($user->hasAnyRole(['hr-officer', 'registrar', 'super-admin'])) {
            return true;
        }

        return $user->hasRole('hod')
            && $user->staff?->department_id !== null
            && $user->staff->department_id === $staff->department_id
            && $user->staff->id !== $staff->id;
    }

    /**
     * Years in the current rank, from rank_since (falling back to the
     * employment date for staff never promoted in the system).
     */
    public function yearsInRank(Staff $staff, Carbon $asOf): float
    {
        $since = $staff->rank_since ?? $staff->employed_on;

        return $since === null ? 0.0 : $since->diffInDays($asOf) / 365.25;
    }

    /**
     * The proposed move: next academic rank for academic staff, one grade
     * level up for everyone else.
     *
     * @return array{to_designation: ?string, to_grade_level: int}
     */
    public function proposal(Staff $staff): array
    {
        $type = Institution::current()?->type;

        return [
            'to_designation' => $staff->type === StaffType::Academic
                ? StaffRanks::next($type, $staff->designation)
                : $staff->designation,
            'to_grade_level' => (int) ($staff->grade_level ?? 0) + 1,
        ];
    }

    public function recommend(User $by, Staff $staff, Carbon $effectiveOn, ?int $toGradeLevel = null, ?string $justification = null): Promotion
    {
        if (! $this->canRecommend($by, $staff)) {
            throw new RuntimeException('You may not recommend this staff member for promotion.');
        }

        if ($staff->promotions()->where('status', 'pending')->exists()) {
            throw new RuntimeException('A promotion for this staff member is already awaiting a decision.');
        }

        $proposal = $this->proposal($staff);

        if ($staff->type === StaffType::Academic && $proposal['to_designation'] === null) {
            throw new RuntimeException("{$staff->designation} has no higher rank on the ladder.");
        }

        $minYears = config('lms.hr.min_years_in_rank');
        $years = $this->yearsInRank($staff, $effectiveOn);

        if ($years < $minYears) {
            throw new RuntimeException(sprintf('Not yet eligible: %.1f year(s) in rank by the effective date; %d required.', $years, $minYears));
        }

        return $staff->promotions()->create([
            'from_designation' => $staff->designation,
            'to_designation' => $proposal['to_designation'],
            'from_grade_level' => $staff->grade_level,
            'to_grade_level' => $toGradeLevel ?? $proposal['to_grade_level'],
            'effective_on' => $effectiveOn,
            'justification' => $justification,
            'recommended_by_id' => $by->id,
        ]);
    }

    /**
     * Approve or reject. Approval needs a second person (not the recommender)
     * and applies the new rank/grade level to the staff record.
     */
    public function decide(User $by, Promotion $promotion, bool $approve, ?string $note = null): Promotion
    {
        if (! $by->hasAnyRole(['hr-officer', 'registrar', 'super-admin'])) {
            throw new RuntimeException('Only HR or the registry can decide promotions.');
        }

        if ($promotion->status !== 'pending') {
            throw new RuntimeException('This promotion has already been decided.');
        }

        if ($promotion->recommended_by_id === $by->id) {
            throw new RuntimeException('A promotion must be approved by someone other than its recommender.');
        }

        DB::transaction(function () use ($by, $promotion, $approve, $note) {
            $promotion->update([
                'status' => $approve ? 'approved' : 'rejected',
                'note' => $note,
                'decided_by_id' => $by->id,
                'decided_at' => now(),
            ]);

            if ($approve) {
                $promotion->staff->update([
                    'designation' => $promotion->to_designation,
                    'grade_level' => $promotion->to_grade_level,
                    'step' => 1,
                    'rank_since' => $promotion->effective_on,
                ]);
            }
        });

        if ($approve) {
            StaffPromoted::dispatch($promotion);
        }

        $promotion->staff->user->notify(PromotionDecided::for($promotion->fresh()));

        return $promotion;
    }

    /**
     * Annual increment: every active staff member moves one step up their
     * grade level, capped at the structure's top step.
     */
    public function annualStepIncrement(): int
    {
        $maxStep = config('lms.hr.max_step');
        $count = 0;

        Staff::query()
            ->where('is_active', true)
            ->whereNotNull('grade_level')
            ->where(fn ($q) => $q->whereNull('step')->orWhere('step', '<', $maxStep))
            ->get()
            ->each(function (Staff $staff) use (&$count) {
                $staff->update(['step' => (int) ($staff->step ?? 0) + 1]);
                $count++;
            });

        return $count;
    }
}
