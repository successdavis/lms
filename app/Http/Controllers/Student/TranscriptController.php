<?php

namespace App\Http\Controllers\Student;

use App\Enums\InvoicePurpose;
use App\Enums\TranscriptStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\TranscriptRequest;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TranscriptController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()->student()->firstOrFail();

        return Inertia::render('student/transcripts', [
            'requests' => $student->transcriptRequests()
                ->with('invoice:id,number,status')
                ->latest()
                ->get(),
            'fees' => config('lms.transcript_fees'),
            'hasApprovedResults' => $student->semesterResults()->exists(),
        ]);
    }

    public function store(Request $request, InvoiceGenerator $invoices): RedirectResponse
    {
        $validated = $request->validate([
            'destination' => ['required', Rule::in(['local', 'international'])],
            'delivery' => ['required', Rule::in(['e_copy', 'hard_copy'])],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['required_if:delivery,e_copy', 'nullable', 'email', 'max:255'],
            'recipient_address' => ['required_if:delivery,hard_copy', 'nullable', 'string', 'max:1000'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        $student = $request->user()->student()->firstOrFail();
        $session = AcademicSession::current();

        if ($session === null) {
            return back()->withErrors(['transcript' => 'No current academic session is configured.']);
        }

        if (! $student->semesterResults()->exists()) {
            return back()->withErrors(['transcript' => 'You have no approved results to put on a transcript yet.']);
        }

        $fee = (float) config("lms.transcript_fees.{$validated['destination']}.{$validated['delivery']}");

        DB::transaction(function () use ($student, $session, $validated, $fee, $invoices) {
            $transcript = TranscriptRequest::create($validated + [
                'number' => 'TR-'.now()->format('Y').'-'.str_pad((string) (TranscriptRequest::count() + 1), 5, '0', STR_PAD_LEFT),
                'student_id' => $student->id,
                'fee' => $fee,
                'status' => TranscriptStatus::AwaitingPayment,
            ]);

            $invoice = $invoices->charge(
                $student,
                $session,
                InvoicePurpose::Transcript,
                'TRN',
                'Transcript Fee',
                $fee,
                "Transcript {$transcript->number} ({$validated['destination']}, ".str_replace('_', '-', $validated['delivery']).')',
            );

            $transcript->update(['invoice_id' => $invoice->id]);
        });

        return back()->with('success', 'Transcript request created. Pay the transcript fee to send it to the records office.');
    }
}
