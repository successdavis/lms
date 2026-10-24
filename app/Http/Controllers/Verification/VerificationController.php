<?php

namespace App\Http\Controllers\Verification;

use App\Enums\TranscriptStatus;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Student;
use App\Models\TranscriptRequest;
use App\Services\Records\TranscriptBuilder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, signed verification pages behind the QR codes on ID cards and
 * transcripts. A forged or altered link fails the signature check; a
 * genuine one shows the record's live state, so a withdrawn student's card
 * no longer verifies as active.
 */
class VerificationController extends Controller
{
    public function student(Student $student): Response
    {
        $student->load(['user:id,name', 'programme.department.faculty', 'entrySession']);

        return Inertia::render('verify/student', [
            'institution' => Institution::current()?->name,
            'student' => [
                'name' => $student->user->name,
                'matric_no' => $student->matric_no,
                'programme' => $student->programme->name,
                'department' => $student->programme->department->name,
                'faculty' => $student->programme->department->faculty->name,
                'status' => $student->status->value,
                'valid_until' => $student->idCardValidUntil()->format('F Y'),
                'graduated_on' => $student->graduated_on?->toFormattedDateString(),
            ],
            'photoUrl' => $student->passport_path ? URL::signedRoute('verify.student.photo', $student) : null,
            'checkedAt' => now()->toDayDateTimeString(),
        ]);
    }

    public function studentPhoto(Student $student): StreamedResponse
    {
        abort_if($student->passport_path === null, 404);

        return Storage::disk('local')->response($student->passport_path);
    }

    public function transcript(TranscriptRequest $transcript, TranscriptBuilder $transcripts): Response
    {
        $student = $transcript->student()->with(['user:id,name', 'programme'])->firstOrFail();
        $issued = in_array($transcript->status, [TranscriptStatus::Processing, TranscriptStatus::Dispatched], true);
        $record = $issued ? $transcripts->build($student) : null;

        return Inertia::render('verify/transcript', [
            'institution' => Institution::current()?->name,
            'transcript' => [
                'number' => $transcript->number,
                'status' => $transcript->status->value,
                'issued' => $issued,
                'recipient' => $transcript->recipient_name,
                'processed_at' => $transcript->processed_at?->toFormattedDateString(),
                'dispatched_at' => $transcript->dispatched_at?->toFormattedDateString(),
            ],
            'student' => [
                'name' => $student->user->name,
                'matric_no' => $student->matric_no,
                'programme' => $student->programme->name,
                'graduated_on' => $student->graduated_on?->toFormattedDateString(),
                'cgpa' => $record['cgpa'] ?? null,
                'classification' => $record['classification'] ?? null,
            ],
            'checkedAt' => now()->toDayDateTimeString(),
        ]);
    }
}
