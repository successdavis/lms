<?php

namespace Database\Seeders\University;

use Illuminate\Support\Carbon;

/**
 * Every cohort since founding, simulated session by session: fees and
 * payments, course registration (carryovers first, 24-unit cap), graded
 * results and GPA/CGPA snapshots computed exactly as the app does
 * (truncated to two decimals, NUC probation/withdrawal rules), promotion
 * through the levels, spill-over, withdrawal, graduation with clearance
 * and NYSC batches. The current semester's results sit at different
 * points of the approval chain, course by course.
 */
trait SimulatesStudents
{
    /** @var array<int, array<string, mixed>> student id => facts later steps need */
    protected array $roster = [];

    protected int $demoStudentId;

    /** @var array<int, string|null> course id => stage of its current-semester results */
    private array $courseStage = [];

    private array $matricSeq = [];

    private int $invoiceSeq = 0;

    private array $courseDifficulty = [];

    private const STREETS = ['Okigwe Road', 'Wetheral Road', 'Douglas Road', 'Aba Road', 'Ikwerre Road', 'Ogui Road', 'Zik Avenue', 'Awolowo Way', 'Ahmadu Bello Way', 'Murtala Mohammed Way', 'Hospital Road', 'Market Road', 'Stadium Road', 'Old Airport Road'];

    protected function simulateStudents(): void
    {
        $span = max(1, $this->currentYear - Catalogue::FOUNDED);

        for ($year = Catalogue::FOUNDED; $year <= $this->currentYear; $year++) {
            $growth = 0.6 + 0.65 * ($year - Catalogue::FOUNDED) / $span;

            foreach ($this->programmes as $dept => $programme) {
                if ($programme['first'] > $year) {
                    continue;
                }

                $size = max(3, (int) round($programme['intake'] * $growth * (0.85 + 0.3 * mt_rand() / mt_getrandmax())));
                for ($k = 0; $k < $size; $k++) {
                    $demo = $dept === 'CSC' && $year === $this->currentYear - 2 && $k === 0;
                    $directEntry = ! $demo && $this->kit->chance($programme['years'] === 4 ? 0.08 : 0.06);
                    $this->simulateStudent($dept, $year, $directEntry, $demo);
                }
            }
        }
    }

    private function simulateStudent(string $dept, int $entryYear, bool $directEntry, bool $demo): void
    {
        $programme = $this->programmes[$dept];
        $person = $demo
            ? ['first' => 'Chinedu', 'last' => 'Okafor', 'gender' => 'male', 'state' => 'Anambra', 'lga' => 'Awka South']
            : $this->kit->person();
        $ability = $demo ? 67.0 : max(25, min(92, $this->kit->normal(58, 10)));

        $matriculated = Carbon::create($entryYear, 11, 3)->addDays($this->kit->between(0, 25))->setTime(10, 0)->min($this->now->copy()->subDay());
        $email = $demo ? 'student@demo.edu.ng' : $this->kit->email($person['first'], $person['last'], 'student.futd.edu.ng');
        $userId = $this->user("{$person['first']} {$person['last']}", $email, ['student'], $matriculated->copy()->subMonths($this->kit->between(3, 8)));

        $studentId = $this->kit->nextId('students');
        $seq = $this->matricSeq[$dept][$entryYear] = ($this->matricSeq[$dept][$entryYear] ?? 0) + 1;
        $matric = sprintf('FUTD/%s/%s/%04d', substr((string) $entryYear, -2), $dept, $seq);
        $jamb = $entryYear.$this->kit->digits(8).$this->kit->alnum(2);
        $entryAge = (int) max(16, min(25, round($this->kit->normal(18.6, 1.4))));
        $dob = Carbon::create($entryYear - $entryAge, $this->kit->between(1, 12), $this->kit->between(1, 28));

        $startRank = $directEntry ? 2 : 1;
        $finalRank = $programme['years'];
        $carry = [];
        $cumUnits = 0;
        $cumQp = 0.0;
        $standing = null;
        $status = 'active';
        $graduatedOn = null;
        $levels = [];
        $paidCurrent = true;
        $awaiting = false;
        $currentInvoice = null;
        $rank = $startRank;

        for ($year = $entryYear; $year <= $this->currentYear; $year++) {
            $rank = $startRank + ($year - $entryYear);
            $spill = $rank > $finalRank;
            $rank = min($rank, $finalRank);
            $levels[$year] = $rank;

            // Fees for the session; in the current one not everyone has paid.
            $plan = 'paid';
            if ($year === $this->currentYear && ! $demo) {
                $plan = $this->kit->weighted(['paid' => 86, 'part' => 6, 'unpaid' => 6, 'deferred' => 2]);
            }
            $invoiceId = $this->sessionInvoice($studentId, $dept, $year, $rank, $year === $entryYear, $plan, $matriculated);
            if ($year === $this->currentYear) {
                $currentInvoice = $invoiceId;
                $paidCurrent = $plan === 'paid';
                if ($plan === 'deferred') {
                    $status = 'deferred';
                }
            }

            if ($plan !== 'paid') {
                continue;
            }

            foreach ([1, 2] as $sem) {
                if ($year === $this->currentYear && $sem > $this->currentSemester) {
                    break;
                }

                $courseIds = $this->semesterCourses($dept, $rank, $sem, $spill, $carry);
                if ($courseIds === []) {
                    continue;
                }

                $semesterId = $this->sessions[$year]['sem'][$sem];
                $closed = $this->isClosed($year, $sem);
                [$start, $end] = $this->semesterDates($year, $sem);
                $submitted = $this->kit->moment($start->copy()->addDays(2), $start->copy()->addDays(25));
                $approved = $closed || $this->kit->chance(0.94);
                $approvedAt = $submitted->copy()->addDays($this->kit->between(1, 6));

                $units = array_sum(array_map(fn ($c) => $this->courses[$c]['units'], $courseIds));
                $registrationId = $this->kit->add('course_registrations', [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'level_id' => $this->levels[$rank],
                    'status' => $approved ? 'approved' : 'submitted',
                    'total_units' => $units,
                    'submitted_at' => $this->dt($submitted),
                    'approved_by_id' => $approved ? ($this->hodStaff[$dept] ?? null) : null,
                    'approved_at' => $approved ? $this->dt($approvedAt) : null,
                    'created_at' => $this->dt($submitted->copy()->subHours(2)),
                    'updated_at' => $this->dt($approved ? $approvedAt : $submitted),
                ]);

                $termUnits = 0;
                $termQp = 0.0;
                $entered = $end->copy()->addWeeks($this->kit->between(2, 4));
                $released = $end->copy()->addWeeks($this->kit->between(6, 9));

                foreach ($courseIds as $courseId) {
                    $isCarry = isset($carry[$courseId]);
                    $registeredId = $this->kit->add('registered_courses', [
                        'course_registration_id' => $registrationId,
                        'course_id' => $courseId,
                        'credit_units' => $this->courses[$courseId]['units'],
                        'is_carryover' => $isCarry,
                        'created_at' => $this->dt($submitted),
                        'updated_at' => $this->dt($submitted),
                    ]);

                    $stage = $closed ? 'senate_approved' : $this->currentStage($courseId);
                    if ($stage === null) {
                        continue;
                    }

                    $graded = $this->grade($ability, $courseId, $isCarry);
                    $at = $closed ? $entered : $this->now->copy()->subDays($this->kit->between(1, 18));
                    $this->kit->add('results', [
                        'registered_course_id' => $registeredId,
                        'status' => $stage,
                        'entered_by_id' => $this->allocations[$semesterId][$courseId] ?? null,
                        'created_at' => $this->dt($at),
                        'updated_at' => $this->dt($closed ? $released : $at),
                    ] + $graded);

                    if ($closed) {
                        $termUnits += $this->courses[$courseId]['units'];
                        $termQp += $graded['quality_points'];
                        if ($graded['is_passed']) {
                            unset($carry[$courseId]);
                        } else {
                            $carry[$courseId] = true;
                        }
                    }
                }

                if (! $closed || $termUnits === 0) {
                    continue;
                }

                $cumUnits += $termUnits;
                $cumQp += $termQp;
                $cgpa = floor($cumQp / $cumUnits * 100) / 100;
                $standing = $this->standing($cgpa, $standing);
                $this->kit->add('semester_results', [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'total_units' => $termUnits,
                    'total_quality_points' => round($termQp, 2),
                    'gpa' => floor($termQp / $termUnits * 100) / 100,
                    'cumulative_units' => $cumUnits,
                    'cumulative_quality_points' => round($cumQp, 2),
                    'cgpa' => $cgpa,
                    'standing' => $standing,
                    'remarks' => $standing === 'probation' ? 'Placed on academic probation' : ($standing === 'withdrawal' ? 'Advised to withdraw' : null),
                    'created_at' => $this->dt($released),
                    'updated_at' => $this->dt($released),
                ]);

                if ($standing === 'withdrawal') {
                    $status = 'withdrawn';
                    break 2;
                }
                $status = $standing === 'probation' ? 'probation' : ($spill ? 'spillover' : 'active');
            }

            if ($year < $this->currentYear && $rank === $finalRank && $carry === []) {
                $graduatedOn = Carbon::create($year + 1, 12, $this->kit->between(5, 15));
                $status = 'graduated';
                break;
            }
            if ($rank === $finalRank && $carry !== [] && $year < $this->currentYear) {
                $status = $status === 'probation' ? 'probation' : 'spillover';
            }
            if ($year === $this->currentYear && $spill && $carry === [] && $this->currentSemester === 2) {
                $awaiting = true; // cleared their carryovers in the first semester
            }
        }

        if (in_array($status, ['active', 'probation'], true) && ! $demo && $this->kit->chance(0.005)) {
            $status = 'suspended';
        }

        $nysc = null;
        if ($graduatedOn !== null) {
            $nysc = $this->graduateClearance($studentId, $dept, $graduatedOn);
        } elseif ($awaiting) {
            $this->graduateClearance($studentId, $dept, $this->now->copy()->subDays($this->kit->between(5, 30)), true);
        }

        $this->kit->add('students', [
            'id' => $studentId,
            'user_id' => $userId,
            'programme_id' => $programme['id'],
            'level_id' => $this->levels[$rank],
            'entry_session_id' => $this->sessions[$entryYear]['id'],
            'entry_mode' => $directEntry ? 'direct_entry' : 'utme',
            'matric_no' => $matric,
            'jamb_reg_no' => $jamb,
            'status' => $status,
            'graduated_on' => $graduatedOn?->toDateString(),
            'nysc_batch' => $nysc,
            'gender' => $person['gender'],
            'date_of_birth' => $dob->toDateString(),
            'nationality' => 'Nigerian',
            'state_of_origin' => $person['state'],
            'lga_of_origin' => $person['lga'],
            'is_indigene' => $person['state'] === 'Imo',
            'phone' => $this->kit->phone(),
            'address' => $this->kit->between(1, 120).' '.$this->kit->pick(self::STREETS).', '.$person['lga'].', '.$person['state'].' State',
            'guardian_name' => ($this->kit->chance(0.6) ? 'Mr. ' : 'Mrs. ').$this->kit->person()['first'].' '.$person['last'],
            'guardian_phone' => $this->kit->phone(),
            'created_at' => $this->dt($matriculated),
            'updated_at' => $this->dt($graduatedOn ?? $matriculated),
        ]);

        if ($demo) {
            $this->demoStudentId = $studentId;
        }

        $this->roster[$studentId] = [
            'user' => $userId, 'name' => "{$person['first']} {$person['last']}", 'dept' => $dept, 'gender' => $person['gender'],
            'status' => $status, 'rank' => $rank, 'final' => $finalRank, 'entry' => $entryYear, 'levels' => $levels,
            'paid' => $paidCurrent, 'invoice' => $currentInvoice, 'graduated' => $graduatedOn, 'awaiting' => $awaiting,
            'carry' => count($carry), 'jamb' => $jamb, 'direct' => $directEntry, 'dob' => $dob, 'person' => $person,
            'ability' => $ability, 'matriculated' => $matriculated, 'cgpa' => $cumUnits ? floor($cumQp / $cumUnits * 100) / 100 : null,
        ];
        $this->kit->checkpoint();
    }

    /** Carryovers first, then the level's courses, up to the 24-unit cap. */
    private function semesterCourses(string $dept, int $rank, int $sem, bool $spill, array $carry): array
    {
        $selected = [];
        $units = 0;
        $candidates = array_merge(
            array_keys(array_filter($carry, fn ($v, $c) => $this->courses[$c]['sem'] === $sem, ARRAY_FILTER_USE_BOTH)),
            $spill ? [] : ($this->curriculum[$dept][$rank][$sem] ?? []),
        );

        foreach (array_unique($candidates) as $courseId) {
            if ($units + $this->courses[$courseId]['units'] > 24) {
                continue;
            }
            $selected[] = $courseId;
            $units += $this->courses[$courseId]['units'];
        }

        return $selected;
    }

    /**
     * A course result: total around the student's ability (harder courses
     * lower, repeat attempts a little higher), split into CA (max 30) and
     * exam (max 70), banded on the NUC scale.
     */
    private function grade(float $ability, int $courseId, bool $isCarry): array
    {
        $this->courseDifficulty[$courseId] ??= (crc32($this->courses[$courseId]['code']) % 9) - 5;
        $total = (int) max(8, min(97, round($this->kit->normal($ability + $this->courseDifficulty[$courseId] + ($isCarry ? 5 : 0), 9))));
        $ca = (int) max(0, min(30, round($total * 0.3 + $this->kit->normal(0, 2))));
        $exam = $total - $ca;
        if ($exam > 70) {
            [$exam, $ca] = [70, $total - 70];
        } elseif ($exam < 0) {
            [$exam, $ca] = [0, $total];
        }

        $band = $this->scale['bands'][array_key_last($this->scale['bands'])];
        foreach ($this->scale['bands'] as $candidate) {
            if ($total >= $candidate['min']) {
                $band = $candidate;
                break;
            }
        }
        $units = $this->courses[$courseId]['units'];

        return [
            'ca_score' => $ca,
            'exam_score' => $exam,
            'total_score' => $total,
            'grade_letter' => $band['letter'],
            'grade_point' => $band['point'],
            'quality_points' => round($band['point'] * $units, 2),
            'is_passed' => $band['pass'],
        ];
    }

    private function standing(float $cgpa, ?string $previous): string
    {
        if ($cgpa >= $this->scale['probation']) {
            return 'good';
        }
        if (in_array($previous, ['probation', 'withdrawal'], true)) {
            return 'withdrawal';
        }
        if ($this->scale['withdrawal'] !== null && $cgpa < $this->scale['withdrawal']) {
            return 'withdrawal';
        }

        return 'probation';
    }

    /**
     * Where a course's current-semester results are. Early in the semester
     * nothing is scored; late in it, courses spread along the approval chain.
     * Returns null when this student's result has not been entered.
     */
    private function currentStage(int $courseId): ?string
    {
        if ($this->semesterProgress < 0.75) {
            return null;
        }

        if (! array_key_exists($courseId, $this->courseStage)) {
            $code = $this->courses[$courseId]['code'];
            $this->courseStage[$courseId] = match (true) {
                $code === 'CSC 102' => 'pending',
                $code === 'CSC 104' => 'partial',
                default => $this->kit->weighted(['none' => 12, 'partial' => 10, 'pending' => 28, 'hod_approved' => 22, 'faculty_approved' => 18, 'senate_approved' => 10]),
            };
        }

        return match ($this->courseStage[$courseId]) {
            'none' => null,
            'partial' => $this->kit->chance(0.55) ? 'pending' : null,
            default => $this->courseStage[$courseId],
        };
    }

    /**
     * The session's school-fees invoice with its lines and payments.
     * Plans: paid (one payment, sometimes two instalments or after a failed
     * attempt), part (half to two-thirds), unpaid (maybe a teller payment
     * waiting for the bursary to confirm), deferred (nothing).
     */
    private function sessionInvoice(int $studentId, string $dept, int $year, int $rank, bool $first, string $plan, Carbon $matriculated): int
    {
        $lines = $this->feeLines($year, $dept, $rank, $first);
        $total = array_sum(array_column($lines, 2));
        $sessionStart = Carbon::create($year, 11, 3);
        $issued = $first ? $matriculated->copy() : $this->kit->moment($sessionStart, $sessionStart->copy()->addDays(10));

        $payments = match ($plan) {
            'paid' => $this->kit->chance(0.1) ? [round($total * 0.6, 2), round($total * 0.4, 2)] : [$total],
            'part' => [round($total * $this->kit->between(50, 70) / 100, 2)],
            default => [],
        };
        $paid = array_sum($payments);

        $invoiceId = $this->kit->add('invoices', [
            'number' => 'INV-'.str_replace('/', '', $this->sessionName($year)).'-'.str_pad((string) ++$this->invoiceSeq, 6, '0', STR_PAD_LEFT),
            'student_id' => $studentId,
            'academic_session_id' => $this->sessions[$year]['id'],
            'purpose' => 'school_fees',
            'total' => $total,
            'amount_paid' => $paid,
            'status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'part_paid' : 'unpaid'),
            'due_on' => $sessionStart->copy()->addDays(60)->toDateString(),
            'created_at' => $this->dt($issued),
            'updated_at' => $this->dt($issued),
        ]);

        foreach ($lines as [$code, $description, $amount]) {
            $this->kit->add('invoice_items', [
                'invoice_id' => $invoiceId, 'fee_type_id' => $this->feeTypes[$code], 'description' => $description, 'amount' => $amount,
                'created_at' => $this->dt($issued), 'updated_at' => $this->dt($issued),
            ]);
        }

        $at = $issued->copy();
        foreach ($payments as $i => $amount) {
            $at = $i === 0
                ? $this->kit->moment($issued->copy()->addDay(), $issued->copy()->addDays($this->kit->chance(0.8) ? 30 : 110))
                : $this->kit->moment($at->copy()->addDays(20), $at->copy()->addDays(90));
            $at = $at->min($this->now->copy()->subHour());

            if ($i === 0 && $this->kit->chance(0.06)) {
                $this->payment(['invoice_id' => $invoiceId, 'student_id' => $studentId], $amount, 'failed', $at->copy()->subMinutes($this->kit->between(5, 600)));
            }
            $this->payment(['invoice_id' => $invoiceId, 'student_id' => $studentId], $amount, 'successful', $at);
        }

        if ($plan === 'unpaid' && $this->kit->chance(0.35)) {
            $this->payment(['invoice_id' => $invoiceId, 'student_id' => $studentId], $total, 'pending', $this->now->copy()->subDays($this->kit->between(1, 12)), 'bank');
        }

        return $invoiceId;
    }

    /** One payment row with a gateway-shaped reference. */
    protected function payment(array $owner, float $amount, string $status, Carbon $at, ?string $gateway = null): int
    {
        $gateway ??= (string) $this->kit->weighted(['remita' => 45, 'paystack' => 28, 'flutterwave' => 10, 'interswitch' => 5, 'bank' => 12]);
        $reference = match ($gateway) {
            'remita' => 'RMT-'.$this->kit->alnum(12),
            'paystack' => 'PSK_'.strtolower($this->kit->alnum(14)),
            'flutterwave' => 'FLW-'.$this->kit->alnum(12),
            'interswitch' => 'ISW'.$this->kit->digits(12),
            default => 'BNK-'.$this->kit->alnum(10),
        };
        $channel = match ($gateway) {
            'remita' => $this->kit->pick(['bank_branch', 'card', 'bank_transfer']),
            'bank' => 'bank_branch',
            default => $this->kit->pick(['card', 'card', 'bank_transfer', 'ussd']),
        };

        return $this->kit->add('payments', $owner + [
            'gateway' => $gateway,
            'reference' => $reference,
            'rrr' => $gateway === 'remita' ? $this->kit->digits(12) : null,
            'amount' => $amount,
            'status' => $status,
            'channel' => $channel,
            'paid_at' => $status === 'successful' ? $this->dt($at) : null,
            'meta' => $gateway === 'bank' ? json_encode(['teller_no' => $this->kit->digits(8), 'bank' => $this->kit->pick(Catalogue::BANKS)]) : null,
            'created_at' => $this->dt($at),
            'updated_at' => $this->dt($at),
        ] + ['invoice_id' => null, 'student_id' => null, 'applicant_id' => null]);
    }

    /**
     * Final clearance signed off by every office before graduation (or,
     * for spill-over students who have just finished, awaiting it), and the
     * NYSC batch the registry stamped on the graduate.
     */
    private function graduateClearance(int $studentId, string $dept, Carbon $on, bool $awaiting = false): ?string
    {
        $cleared = $awaiting ? $on->copy() : $on->copy()->subDays($this->kit->between(4, 20));
        $started = $cleared->copy()->subDays($this->kit->between(10, 30));
        $requestId = $this->kit->add('clearance_requests', [
            'student_id' => $studentId, 'status' => 'cleared', 'cleared_at' => $this->dt($cleared),
            'created_at' => $this->dt($started), 'updated_at' => $this->dt($cleared),
        ]);

        foreach ($this->clearanceReviewers($dept) as $unit => $reviewer) {
            $at = $this->kit->moment($started, $cleared);
            $this->kit->add('clearance_items', [
                'clearance_request_id' => $requestId, 'unit' => $unit, 'status' => 'cleared', 'note' => null,
                'reviewed_by_id' => $reviewer, 'reviewed_at' => $this->dt($at), 'created_at' => $this->dt($started), 'updated_at' => $this->dt($at),
            ]);
        }

        // The registry stamps graduates onto an NYSC batch; the newest
        // cohort still has a few waiting for the next export.
        if ($awaiting || ($on->year === $this->currentYear && $this->kit->chance(0.15))) {
            return null;
        }

        return ($on->year + 1).' Batch '.$this->kit->pick(['A', 'A', 'B', 'C']);
    }

    /** @return array<string, int> clearance unit => reviewing user id */
    protected function clearanceReviewers(string $dept): array
    {
        $faculty = $this->departments[$dept]['faculty'];

        return [
            'department' => $this->hodUsers[$dept] ?? $this->office['registrar'],
            'faculty' => $this->deanUsers[$faculty] ?? $this->office['registrar'],
            'library' => $this->office['librarian'],
            'bursary' => $this->office['bursar'],
            'student_affairs' => $this->office['student-affairs'],
        ];
    }
}
