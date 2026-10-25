<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Student;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student ID card: passport upload, on-screen preview, and a CR80
 * (credit-card size) PDF for card printers. The QR code opens a signed
 * public verification page showing the student's live status.
 */
class IdCardController extends Controller
{
    /** CR80 card, 85.6mm x 54mm, in PDF points (landscape). */
    private const CR80 = [0, 0, 242.65, 153.07];

    public function show(Request $request): Response|HttpResponse
    {
        $student = $this->student($request);
        $verifyUrl = URL::signedRoute('verify.student', $student);
        $card = $this->cardData($student, $verifyUrl);

        if ($request->query('format') === 'pdf') {
            abort_if($student->passport_path === null, 403, 'Upload a passport photograph before printing your ID card.');
            abort_if($student->matric_no === null, 403, 'An ID card needs a matriculation number.');

            $photo = 'data:image/'.pathinfo($student->passport_path, PATHINFO_EXTENSION).';base64,'
                .base64_encode(Storage::disk('local')->get($student->passport_path));

            return Pdf::loadView('pdf.id-card', $card + ['photo' => $photo])
                ->setPaper(self::CR80)
                ->download("id-card-{$student->id}.pdf");
        }

        return Inertia::render('student/id-card', $card + [
            'photoUrl' => $student->passport_path ? route('student.id-card.passport').'?v='.md5($student->passport_path) : null,
            'pdfUrl' => route('student.id-card', ['format' => 'pdf']),
        ]);
    }

    public function uploadPassport(Request $request): RedirectResponse
    {
        $request->validate([
            'passport' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:1024', 'dimensions:min_width=200,min_height=200'],
        ]);

        $student = $this->student($request);

        if ($student->passport_path) {
            Storage::disk('local')->delete($student->passport_path);
        }

        $path = $request->file('passport')->storeAs(
            'passports',
            $student->id.'-'.now()->timestamp.'.'.$request->file('passport')->extension(),
            'local',
        );

        $student->update(['passport_path' => $path]);

        return back()->with('success', 'Passport photograph uploaded.');
    }

    public function passport(Request $request): StreamedResponse
    {
        $student = $this->student($request);
        abort_if($student->passport_path === null, 404);

        return Storage::disk('local')->response($student->passport_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardData(Student $student, string $verifyUrl): array
    {
        return [
            'institution' => Institution::current()?->only('name', 'short_name', 'motto', 'address'),
            'student' => [
                'name' => $student->user->name,
                'matric_no' => $student->matric_no,
                'programme' => $student->programme->name,
                'department' => $student->programme->department->name,
                'faculty' => $student->programme->department->faculty->name,
                'gender' => $student->gender,
                'status' => $student->status->value,
            ],
            'validUntil' => $student->idCardValidUntil()->format('F Y'),
            'qr' => Qr::dataUri($verifyUrl, 3),
            'verifyUrl' => $verifyUrl,
        ];
    }

    private function student(Request $request): Student
    {
        return $request->user()->student()
            ->with(['user', 'programme.department.faculty', 'entrySession'])
            ->firstOrFail();
    }
}
