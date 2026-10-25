<?php

namespace Database\Seeders\University;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hostel allocations (this session and last), SIWES placements, final-year
 * clearance in progress, transcript requests from graduates, and the
 * activity feeds: in-app notifications and the audit trail.
 */
trait BuildsCampusLife
{
    private const RECIPIENTS = [
        ['University of Lagos School of Postgraduate Studies', 'local', 'pgschool@unilag.edu.ng', 'Akoka, Yaba, Lagos'],
        ['University of Ibadan Postgraduate College', 'local', 'pgcollege@ui.edu.ng', 'Ibadan, Oyo State'],
        ['Federal Civil Service Commission', 'local', null, 'Federal Secretariat Complex, Phase II, Abuja'],
        ['Shell Petroleum Development Company', 'local', 'recruitment@shell.com.ng', 'Industry Road, Port Harcourt'],
        ['KPMG Nigeria', 'local', 'careers@kpmg.com.ng', 'Bank Anthony Way, Ikeja, Lagos'],
        ['Covenant University School of Postgraduate Studies', 'local', 'spgs@covenantuniversity.edu.ng', 'Ota, Ogun State'],
        ['World Education Services (WES)', 'international', 'transcripts@wes.org', 'Bowling Green Station, New York, USA'],
        ['University of Manchester', 'international', 'pgadmissions@manchester.ac.uk', 'Oxford Road, Manchester, United Kingdom'],
        ['Coventry University', 'international', 'admissions@coventry.ac.uk', 'Priory Street, Coventry, United Kingdom'],
        ['University of Toronto', 'international', 'graduate.admissions@utoronto.ca', 'King\'s College Circle, Toronto, Canada'],
        ['Texas A&M University', 'international', 'gradadmissions@tamu.edu', 'College Station, Texas, USA'],
        ['RWTH Aachen University', 'international', 'international@rwth-aachen.de', 'Templergraben 55, Aachen, Germany'],
    ];

    protected function buildCampusLife(): void
    {
        $this->allocateHostels($this->currentYear);
        if (isset($this->sessions[$this->currentYear - 1])) {
            $this->allocateHostels($this->currentYear - 1);
        }
        $this->placeSiwes();
        $this->finalYearClearance();
        $this->transcripts();
    }

    /** One-line service invoice (hostel bed, transcript), as InvoiceGenerator::charge builds it. */
    private function serviceInvoice(int $studentId, int $year, string $purpose, string $feeCode, string $description, float $amount, Carbon $at, bool $paid): int
    {
        $id = $this->kit->add('invoices', [
            'number' => 'INV-'.str_replace('/', '', $this->sessionName($year)).'-'.str_pad((string) ++$this->invoiceSeq, 6, '0', STR_PAD_LEFT),
            'student_id' => $studentId,
            'academic_session_id' => $this->sessions[$year]['id'],
            'purpose' => $purpose,
            'total' => $amount,
            'amount_paid' => $paid ? $amount : 0,
            'status' => $paid ? 'paid' : 'unpaid',
            'due_on' => null,
            'created_at' => $this->dt($at),
            'updated_at' => $this->dt($at),
        ]);
        $this->kit->add('invoice_items', [
            'invoice_id' => $id, 'fee_type_id' => $this->feeTypes[$feeCode], 'description' => $description, 'amount' => $amount,
            'created_at' => $this->dt($at), 'updated_at' => $this->dt($at),
        ]);

        if ($paid) {
            $this->payment(['invoice_id' => $id, 'student_id' => $studentId], $amount, 'successful', $at->copy()->addHours($this->kit->between(1, 30))->min($this->now->copy()->subMinutes(30)));
        }

        return $id;
    }

    private function allocateHostels(int $year): void
    {
        $current = $year === $this->currentYear;
        $taken = [];
        $sessionStart = Carbon::create($year, 11, 3);
        $candidates = array_filter($this->roster, fn ($s) => isset($s['levels'][$year])
            && (! $current || ($s['paid'] && in_array($s['status'], ['active', 'probation', 'spillover'], true))));

        // The demo student always has a confirmed bed this session.
        $ids = array_keys($candidates);
        shuffle($ids);
        if ($current && isset($candidates[$this->demoStudentId])) {
            $ids = array_merge([$this->demoStudentId], array_diff($ids, [$this->demoStudentId]));
        }

        foreach ($ids as $studentId) {
            $student = $this->roster[$studentId];
            $demo = $current && $studentId === $this->demoStudentId;
            if (! $demo && ! $this->kit->chance(0.7)) {
                continue;
            }

            $halls = array_values(array_filter($this->hostels, fn ($h) => $h['gender'] === $student['gender']));
            usort($halls, fn ($a, $b) => $a['fee'] <=> $b['fee']);
            $preferred = $this->kit->weighted([0 => 45, 1 => 35, 2 => 20]);
            $order = array_merge([$halls[$preferred]], array_diff_key($halls, [$preferred => true]));

            $room = null;
            foreach ($order as $hall) {
                foreach ($hall['rooms'] as $candidate) {
                    if (($taken[$candidate['id']] ?? 0) < $candidate['capacity']) {
                        $room = $candidate;
                        break 2;
                    }
                }
            }
            if ($room === null) {
                continue;
            }
            $taken[$room['id']] = ($taken[$room['id']] ?? 0) + 1;

            $reserved = $current && ! $demo && $this->kit->chance(0.08);
            $at = $reserved ? $this->now->copy()->subHours($this->kit->between(2, 30)) : $this->kit->moment($sessionStart->copy()->addDays(5), $sessionStart->copy()->addDays(50))->min($this->now->copy()->subDay());
            $invoiceId = $this->serviceInvoice($studentId, $year, 'hostel', 'HST', "Hostel accommodation: {$hall['name']}, room {$room['number']}", (float) $hall['fee'], $at, ! $reserved);

            $allocationId = $this->kit->add('hostel_allocations', [
                'student_id' => $studentId,
                'hostel_room_id' => $room['id'],
                'academic_session_id' => $this->sessions[$year]['id'],
                'invoice_id' => $invoiceId,
                'status' => $reserved ? 'reserved' : 'confirmed',
                'expires_at' => $reserved ? $this->dt($at->copy()->addHours(48)) : null,
                'created_at' => $this->dt($at),
                'updated_at' => $this->dt($at),
            ]);

            $this->kit->checkpoint();
            if ($current && $at->gt($this->now->copy()->subDays(60))) {
                $this->audit('created', 'App\\Models\\HostelAllocation', $allocationId, null, ['status' => $reserved ? 'reserved' : 'confirmed'], $student['user'], $at);
            }
        }
    }

    /** Industrial attachments for the last four sessions. */
    private function placeSiwes(): void
    {
        $coordinator = $this->office['siwes-coordinator'];

        foreach ($this->roster as $studentId => $student) {
            $level = Catalogue::SIWES_LEVEL[$student['dept']] ?? null;
            if ($level === null) {
                continue;
            }

            foreach ($student['levels'] as $year => $rank) {
                if ($rank !== $level || $year < $this->currentYear - 3) {
                    continue;
                }
                $current = $year === $this->currentYear;
                if ($current && (! $student['paid'] || ! in_array($student['status'], ['active', 'probation'], true))) {
                    continue;
                }

                $demo = $studentId === $this->demoStudentId;
                [$employer, $address] = $demo ? ['Interswitch Group', '1648C Oko-Awo Street, Victoria Island, Lagos'] : $this->kit->pick(Catalogue::EMPLOYERS);
                $starts = Carbon::create($year + 1, 4, $this->kit->between(20, 30));
                $ends = $starts->copy()->addWeeks($this->kit->pick([16, 20, 24]));
                $supervisor = $this->kit->person();

                $status = $current
                    ? ($demo ? 'approved' : (string) $this->kit->weighted(['approved' => 70, 'pending' => 22, 'rejected' => 8]))
                    : 'graded';
                $scores = ['logbook_score' => null, 'report_score' => null, 'visit_score' => null, 'total_score' => null, 'is_passed' => null];
                if ($status === 'graded') {
                    $logbook = $this->kit->between(22, 38);
                    $report = $this->kit->between(20, 38);
                    $visit = $this->kit->between(10, 19);
                    $total = $logbook + $report + $visit;
                    $scores = ['logbook_score' => $logbook, 'report_score' => $report, 'visit_score' => $visit, 'total_score' => $total, 'is_passed' => $total >= 40];
                }

                $registered = $starts->copy()->subWeeks($this->kit->between(2, 6));
                $reviewed = match ($status) {
                    'graded' => $ends->copy()->addWeeks(3),
                    'pending' => null,
                    default => $registered->copy()->addDays($this->kit->between(2, 10)),
                };

                $id = $this->kit->add('siwes_placements', [
                    'student_id' => $studentId,
                    'academic_session_id' => $this->sessions[$year]['id'],
                    'employer_name' => $employer,
                    'employer_address' => $address,
                    'industry_supervisor_name' => ($supervisor['gender'] === 'male' ? 'Mr. ' : 'Mrs. ').$supervisor['first'].' '.$supervisor['last'],
                    'industry_supervisor_phone' => $this->kit->phone(),
                    'starts_on' => $starts->toDateString(),
                    'ends_on' => $ends->toDateString(),
                    'status' => $status,
                    'reviewed_by_id' => $reviewed ? $coordinator : null,
                    'created_at' => $this->dt($registered),
                    'updated_at' => $this->dt($reviewed ?? $registered),
                ] + $scores);

                if ($current && $reviewed) {
                    $this->audit('updated', 'App\\Models\\SiwesPlacement', $id, ['status' => 'pending'], ['status' => $status], $coordinator, $reviewed->min($this->now));
                }
            }
        }
    }

    /** About half of this year's finalists have started clearance. */
    private function finalYearClearance(): void
    {
        foreach ($this->roster as $studentId => $student) {
            $finalist = in_array($student['status'], ['active', 'probation'], true)
                && ($student['levels'][$this->currentYear] ?? null) === $student['final'];
            if (! $finalist || ! $this->kit->chance(0.45)) {
                continue;
            }

            $started = $this->now->copy()->subDays($this->kit->between(3, 45));
            $requestId = $this->kit->add('clearance_requests', [
                'student_id' => $studentId, 'status' => 'in_progress', 'cleared_at' => null,
                'created_at' => $this->dt($started), 'updated_at' => $this->dt($started),
            ]);

            foreach ($this->clearanceReviewers($student['dept']) as $unit => $reviewer) {
                // Offices only sign when nothing blocks them.
                $status = match ($unit) {
                    'bursary' => $student['paid'] ? ($this->kit->chance(0.7) ? 'cleared' : 'pending') : 'pending',
                    'department' => $student['carry'] === 0 && $this->kit->chance(0.5) ? 'cleared' : 'pending',
                    'library' => (string) $this->kit->weighted(['cleared' => 60, 'pending' => 30, 'rejected' => 10]),
                    default => (string) $this->kit->weighted(['cleared' => 55, 'pending' => 40, 'rejected' => 5]),
                };
                $at = $status === 'pending' ? null : $this->kit->moment($started, $this->now->copy()->subHour());
                $note = $status !== 'rejected' ? null : match ($unit) {
                    'library' => 'Two library books outstanding: return them to the circulation desk.',
                    'student_affairs' => 'Hostel damage form not yet settled.',
                    default => 'Missing documents; see the office.',
                };

                $itemId = $this->kit->add('clearance_items', [
                    'clearance_request_id' => $requestId, 'unit' => $unit, 'status' => $status, 'note' => $note,
                    'reviewed_by_id' => $at ? $reviewer : null, 'reviewed_at' => $this->dt($at),
                    'created_at' => $this->dt($started), 'updated_at' => $this->dt($at ?? $started),
                ]);
                if ($at) {
                    $this->audit('updated', 'App\\Models\\ClearanceItem', $itemId, ['status' => 'pending'], ['status' => $status], $reviewer, $at);
                }
            }
        }
    }

    private function transcripts(): void
    {
        $records = $this->office['exam-officer'];
        $fees = config('lms.transcript_fees');
        $seq = 0;
        $requests = [];

        foreach ($this->roster as $studentId => $student) {
            if ($student['graduated'] === null) {
                continue;
            }
            $count = $this->kit->chance(0.35) ? ($this->kit->chance(0.3) ? 2 : 1) : 0;
            for ($i = 0; $i < $count; $i++) {
                $from = $student['graduated']->copy()->addDays(45);
                if ($from->gte($this->now)) {
                    continue;
                }
                $requests[] = [$studentId, $this->kit->moment($from, $this->now->copy()->subHours(6))];
            }
        }
        usort($requests, fn ($a, $b) => $a[1] <=> $b[1]);

        foreach ($requests as [$studentId, $at]) {
            [$recipient, $destination, $email, $address] = $this->kit->pick(self::RECIPIENTS);
            $delivery = $email === null || $this->kit->chance(0.35) ? 'hard_copy' : 'e_copy';
            $fee = (float) $fees[$destination][$delivery];
            $age = $at->diffInDays($this->now);

            $status = $age > 45
                ? (string) $this->kit->weighted(['dispatched' => 93, 'rejected' => 3, 'processing' => 4])
                : (string) $this->kit->weighted(['awaiting_payment' => 25, 'paid' => 30, 'processing' => 30, 'dispatched' => 15]);
            $paid = $status !== 'awaiting_payment';
            $year = $this->sessionYearOf($at);
            $invoiceId = $this->serviceInvoice($studentId, $year, 'transcript', 'TRN', 'Transcript ('.($destination === 'local' ? 'local' : 'international').', '.str_replace('_', '-', $delivery).')', $fee, $at, $paid);

            $processed = in_array($status, ['processing', 'dispatched', 'rejected'], true) ? $at->copy()->addDays($this->kit->between(2, 10))->min($this->now->copy()->subHours(2)) : null;
            $dispatched = $status === 'dispatched' ? $processed->copy()->addDays($this->kit->between(1, 7))->min($this->now->copy()->subHour()) : null;

            $id = $this->kit->add('transcript_requests', [
                'number' => sprintf('TR-%d-%05d', $at->year, ++$seq),
                'student_id' => $studentId,
                'invoice_id' => $invoiceId,
                'destination' => $destination,
                'delivery' => $delivery,
                'recipient_name' => $recipient,
                'recipient_address' => $delivery === 'hard_copy' ? $address : null,
                'recipient_email' => $delivery === 'e_copy' ? $email : null,
                'purpose' => $this->kit->pick(['Postgraduate admission', 'Employment', 'Credential evaluation', 'Professional certification']),
                'fee' => $fee,
                'status' => $status,
                'dispatch_reference' => $dispatched ? ($delivery === 'hard_copy' ? 'DHL-'.$this->kit->digits(10) : 'EML-'.$this->kit->alnum(10)) : null,
                'processed_by_id' => $processed ? $records : null,
                'processed_at' => $this->dt($processed),
                'dispatched_at' => $this->dt($dispatched),
                'remarks' => $status === 'rejected' ? 'Recipient address incomplete; please submit a new request.' : null,
                'created_at' => $this->dt($at),
                'updated_at' => $this->dt($dispatched ?? $processed ?? $at),
            ]);

            $this->kit->checkpoint();
            if ($processed && $processed->gt($this->now->copy()->subDays(60))) {
                $this->audit('updated', 'App\\Models\\TranscriptRequest', $id, ['status' => 'paid'], ['status' => $status], $records, $dispatched ?? $processed);
            }
        }
    }

    private function sessionYearOf(Carbon $at): int
    {
        $year = $at->lt(Carbon::create($at->year, 11, 3)) ? $at->year - 1 : $at->year;

        return min($this->currentYear, max(Catalogue::FOUNDED, $year));
    }

    /**
     * In-app notifications people would have received recently, and an
     * audit trail of the last few months' changes.
     */
    protected function buildActivityFeeds(): void
    {
        $this->kit->flush();
        $this->notifications();
        $this->auditFeed();
    }

    private function notify(int $userId, string $kind, string $title, string $body, string $url, Carbon $at, bool $read): void
    {
        $this->kit->add('notifications', [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\'.$kind,
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'data' => json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'kind' => $kind]),
            'read_at' => $read ? $this->dt($at->copy()->addHours($this->kit->between(1, 72))) : null,
            'created_at' => $this->dt($at),
            'updated_at' => $this->dt($at),
        ], false);
        $this->kit->checkpoint();
    }

    private function notifications(): void
    {
        // Result releases for the most recently closed semester.
        [$year, $sem] = $this->currentSemester === 2 ? [$this->currentYear, 1] : [$this->currentYear - 1, 2];
        $semesterId = $this->sessions[$year]['sem'][$sem];
        $semesterName = DB::table('semesters')->where('id', $semesterId)->value('name');
        $released = DB::table('results')
            ->join('registered_courses', 'registered_courses.id', '=', 'results.registered_course_id')
            ->join('course_registrations', 'course_registrations.id', '=', 'registered_courses.course_registration_id')
            ->join('students', 'students.id', '=', 'course_registrations.student_id')
            ->join('courses', 'courses.id', '=', 'registered_courses.course_id')
            ->where('course_registrations.semester_id', $semesterId)
            ->whereIn('students.status', ['active', 'probation', 'spillover'])
            ->get(['students.user_id', 'courses.code', 'courses.title', 'results.updated_at']);

        foreach ($released as $row) {
            $at = Carbon::parse($row->updated_at);
            $this->notify($row->user_id, 'ResultsPublished', "Result released: {$row->code}", "Your {$row->code} ({$row->title}) result for the {$semesterName} has been approved and released.", '/student/results', $at, $this->kit->chance(0.7));
        }

        // Recent successful payments.
        $payments = DB::table('payments')
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->where('payments.status', 'successful')
            ->where('payments.paid_at', '>=', $this->now->copy()->subDays(120))
            ->get(['students.user_id', 'payments.amount', 'payments.reference', 'payments.paid_at']);
        foreach ($payments as $p) {
            $amount = '₦'.number_format((float) $p->amount, 2);
            $this->notify($p->user_id, 'PaymentReceived', "Payment received: {$amount}", "We received your payment of {$amount} (ref {$p->reference}). Thank you.", '/student/fees', Carbon::parse($p->paid_at), $this->kit->chance(0.6));
        }

        foreach ($this->openApplicants as $applicant) {
            if (in_array($applicant['status'], ['admitted', 'accepted'], true)) {
                $this->notify($applicant['user'], 'AdmissionOffered', 'Admission offered', 'Congratulations! You have been offered provisional admission. Log in to accept your offer and print your admission letter.', '/apply', $applicant['at'], $applicant['status'] === 'accepted' && ! $applicant['demo']);
            }
        }

        // Institution-wide notices for the registry and administrators.
        foreach ([$this->office['super-admin'], $this->office['registrar']] as $userId) {
            $this->notify($userId, 'PortalNotification', 'Results awaiting Senate', 'Faculty boards have forwarded course results for Senate approval.', '/staff/approvals', $this->now->copy()->subDays(2), false);
            $this->notify($userId, 'PortalNotification', 'Fee schedule published', 'The Bursary has published the '.$this->sessionName($this->currentYear + 1).' fee schedule.', '/bursary/fees', Carbon::create($this->currentYear + 1, 8, 30, 11)->min($this->now->copy()->subDays(20)), true);
            $this->notify($userId, 'PortalNotification', 'Graduands awaiting approval', 'Cleared students are waiting to be graduated and stamped for NYSC mobilisation.', '/records/graduation', $this->now->copy()->subDays(6), false);
        }

        $demo = $this->roster[$this->demoStudentId];
        $this->notify($demo['user'], 'HostelReserved', 'Hostel bed confirmed', 'Your bed space is confirmed for the session. Welcome to your hall!', '/student/hostel', Carbon::create($this->currentYear, 11, 20, 10), true);
        $this->notify($demo['user'], 'SiwesUpdated', 'SIWES placement approved', 'Your SIWES placement at Interswitch Group has been approved by the coordinator.', '/student/siwes', $this->now->copy()->subDays(9), false);

        $leave = DB::table('leave_requests')->join('staff', 'staff.id', '=', 'leave_requests.staff_id')
            ->whereIn('leave_requests.status', ['approved', 'rejected'])
            ->where('leave_requests.reviewed_at', '>=', $this->now->copy()->subDays(60))
            ->get(['staff.user_id', 'leave_requests.status', 'leave_requests.type', 'leave_requests.starts_on', 'leave_requests.reviewed_at']);
        foreach ($leave as $l) {
            $this->notify($l->user_id, 'LeaveDecided', 'Leave request '.$l->status, ucfirst($l->type)." leave from {$l->starts_on} was {$l->status}.", '/staff/leave', Carbon::parse($l->reviewed_at), $this->kit->chance(0.7));
        }
    }

    private function auditFeed(): void
    {
        $since = $this->now->copy()->subDays(90);

        // Score entry and amendments on the current semester's sheets.
        $semesterId = $this->sessions[$this->currentYear]['sem'][$this->currentSemester];
        $results = DB::table('results')
            ->join('registered_courses', 'registered_courses.id', '=', 'results.registered_course_id')
            ->join('course_registrations', 'course_registrations.id', '=', 'registered_courses.course_registration_id')
            ->where('course_registrations.semester_id', $semesterId)
            ->get(['results.id', 'results.entered_by_id', 'results.total_score', 'results.status', 'results.created_at', 'registered_courses.course_id']);

        foreach ($results->shuffle()->take(260) as $r) {
            $user = $this->staffUsers[$r->entered_by_id] ?? null;
            $at = Carbon::parse($r->created_at);
            $this->audit('created', 'App\\Models\\Result', $r->id, null, ['total_score' => $r->total_score, 'status' => 'pending'], $user, $at);
            if ($this->kit->chance(0.12)) {
                $this->audit('updated', 'App\\Models\\Result', $r->id, ['total_score' => (float) $r->total_score - $this->kit->between(2, 6)], ['total_score' => $r->total_score], $user, $at->copy()->addHours($this->kit->between(2, 40))->min($this->now));
            }
        }

        // Approval-stage sign-offs, one entry per course and stage, like the approval queue writes.
        $deptOf = array_map(fn ($c) => $c['dept'], $this->courses);
        foreach ($results->groupBy('course_id') as $courseId => $group) {
            $dept = $deptOf[$courseId];
            $faculty = $this->departments[$dept]['faculty'];
            $ids = $group->pluck('id')->all();
            $at = Carbon::parse($group->max('created_at'));
            foreach ([['hod_approved', $this->hodUsers[$dept] ?? null], ['faculty_approved', $this->deanUsers[$faculty] ?? $this->office['registrar']], ['senate_approved', $this->office['registrar']]] as [$stage, $user]) {
                if (! $group->contains(fn ($r) => $this->stageRank($r->status) >= $this->stageRank($stage))) {
                    break;
                }
                $at = $at->copy()->addDays($this->kit->between(1, 4))->min($this->now->copy()->subMinutes(10));
                $this->audit("results.{$stage}", 'App\\Models\\Result', null, null, ['result_ids' => $ids, 'course' => $this->courses[$courseId]['code']], $user, $at, $this->courses[$courseId]['code'].' results moved to '.str_replace('_', ' ', $stage));
            }
        }

        // Teller payments the bursary confirmed.
        $tellers = DB::table('payments')->where('gateway', 'bank')->where('status', 'successful')->where('paid_at', '>=', $since)->get(['id', 'amount', 'paid_at']);
        foreach ($tellers as $p) {
            $this->audit('updated', 'App\\Models\\Payment', $p->id, ['status' => 'pending'], ['status' => 'successful', 'amount' => $p->amount], $this->office['bursar'], Carbon::parse($p->paid_at)->addHours(3)->min($this->now));
        }

        $trail = array_filter($this->auditTrail, fn ($e) => $e['at']->gte($since->copy()->subDays(120)) && $e['at']->lte($this->now));
        usort($trail, fn ($a, $b) => $a['at'] <=> $b['at']);
        foreach ($trail as $e) {
            $this->kit->add('audit_logs', [
                'user_id' => $e['user'] ?? ($e['type'] === 'App\\Models\\FeeStructure' ? $this->office['bursar'] : null),
                'event' => $e['event'],
                'auditable_type' => $e['type'],
                'auditable_id' => $e['id'],
                'old_values' => $e['old'] === null ? null : json_encode($e['old']),
                'new_values' => $e['new'] === null ? null : json_encode($e['new']),
                'description' => $e['description'],
                'ip_address' => $this->kit->pick(['102.89.', '105.112.', '197.210.', '41.58.', '129.205.']).$this->kit->between(1, 254).'.'.$this->kit->between(1, 254),
                'created_at' => $this->dt($e['at']),
            ]);
            $this->kit->checkpoint();
        }
    }

    private function stageRank(string $status): int
    {
        return ['pending' => 0, 'hod_approved' => 1, 'faculty_approved' => 2, 'senate_approved' => 3][$status] ?? 0;
    }
}
