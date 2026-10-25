<?php

namespace Database\Seeders\University;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Admission cycles: the last three completed cycles with their whole
 * funnel (the matriculated applicants are the students simulated earlier),
 * and the open cycle with applicants at every stage, application-fee
 * payments, CAPS statuses and uploaded credentials for verification.
 */
trait BuildsAdmissions
{
    /** @var list<array{id: int, user: int, status: string, at: Carbon}> applicants of the open cycle */
    protected array $openApplicants = [];

    private array $applicationSeq = [];

    private const OLEVEL_SUBJECTS = ['English Language', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Further Mathematics', 'Geography', 'Civic Education', 'Agricultural Science', 'Computer Studies'];

    protected function buildAdmissions(): void
    {
        for ($year = max(Catalogue::FOUNDED, $this->currentYear - 2); $year <= $this->admissionsYear; $year++) {
            $open = $year === $this->admissionsYear && $year > $this->currentYear;
            $open ? $this->openCycle($year) : $this->completedCycle($year);
        }
    }

    private function completedCycle(int $year): void
    {
        $cycle = $this->cycles[$year];
        $lists = $cycle['lists'];

        // Everyone who matriculated that year came through this cycle.
        foreach ($this->roster as $studentId => $student) {
            if ($student['entry'] !== $year) {
                continue;
            }

            $scores = $this->applicantScores($student['ability'], true, $student['direct']);
            $supplementary = $this->kit->chance(0.15);
            $admitted = Carbon::create($year, 10, $supplementary ? 26 : 5, 12);
            $submitted = $this->kit->moment(Carbon::create($year, 3, 1), Carbon::create($year, 7, 31));

            $this->applicant([
                'user_id' => $student['user'],
                'admission_cycle_id' => $cycle['id'],
                'status' => 'matriculated',
                'programme_id' => $this->programmes[$student['dept']]['id'],
                'admitted_programme_id' => $this->programmes[$student['dept']]['id'],
                'entry_mode' => $student['direct'] ? 'direct_entry' : 'utme',
                'jamb_reg_no' => $student['jamb'],
                'admission_list_id' => $lists[$supplementary ? 'Supplementary List' : 'Merit List'] ?? null,
                'submitted_at' => $this->dt($submitted),
                'admitted_at' => $this->dt($admitted),
                'accepted_at' => $this->dt($admitted->copy()->addDays($this->kit->between(2, 14))),
                'matriculated_at' => $this->dt($student['matriculated']),
                'student_id' => $studentId,
                'caps_status' => 'accepted',
            ] + $scores, $student['person'], $student['dob'], $year, $submitted, $student['matriculated']);
            $this->kit->checkpoint();
        }

        // And many more who did not make it.
        foreach ($this->programmes as $dept => $programme) {
            if ($programme['first'] > $year) {
                continue;
            }
            $count = (int) round($programme['intake'] * 1.4);
            for ($i = 0; $i < $count; $i++) {
                $status = (string) $this->kit->weighted(['screened' => 55, 'rejected' => 15, 'admitted' => 15, 'submitted' => 10, 'draft' => 5]);
                $this->newApplicant($year, $dept, $status, false);
            }
        }
    }

    private function openCycle(int $year): void
    {
        $meritOut = isset($this->cycles[$year]['lists']['Merit List']) && Carbon::create($year, 10, 5, 12)->lte($this->now);
        $weights = $meritOut
            ? ['draft' => 7, 'submitted' => 15, 'screened' => 35, 'admitted' => 20, 'accepted' => 15, 'rejected' => 4, 'provisional' => 4]
            : ['draft' => 10, 'submitted' => 40, 'screened' => 50];

        foreach ($this->programmes as $dept => $programme) {
            $count = (int) round($programme['intake'] * 1.25 * 4.5);
            for ($i = 0; $i < $count; $i++) {
                $this->newApplicant($year, $dept, (string) $this->kit->weighted($weights), true);
            }
        }

        // The demo applicant: offered Computer Science on the merit list.
        $this->newApplicant($year, 'CSC', $meritOut ? 'admitted' : 'screened', true, [
            'first' => 'Ngozi', 'last' => 'Eze', 'gender' => 'female', 'state' => 'Enugu', 'lga' => 'Nsukka',
        ], 'applicant@demo.edu.ng');
    }

    /**
     * An applicant who did not (yet) become a student.
     * "provisional" = admitted onto the supplementary list, not yet published.
     */
    private function newApplicant(int $year, string $dept, string $status, bool $open, ?array $person = null, ?string $email = null): void
    {
        $cycle = $this->cycles[$year];
        $person ??= $this->kit->person();
        $demo = $email !== null;
        $ability = $demo ? 64.0 : max(20, min(90, $this->kit->normal(52, 12)));
        $screened = in_array($status, ['screened', 'admitted', 'accepted', 'rejected', 'provisional'], true);
        $offered = in_array($status, ['admitted', 'accepted', 'provisional'], true);

        // Below the cut-off unless offered.
        $scores = $this->applicantScores($ability, $offered, false);
        if (! $offered && $screened && $scores['aggregate_score'] >= 50) {
            $scores['post_utme_score'] = max(20, $scores['post_utme_score'] - 25);
            $scores['aggregate_score'] = round($scores['utme_score'] / 400 * 60 + $scores['post_utme_score'] / 100 * 40, 2);
        }
        if (! $screened) {
            $scores['post_utme_score'] = null;
            $scores['aggregate_score'] = null;
        }

        $cycleOpened = Carbon::create($year, 2, 1);
        $latest = $open ? $this->now->copy()->subDays(1) : Carbon::create($year, 8, 31);
        $submitted = $this->kit->moment($cycleOpened->copy()->addWeeks(2), $open ? $latest->copy()->min(Carbon::create($year, 9, 30)) : Carbon::create($year, 7, 31));
        $created = $submitted->copy()->subDays($this->kit->between(1, 20));
        $list = match ($status) {
            'admitted', 'accepted' => $cycle['lists']['Merit List'] ?? null,
            'provisional' => $cycle['lists']['Supplementary List'] ?? null,
            default => null,
        };
        $admittedAt = $offered ? Carbon::create($year, 10, 5, 12) : null;
        $acceptedAt = $status === 'accepted' ? $admittedAt->copy()->addDays($this->kit->between(1, 12))->min($this->now->copy()->subHours(3)) : null;

        $userId = $this->user(
            "{$person['first']} {$person['last']}",
            $email ?? $this->kit->email($person['first'], $person['last'], $this->kit->pick(['gmail.com', 'yahoo.com', 'gmail.com', 'outlook.com'])),
            ['applicant'],
            $created,
        );

        $applicantId = $this->applicant([
            'user_id' => $userId,
            'admission_cycle_id' => $cycle['id'],
            'status' => $status === 'provisional' ? 'admitted' : $status,
            'programme_id' => $this->programmes[$dept]['id'],
            'admitted_programme_id' => $offered ? $this->programmes[$dept]['id'] : null,
            'entry_mode' => 'utme',
            'jamb_reg_no' => $year.$this->kit->digits(8).$this->kit->alnum(2),
            'admission_list_id' => $list,
            'submitted_at' => $status === 'draft' ? null : $this->dt($submitted),
            'admitted_at' => $this->dt($admittedAt),
            'accepted_at' => $this->dt($acceptedAt),
            'matriculated_at' => null,
            'student_id' => null,
            'caps_status' => match ($status) {
                'accepted' => 'accepted',
                'admitted' => $this->kit->chance(0.6) ? 'approved' : 'recommended',
                'provisional' => 'recommended',
                'screened' => $open && $this->kit->chance(0.3) ? 'not_uploaded' : null,
                default => null,
            },
        ] + $scores, $person, Carbon::create($year - $this->kit->between(16, 21), $this->kit->between(1, 12), $this->kit->between(1, 28)), $year, $status === 'draft' ? $created : $submitted, $acceptedAt ?? $admittedAt ?? $submitted);
        if (! $open) {
            $this->kit->checkpoint();
        }

        if ($open) {
            $this->openApplicants[] = ['id' => $applicantId, 'user' => $userId, 'status' => $status, 'at' => $admittedAt ?? $submitted, 'demo' => $demo];
            $this->credentials($applicantId, $status, $submitted, $demo);

            $this->kit->checkpoint();

            if ($screened && $this->kit->chance(0.25)) {
                $this->audit('updated', 'App\\Models\\Applicant', $applicantId, ['post_utme_score' => null], ['post_utme_score' => $scores['post_utme_score']], $this->office['admission-officer'], $this->kit->moment(Carbon::create($year, 9, 1)->min($this->now->copy()->subDays(5)), $this->now->copy()->subDay()));
            }
        }
    }

    /** UTME (0–400), post-UTME (0–100) and the 60/40 aggregate. */
    private function applicantScores(float $ability, bool $admitted, bool $directEntry): array
    {
        $utme = (int) max(140, min(365, round(170 + ($ability - 35) * 3.2 + $this->kit->normal(0, 18))));
        $post = (float) max(15, min(96, round($ability + $this->kit->normal(4, 8))));

        if ($admitted) {
            $utme = max($utme, 200);
            // Offered candidates cleared the 50-point cut-off.
            while ($utme / 400 * 60 + $post / 100 * 40 < 50) {
                $post = min(96, $post + 5);
                $utme = min(365, $utme + 10);
            }
        }

        return [
            'utme_score' => $directEntry ? null : $utme,
            'post_utme_score' => $post,
            'aggregate_score' => $directEntry ? $post : round($utme / 400 * 60 + $post / 100 * 40, 2),
        ];
    }

    private function applicant(array $row, array $person, Carbon $dob, int $year, Carbon $created, Carbon $updated): int
    {
        $seq = $this->applicationSeq[$year] = ($this->applicationSeq[$year] ?? 0) + 1;
        $grades = ['A1', 'B2', 'B3', 'B3', 'C4', 'C5', 'C6'];
        $subjects = $this->olevelSubjects();

        $id = $this->kit->add('applicants', $row + [
            'application_no' => sprintf('APP-%d-%05d', $year, $seq),
            'gender' => $person['gender'],
            'date_of_birth' => $dob->toDateString(),
            'phone' => $this->kit->phone(),
            'state_of_origin' => $person['state'],
            'lga_of_origin' => $person['lga'],
            'address' => $this->kit->between(1, 90).' '.$this->kit->pick(['Okigwe Road', 'Ikwerre Road', 'Zik Avenue', 'Awolowo Way', 'Ahmadu Bello Way']).', '.$person['lga'].', '.$person['state'].' State',
            'o_level_results' => json_encode([[
                'exam' => $this->kit->pick(['WAEC', 'WAEC', 'NECO']),
                'year' => $year - 1,
                'number' => $this->kit->digits(10),
                'subjects' => array_combine($subjects, array_map(fn () => $this->kit->pick($grades), $subjects)),
            ]]),
            'created_at' => $this->dt($created),
            'updated_at' => $this->dt($updated),
        ]);

        if ($row['status'] !== 'draft') {
            $cycle = collect($this->cycles)->firstWhere('id', $row['admission_cycle_id']);
            $this->payment(['applicant_id' => $id], (float) $cycle['fee'], 'successful', $created->copy()->subHours($this->kit->between(1, 48)), (string) $this->kit->weighted(['remita' => 50, 'paystack' => 40, 'flutterwave' => 10]));
        } elseif ($this->kit->chance(0.3)) {
            $cycle = collect($this->cycles)->firstWhere('id', $row['admission_cycle_id']);
            $this->payment(['applicant_id' => $id], (float) $cycle['fee'], 'pending', $created, 'remita');
        }

        return $id;
    }

    private function olevelSubjects(): array
    {
        $optional = array_slice(self::OLEVEL_SUBJECTS, 2);
        shuffle($optional);

        return array_merge(['English Language', 'Mathematics'], array_slice($optional, 0, 7));
    }

    /** Uploaded credentials (real files in private storage) and their checks. */
    private function credentials(int $applicantId, string $status, Carbon $submitted, bool $demo): void
    {
        if ($status === 'draft' && $this->kit->chance(0.5)) {
            return;
        }

        $types = ['olevel_result' => "WAEC_result_{$applicantId}.pdf", 'jamb_result' => "JAMB_slip_{$applicantId}.pdf"];
        if (in_array($status, ['admitted', 'accepted', 'provisional'], true) || ($status === 'screened' && $this->kit->chance(0.5))) {
            $types += ['birth_certificate' => "birth_certificate_{$applicantId}.pdf", 'lga_identification' => "LGA_certificate_{$applicantId}.pdf"];
        }

        foreach ($types as $type => $name) {
            $docStatus = match (true) {
                $demo, in_array($status, ['admitted', 'accepted', 'provisional'], true) => 'verified',
                $status === 'screened' => (string) $this->kit->weighted(['verified' => 70, 'pending' => 20, 'rejected' => 10]),
                default => 'pending',
            };
            $path = "applicant-documents/{$applicantId}/{$type}.pdf";
            Storage::disk('local')->put($path, $this->placeholderPdf(ucwords(str_replace('_', ' ', $type))));

            $uploaded = $submitted->copy()->subHours($this->kit->between(1, 72));
            $checked = $docStatus === 'pending' ? null : $this->kit->moment($uploaded->copy()->addDay(), $uploaded->copy()->addDays(20)->min($this->now->copy()->subHour()));
            $id = $this->kit->add('applicant_documents', [
                'applicant_id' => $applicantId,
                'type' => $type,
                'path' => $path,
                'original_name' => $name,
                'status' => $docStatus,
                'note' => $docStatus === 'rejected' ? $this->kit->pick(['Scan is not legible; please upload a clearer copy.', 'Name on the document does not match your application.', 'Result scratch card details missing.']) : null,
                'verified_by_id' => $checked ? $this->office['admission-officer'] : null,
                'verified_at' => $this->dt($checked),
                'created_at' => $this->dt($uploaded),
                'updated_at' => $this->dt($checked ?? $uploaded),
            ]);

            if ($checked && $checked->gt($this->now->copy()->subDays(45))) {
                $this->audit('updated', 'App\\Models\\ApplicantDocument', $id, ['status' => 'pending'], ['status' => $docStatus], $this->office['admission-officer'], $checked);
            }
        }
    }

    /** A minimal one-page PDF standing in for a scanned credential. */
    protected function placeholderPdf(string $title): string
    {
        $text = 'Scanned copy: '.$title;
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
