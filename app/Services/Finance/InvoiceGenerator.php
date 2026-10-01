<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceGenerator
{
    /**
     * Build a student's invoice for a session from the fee structures that
     * match them. For each fee type the most specific matching rule wins
     * (programme > level > entry mode > indigene scope).
     */
    public function generateFor(Student $student, AcademicSession $session): Invoice
    {
        $structures = FeeStructure::query()
            ->where('academic_session_id', $session->id)
            ->where(fn ($q) => $q->whereNull('programme_id')->orWhere('programme_id', $student->programme_id))
            ->where(fn ($q) => $q->whereNull('level_id')->orWhere('level_id', $student->level_id))
            ->where(fn ($q) => $q->whereNull('entry_mode')->orWhere('entry_mode', $student->entry_mode->value))
            ->where(function ($q) use ($student) {
                $q->whereNull('indigene_scope')
                    ->orWhere('indigene_scope', $student->is_indigene ? 'indigene' : 'non_indigene');
            })
            ->with('feeType')
            ->get();

        $applicable = $structures
            ->filter(fn (FeeStructure $s) => $s->feeType->is_recurring || $this->neverCharged($student, $s->fee_type_id))
            ->groupBy('fee_type_id')
            ->map(fn ($rules) => $rules->sortByDesc(fn (FeeStructure $s) => $s->specificity())->first());

        if ($applicable->isEmpty()) {
            throw new RuntimeException('No fee structures match this student for the session.');
        }

        return DB::transaction(function () use ($student, $session, $applicable) {
            $invoice = Invoice::create([
                'number' => $this->nextNumber($session),
                'student_id' => $student->id,
                'academic_session_id' => $session->id,
                'total' => $applicable->sum(fn (FeeStructure $s) => (float) $s->amount),
                'status' => InvoiceStatus::Unpaid,
            ]);

            foreach ($applicable as $structure) {
                $invoice->items()->create([
                    'fee_type_id' => $structure->fee_type_id,
                    'description' => $structure->feeType->name,
                    'amount' => $structure->amount,
                ]);
            }

            return $invoice->load('items');
        });
    }

    /**
     * Apply a successful payment to its invoice and refresh the status.
     */
    public function applyPayment(\App\Models\Payment $payment): Invoice
    {
        $invoice = $payment->invoice;

        $paid = $invoice->payments()
            ->where('status', \App\Enums\PaymentStatus::Successful)
            ->sum('amount');

        $invoice->amount_paid = $paid;
        $invoice->status = match (true) {
            (float) $paid >= (float) $invoice->total => InvoiceStatus::Paid,
            (float) $paid > 0 => InvoiceStatus::PartPaid,
            default => InvoiceStatus::Unpaid,
        };
        $invoice->save();

        return $invoice;
    }

    private function neverCharged(Student $student, int $feeTypeId): bool
    {
        return ! $student->invoices()
            ->whereHas('items', fn ($q) => $q->where('fee_type_id', $feeTypeId))
            ->exists();
    }

    private function nextNumber(AcademicSession $session): string
    {
        $prefix = 'INV-'.str_replace('/', '', $session->name).'-';

        return $prefix.str_pad((string) (Invoice::count() + 1), 6, '0', STR_PAD_LEFT);
    }
}
