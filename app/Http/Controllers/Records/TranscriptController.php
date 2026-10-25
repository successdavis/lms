<?php

namespace App\Http\Controllers\Records;

use App\Enums\TranscriptStatus;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\TranscriptRequest;
use App\Notifications\TranscriptUpdated;
use App\Services\Records\TranscriptBuilder;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exams & Records office: the transcript queue. Paid requests are compiled
 * from senate-approved results, signed off, then dispatched directly to the
 * receiving institution — students never handle the official copy.
 */
class TranscriptController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: TranscriptStatus::Paid->value;

        return Inertia::render('records/transcripts', [
            'requests' => TranscriptRequest::query()
                ->with(['student.user:id,name', 'student.programme:id,name', 'processedBy:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->oldest()
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'counts' => TranscriptRequest::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function update(Request $request, TranscriptRequest $transcript): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['process', 'dispatch', 'reject'])],
            'dispatch_reference' => ['required_if:action,dispatch', 'nullable', 'string', 'max:255'],
            'remarks' => ['required_if:action,reject', 'nullable', 'string', 'max:255'],
        ]);

        $allowedFrom = match ($validated['action']) {
            'process' => [TranscriptStatus::Paid],
            'dispatch' => [TranscriptStatus::Processing],
            'reject' => [TranscriptStatus::Paid, TranscriptStatus::Processing],
        };

        if (! in_array($transcript->status, $allowedFrom, true)) {
            return back()->withErrors(['transcript' => "Cannot {$validated['action']} a request that is {$transcript->status->value}."]);
        }

        $transcript->update(match ($validated['action']) {
            'process' => [
                'status' => TranscriptStatus::Processing,
                'processed_by_id' => $request->user()->id,
                'processed_at' => now(),
            ],
            'dispatch' => [
                'status' => TranscriptStatus::Dispatched,
                'dispatch_reference' => $validated['dispatch_reference'],
                'dispatched_at' => now(),
            ],
            'reject' => [
                'status' => TranscriptStatus::Rejected,
                'remarks' => $validated['remarks'],
            ],
        });

        if (in_array($validated['action'], ['dispatch', 'reject'], true)) {
            $transcript->student->user->notify(TranscriptUpdated::for($transcript->fresh()));
        }

        return back()->with('success', "Transcript {$transcript->number} updated.");
    }

    public function pdf(TranscriptRequest $transcript, TranscriptBuilder $transcripts): \Illuminate\Http\Response
    {
        abort_unless(in_array($transcript->status, [TranscriptStatus::Processing, TranscriptStatus::Dispatched], true), 403,
            'Mark the request as processing before generating the transcript.');

        $student = $transcript->student()->with(['user', 'programme.department.faculty', 'entrySession'])->firstOrFail();

        $pdf = Pdf::loadView('pdf.transcript', [
            'institution' => Institution::current(),
            'student' => $student,
            'record' => $transcripts->build($student),
            'transcript' => $transcript,
            'issuedOn' => now()->toFormattedDateString(),
            'verifyQr' => Qr::dataUri(URL::signedRoute('verify.transcript', $transcript), 2),
        ])->setPaper('a4');

        return $pdf->download("transcript-{$transcript->number}.pdf");
    }
}
