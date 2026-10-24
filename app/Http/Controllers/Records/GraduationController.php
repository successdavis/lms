<?php

namespace App\Http\Controllers\Records;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use App\Services\Academics\ResultComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Senate/Academic-board approved graduation list and NYSC mobilization.
 * A student graduates only after full clearance with no outstanding
 * carryovers; NYSC lists cover degree and HND graduates (not ND).
 */
class GraduationController extends Controller
{
    public function index(ResultComputationService $computer): Response
    {
        $students = Student::query()
            ->whereHas('clearanceRequest', fn ($q) => $q->where('status', 'cleared'))
            ->with(['user:id,name,email', 'programme.gradeScale.classificationBands', 'semesterResults' => fn ($q) => $q->latest('id')])
            ->get()
            ->map(function (Student $student) use ($computer) {
                $latest = $student->semesterResults->first();

                return [
                    'id' => $student->id,
                    'name' => $student->user->name,
                    'matric_no' => $student->matric_no,
                    'programme' => $student->programme->name,
                    'award' => $student->programme->award,
                    'cgpa' => $latest?->cgpa,
                    'classification' => $latest ? $computer->classify($student->programme->gradeScale, (float) $latest->cgpa)?->name : null,
                    'graduated_on' => $student->graduated_on?->toDateString(),
                    'nysc_batch' => $student->nysc_batch,
                    'nysc_eligible' => $this->nyscEligibility($student),
                    'carryovers' => $student->outstandingCarryovers()->count(),
                ];
            });

        return Inertia::render('records/graduation', ['students' => $students]);
    }

    public function graduate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'graduated_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $graduated = 0;
        $skipped = [];

        foreach (Student::query()->whereIn('id', $validated['student_ids'])->with('clearanceRequest')->get() as $student) {
            if ($student->clearanceRequest?->status !== 'cleared') {
                $skipped[] = "{$student->matric_no}: clearance incomplete.";

                continue;
            }

            if ($student->outstandingCarryovers()->isNotEmpty()) {
                $skipped[] = "{$student->matric_no}: outstanding carryover courses.";

                continue;
            }

            $student->update(['status' => StudentStatus::Graduated, 'graduated_on' => $validated['graduated_on']]);
            $graduated++;
        }

        $response = back()->with('success', "{$graduated} student(s) graduated.");

        return $skipped === []
            ? $response
            : $response->withErrors(collect($skipped)->mapWithKeys(fn ($m, $i) => ["graduate.{$i}" => $m])->all());
    }

    /**
     * CSV of graduates for the NYSC mobilization portal. Exporting with a batch
     * name stamps it on graduates not yet in any batch, so each graduate is
     * mobilized once.
     */
    public function nyscExport(Request $request, ResultComputationService $computer): StreamedResponse
    {
        $validated = $request->validate([
            'batch' => ['required', 'string', 'max:50'],
        ]);

        $students = Student::query()
            ->where('status', StudentStatus::Graduated)
            ->where(fn ($q) => $q->whereNull('nysc_batch')->orWhere('nysc_batch', $validated['batch']))
            ->whereHas('programme', fn ($q) => $q->where('award', '!=', 'ND'))
            ->with(['user:id,name,email', 'programme.gradeScale.classificationBands', 'semesterResults' => fn ($q) => $q->latest('id')])
            ->get();

        DB::transaction(function () use ($students, $validated) {
            $newlyStamped = $students->whereNull('nysc_batch')->pluck('id');

            Student::query()->whereIn('id', $newlyStamped)->update(['nysc_batch' => $validated['batch']]);

            if ($newlyStamped->isNotEmpty()) {
                AuditLog::record('nysc.batch_stamped', Student::class, null, [
                    'batch' => $validated['batch'],
                    'student_ids' => $newlyStamped->all(),
                ], "{$newlyStamped->count()} graduate(s) stamped for NYSC {$validated['batch']}");
            }
        });

        $filename = 'nysc-'.str($validated['batch'])->slug().'.csv';

        return response()->streamDownload(function () use ($students, $computer) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Matric No', 'Full Name', 'Gender', 'Date of Birth', 'Age at Graduation', 'Mobilization',
                'Course of Study', 'Qualification', 'Class of Degree', 'CGPA', 'Date of Graduation',
                'State of Origin', 'Phone', 'Email', 'JAMB Reg No',
            ]);

            foreach ($students as $student) {
                $latest = $student->semesterResults->first();
                fputcsv($out, [
                    $student->matric_no,
                    $student->user->name,
                    ucfirst((string) $student->gender),
                    $student->date_of_birth?->toDateString(),
                    $this->ageAtGraduation($student),
                    $this->nyscEligibility($student),
                    $student->programme->name,
                    $student->programme->award,
                    $latest ? $computer->classify($student->programme->gradeScale, (float) $latest->cgpa)?->name : '',
                    $latest?->cgpa,
                    $student->graduated_on?->toDateString(),
                    $student->state_of_origin,
                    $student->phone,
                    $student->user->email,
                    $student->jamb_reg_no,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function ageAtGraduation(Student $student): ?int
    {
        if ($student->date_of_birth === null) {
            return null;
        }

        return (int) $student->date_of_birth->diffInYears($student->graduated_on ?? Carbon::today());
    }

    /**
     * NYSC mobilizes graduates under 30 at graduation; older graduates are
     * issued an exemption certificate. ND holders are not mobilized at all.
     */
    private function nyscEligibility(Student $student): string
    {
        if ($student->programme->award === 'ND') {
            return 'Not applicable (ND)';
        }

        $age = $this->ageAtGraduation($student);

        if ($age === null) {
            return 'Unknown (no date of birth)';
        }

        return $age < config('lms.nysc.max_age') ? 'Mobilize' : 'Exemption';
    }
}
