<?php

namespace App\Services\Records;

use App\Enums\ClearanceItemStatus;
use App\Enums\ClearanceUnit;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\ClearanceItem;
use App\Models\ClearanceRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClearanceService
{
    public function start(Student $student): ClearanceRequest
    {
        $student->loadMissing('level', 'programme');

        if (! $student->isInFinalYear()) {
            throw new RuntimeException('Final-year clearance opens in your final year of study.');
        }

        if (in_array($student->status, [StudentStatus::Withdrawn, StudentStatus::Suspended], true)) {
            throw new RuntimeException('Your studentship status does not permit clearance.');
        }

        if ($student->clearanceRequest()->exists()) {
            throw new RuntimeException('You have already started clearance.');
        }

        return DB::transaction(function () use ($student) {
            $request = $student->clearanceRequest()->create(['status' => 'in_progress']);

            foreach (ClearanceUnit::cases() as $unit) {
                $request->items()->create(['unit' => $unit]);
            }

            return $request->load('items');
        });
    }

    /**
     * Whether this user may sign for this item: registrar/super-admin for any
     * unit; otherwise the unit's role, scoped to the student's own department
     * (HOD) or faculty (Dean).
     */
    public function canReview(User $user, ClearanceItem $item): bool
    {
        if ($user->hasAnyRole(['registrar', 'super-admin'])) {
            return true;
        }

        if (! $user->hasRole($item->unit->role())) {
            return false;
        }

        $department = $item->clearanceRequest->student->programme->department;

        return match ($item->unit) {
            ClearanceUnit::Department => $user->staff?->department_id === $department->id,
            ClearanceUnit::Faculty => $user->staff?->department?->faculty_id === $department->faculty_id,
            default => true,
        };
    }

    /**
     * Facts a unit must not clear against; empty when the unit is free to clear.
     *
     * @return list<string>
     */
    public function blockers(ClearanceItem $item): array
    {
        $student = $item->clearanceRequest->student;

        return match ($item->unit) {
            ClearanceUnit::Bursary => $student->invoices()
                ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
                ->get()
                ->map(fn ($invoice) => 'Outstanding balance ₦'.number_format($invoice->balance(), 2)." on {$invoice->number}")
                ->all(),
            ClearanceUnit::Department => $student->outstandingCarryovers()
                ->map(fn ($course) => "Outstanding carryover: {$course->code}")
                ->all(),
            default => [],
        };
    }

    public function review(User $user, ClearanceItem $item, string $action, ?string $note = null): ClearanceItem
    {
        if (! $this->canReview($user, $item)) {
            throw new RuntimeException('You are not authorised to sign for this unit.');
        }

        if ($item->status === ClearanceItemStatus::Cleared) {
            throw new RuntimeException('This unit has already cleared the student.');
        }

        if ($action === 'clear' && ($blockers = $this->blockers($item)) !== []) {
            throw new RuntimeException('Cannot clear: '.implode('; ', $blockers).'.');
        }

        return DB::transaction(function () use ($user, $item, $action, $note) {
            $item->update([
                'status' => $action === 'clear' ? ClearanceItemStatus::Cleared : ClearanceItemStatus::Rejected,
                'note' => $note,
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            $request = $item->clearanceRequest;
            $allCleared = $request->items()->where('status', '!=', ClearanceItemStatus::Cleared)->doesntExist();

            if ($allCleared) {
                $request->update(['status' => 'cleared', 'cleared_at' => now()]);
            }

            return $item;
        });
    }

    /**
     * After resolving a rejection, the student sends rejected units back for review.
     */
    public function resubmit(ClearanceRequest $request): int
    {
        $rejected = $request->items()->where('status', ClearanceItemStatus::Rejected)->get();

        $rejected->each->update(['status' => ClearanceItemStatus::Pending, 'reviewed_by_id' => null, 'reviewed_at' => null]);

        return $rejected->count();
    }
}
