<?php

namespace App\Services\Hostels;

use App\Enums\AllocationStatus;
use App\Enums\InvoicePurpose;
use App\Enums\InvoiceStatus;
use App\Models\AcademicSession;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\Institution;
use App\Models\Student;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HostelService
{
    public function __construct(private InvoiceGenerator $invoices) {}

    /**
     * Release reservations whose payment window has passed, cancelling their
     * unpaid invoices so they can no longer be paid against a lost bed.
     */
    public function expireLapsed(): int
    {
        $lapsed = HostelAllocation::query()
            ->where('status', AllocationStatus::Reserved)
            ->where('expires_at', '<=', now())
            ->with('invoice')
            ->get();

        foreach ($lapsed as $allocation) {
            $allocation->update(['status' => AllocationStatus::Cancelled]);

            if ($allocation->invoice && $allocation->invoice->status === InvoiceStatus::Unpaid) {
                $allocation->invoice->update(['status' => InvoiceStatus::Cancelled]);
            }
        }

        return $lapsed->count();
    }

    public function activeAllocation(Student $student, int $sessionId): ?HostelAllocation
    {
        return $student->hostelAllocations()
            ->where('academic_session_id', $sessionId)
            ->whereIn('status', [AllocationStatus::Reserved, AllocationStatus::Confirmed])
            ->with(['room.hostel', 'invoice:id,number,status,total'])
            ->latest('id')
            ->first();
    }

    /**
     * Reserve the first free bed space in the hostel and raise its invoice.
     */
    public function book(Student $student, Hostel $hostel): HostelAllocation
    {
        $session = AcademicSession::current() ?? throw new RuntimeException('No current academic session.');

        $this->expireLapsed();

        if (! $hostel->is_active) {
            throw new RuntimeException('This hostel is not open for booking.');
        }

        if (! $hostel->acceptsGender($student->gender)) {
            throw new RuntimeException("{$hostel->name} does not accommodate your gender.");
        }

        if ($this->activeAllocation($student, $session->id) !== null) {
            throw new RuntimeException('You already hold a bed space this session.');
        }

        $this->assertSchoolFeesCleared($student, $session);

        return DB::transaction(function () use ($student, $hostel, $session) {
            $room = $hostel->rooms()
                ->orderBy('number')
                ->lockForUpdate()
                ->get()
                ->first(fn ($room) => $room->hasSpace($session->id));

            if ($room === null) {
                throw new RuntimeException("{$hostel->name} is fully booked.");
            }

            $invoice = $this->invoices->charge(
                $student,
                $session,
                InvoicePurpose::Hostel,
                'HST',
                'Hostel Accommodation Fee',
                (float) $hostel->fee,
                "Hostel: {$hostel->name}, Room {$room->number} ({$session->name})",
            );

            return HostelAllocation::create([
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'academic_session_id' => $session->id,
                'invoice_id' => $invoice->id,
                'status' => AllocationStatus::Reserved,
                'expires_at' => now()->addHours(config('lms.hostel.reservation_hours')),
            ])->load(['room.hostel', 'invoice']);
        });
    }

    public function cancel(HostelAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            $allocation->update(['status' => AllocationStatus::Cancelled]);

            if ($allocation->invoice && $allocation->invoice->status === InvoiceStatus::Unpaid) {
                $allocation->invoice->update(['status' => InvoiceStatus::Cancelled]);
            }
        });
    }

    /**
     * Most institutions allocate bed spaces only to students who have paid
     * school fees (to the same threshold that opens course registration).
     */
    private function assertSchoolFeesCleared(Student $student, AcademicSession $session): void
    {
        $minPercent = Institution::current()?->min_fee_percent_for_registration ?? 100;

        $schoolFees = $student->invoices()
            ->where('academic_session_id', $session->id)
            ->where('purpose', InvoicePurpose::SchoolFees)
            ->first();

        if ($schoolFees === null || $schoolFees->percentPaid() < $minPercent) {
            throw new RuntimeException('Pay your school fees before booking accommodation.');
        }
    }
}
