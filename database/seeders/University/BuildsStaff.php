<?php

namespace Database\Seeders\University;

use App\Support\StaffRanks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Academic staff per department with career histories (promotions every
 * three to four years, the odd unsuccessful case), HODs and Deans, the
 * central offices with the demo logins, non-academic staff, course
 * allocations for every semester, and leave records.
 */
trait BuildsStaff
{
    /** @var array<string, list<array{id: int, user: int, rank: int, employed: Carbon}>> dept => academic staff */
    protected array $academics = [];

    /** @var array<string, int> dept => HOD user id */
    protected array $hodUsers = [];

    /** @var array<string, int> dept => HOD staff id */
    protected array $hodStaff = [];

    /** @var array<string, int> faculty => Dean user id */
    protected array $deanUsers = [];

    /** @var array<string, int> role => user id of the office holder */
    protected array $office = [];

    /** @var array<int, array<int, int>> semester id => course id => staff id */
    protected array $allocations = [];

    /** @var array<int, int> staff id => user id */
    protected array $staffUsers = [];

    protected int $demoLecturerStaff;

    /** @var list<int> */
    protected array $demoLecturerCourses = [];

    private int $academicSeq = 1;

    private int $nonAcademicSeq = 1;

    private array $ippis = [];

    /** CONUASS grade level by rank index on the university ladder. */
    private const RANK_GL = [1, 2, 3, 4, 5, 6, 7];

    protected function buildStaff(): void
    {
        $this->buildOffices();
        $this->buildAcademicStaff();
        $this->buildNonAcademicStaff();
        $this->buildAllocations();
        $this->buildLeave();
    }

    private function buildOffices(): void
    {
        $founded = Carbon::create(Catalogue::FOUNDED, 5, 1);
        $this->office['super-admin'] = $this->user('Demo Administrator', 'admin@demo.edu.ng', ['super-admin'], $founded);

        // [email, name, role, designation, staff no, GL, employed]
        foreach ([
            ['bursar@demo.edu.ng', 'Mrs. Funke Adeyemi', 'bursar', 'Bursar', 1, 15, 2014],
            ['librarian@demo.edu.ng', 'Mr. Tunde Bakare', 'librarian', 'University Librarian', 2, 15, 2014],
            ['studentaffairs@demo.edu.ng', 'Dr. Halima Musa', 'student-affairs', 'Dean of Student Affairs', 3, 15, 2015],
            ['siwes@demo.edu.ng', 'Engr. Bola Ige', 'siwes-coordinator', 'SIWES Coordinator', 4, 13, 2016],
            ['hr@demo.edu.ng', 'Mrs. Ngozi Okeke', 'hr-officer', 'Deputy Registrar (Establishment)', 5, 15, 2014],
            ['registrar@demo.edu.ng', 'Mr. Ibrahim Danjuma', 'registrar', 'Registrar', 6, 15, 2014],
            ['admissions@demo.edu.ng', 'Mrs. Folake Adeleke', 'admission-officer', 'Admissions Officer', 7, 12, 2017],
            ['examofficer@demo.edu.ng', 'Mr. Kelechi Ugwu', 'exam-officer', 'Examinations and Records Officer', 8, 12, 2016],
        ] as [$email, $name, $role, $designation, $no, $gl, $year]) {
            $employed = Carbon::create($year, $this->kit->between(1, 9), 1);
            $userId = $this->user($name, $email, [$role], $employed);
            $this->office[$role] = $userId;
            $this->nonAcademicSeq = max($this->nonAcademicSeq, $no + 1);
            $this->staffRow($userId, null, sprintf('FUTD/NA/%04d', $no), 'non_academic', $designation, 'CONTISS', $gl, $employed, $employed->copy()->addYears(3));
        }
    }

    private function buildAcademicStaff(): void
    {
        $ladder = StaffRanks::UNIVERSITY; // GA … Professor
        $deanStaff = [];
        $shape = [6, 5, 4, 4, 3, 3, 2, 1, 0, 2, 6, 4, 3]; // rank indexes, most senior first

        foreach ($this->departments as $dept => $info) {
            $intake = Catalogue::DEPARTMENTS[$dept][4];
            $size = $dept === 'GST' ? 4 : max(6, min(13, (int) round($intake * 0.7) + 3));
            $ranks = $dept === 'GST' ? [4, 3, 2, 1] : array_slice($shape, 0, $size);
            $firstYear = Catalogue::DEPARTMENTS[$dept][5];

            foreach ($ranks as $i => $rank) {
                $isHod = $i === 0;
                $demo = $dept === 'CSC' && $i === 2; // Dr. Ada Obi, Senior Lecturer
                $person = $this->kit->person($demo ? 'female' : null);

                $employedYear = match (true) {
                    $demo => 2014,
                    $rank >= 5 => $this->kit->between(max(Catalogue::FOUNDED, $firstYear - 1), max(Catalogue::FOUNDED, $firstYear) + 1),
                    $rank >= 3 => $this->kit->between(max(Catalogue::FOUNDED, $firstYear - 1), $this->currentYear - 6),
                    $rank >= 1 => $this->kit->between(max(Catalogue::FOUNDED, $firstYear), $this->currentYear - 3),
                    default => $this->kit->between(max($firstYear, $this->currentYear - 3), $this->currentYear),
                };
                $employed = Carbon::create($employedYear, $this->kit->pick([2, 6, 9, 10]), 1);

                $title = match (true) {
                    $rank === 6 => 'Prof.',
                    $rank >= 3 || $this->kit->chance(0.25) => 'Dr.',
                    in_array($info['faculty'], ['SEET'], true) => 'Engr.',
                    default => $person['gender'] === 'male' ? 'Mr.' : $this->kit->pick(['Mrs.', 'Miss']),
                };

                [$name, $email] = match (true) {
                    $demo => ['Dr. Ada Obi', 'lecturer@demo.edu.ng'],
                    $dept === 'CSC' && $isHod => ['Prof. Chukwuemeka Nwosu', 'hod@demo.edu.ng'],
                    $dept === 'IFT' && $isHod => ['Prof. Adebayo Ogundipe', 'dean@demo.edu.ng'],
                    default => ["{$title} {$person['first']} {$person['last']}", $this->kit->email($person['first'], $person['last'], 'futd.edu.ng')],
                };

                $roles = ['lecturer'];
                $deanOf = null;
                if ($isHod && $dept !== 'GST') {
                    // The most senior professor of the first department leads the faculty.
                    $facultyDepts = Catalogue::FACULTIES[$info['faculty']][1];
                    $deanDept = $info['faculty'] === 'SICT' ? 'IFT' : $facultyDepts[0];
                    if ($dept === $deanDept) {
                        $deanOf = $info['faculty'];
                        $roles[] = 'dean';
                    } else {
                        $roles[] = 'hod';
                    }
                } elseif ($isHod) {
                    $roles[] = 'hod';
                }

                $userId = $this->user($name, $email, $roles, $employed);
                [$rankSince, $history] = $this->career($rank, $employed, $demo);
                $staffId = $this->staffRow(
                    $userId, $info['id'],
                    $demo ? 'FUTD/AC/0001' : sprintf('FUTD/AC/%04d', ++$this->academicSeq),
                    'academic', $ladder[$rank], 'CONUASS', self::RANK_GL[$rank], $employed, $rankSince,
                    $demo ? ['ippis_number' => 'IPP120045', 'bank_name' => 'First Bank of Nigeria', 'account_number' => '3012345678', 'step' => 4] : [],
                );

                if ($demo) {
                    $this->demoLecturerStaff = $staffId;
                }

                $this->academics[$dept][] = ['id' => $staffId, 'user' => $userId, 'rank' => $rank, 'employed' => $employed, 'history' => $history, 'since' => $rankSince];

                if ($isHod) {
                    $this->hodUsers[$dept] = $userId;
                    $this->hodStaff[$dept] = $staffId;
                }
                if ($deanOf !== null) {
                    $this->deanUsers[$deanOf] = $userId;
                    $deanStaff[$deanOf] = $staffId;
                }
            }
        }

        // A Dean also heads their department until a successor is named;
        // here the department gets its own HOD from the next most senior.
        foreach ($this->deanUsers as $faculty => $deanUser) {
            $dept = $faculty === 'SICT' ? 'IFT' : Catalogue::FACULTIES[$faculty][1][0];
            $next = $this->academics[$dept][1];
            $this->hodUsers[$dept] = $next['user'];
            $this->hodStaff[$dept] = $next['id'];
            $this->kit->add('model_has_roles', ['role_id' => $this->roleIds['hod'], 'model_type' => 'App\\Models\\User', 'model_id' => $next['user']], false);
        }

        // HOD/Dean pointers on the structure tables.
        $this->kit->flush();
        foreach ($this->hodStaff as $dept => $staffId) {
            DB::table('departments')->where('id', $this->departments[$dept]['id'])->update(['hod_staff_id' => $staffId]);
        }
        foreach ($deanStaff as $faculty => $staffId) {
            DB::table('faculties')->where('id', $this->faculties[$faculty])->update(['dean_staff_id' => $staffId]);
        }

        $this->promotionHistory();
    }

    /**
     * When the current rank was reached, and the promotions that led there.
     *
     * @return array{0: Carbon, 1: list<array{from: int, to: int, on: Carbon}>}
     */
    private function career(int $rank, Carbon $employed, bool $demo): array
    {
        if ($demo) {
            return [Carbon::create(2021, 10, 1), [['from' => 3, 'to' => 4, 'on' => Carbon::create(2021, 10, 1)], ['from' => 2, 'to' => 3, 'on' => Carbon::create(2017, 10, 1)]]];
        }

        // Most recent promotion 0–5 years ago (or none if hired recently).
        $since = Carbon::create($this->currentYear + 1 - $this->kit->between(1, 6), 10, 1);
        if ($since->lt($employed->copy()->addYears(2)) || $rank === 0) {
            return [$employed->copy(), []];
        }

        $history = [];
        $at = $since->copy();
        $current = $rank;
        $entryFloor = $rank >= 5 ? $rank - 2 : 0; // senior staff were hired senior

        while ($current > $entryFloor && $at->gte($employed->copy()->addYears(3))) {
            $history[] = ['from' => $current - 1, 'to' => $current, 'on' => $at->copy()];
            $current--;
            $at = $at->copy()->subYears($this->kit->between(3, 4));
        }

        return [$history === [] ? $employed->copy() : $since, $history];
    }

    private function staffRow(int $userId, ?int $deptId, string $staffNo, string $type, string $designation, string $structure, int $gl, Carbon $employed, Carbon $rankSince, array $extra = []): int
    {
        do {
            $ippis = 'IPP'.$this->kit->digits(6);
        } while (isset($this->ippis[$ippis]));
        $this->ippis[$ippis] = true;

        $id = $this->kit->add('staff', $extra + [
            'user_id' => $userId,
            'department_id' => $deptId,
            'staff_no' => $staffNo,
            'type' => $type,
            'designation' => $designation,
            'salary_structure' => $structure,
            'grade_level' => $gl,
            'step' => min(9, max(1, (int) $rankSince->diffInYears($this->now) + 1)),
            'rank_since' => $rankSince->toDateString(),
            // A few new hires are still waiting on IPPIS enrolment or bank details.
            'ippis_number' => $employed->gt($this->now->copy()->subMonths(8)) && $this->kit->chance(0.7) ? null : $ippis,
            'bank_name' => $this->kit->pick(Catalogue::BANKS),
            'account_number' => $this->kit->chance(0.96) ? $this->kit->digits(10) : null,
            'employed_on' => $employed->toDateString(),
            'phone' => $this->kit->phone(),
            'is_active' => true,
            'created_at' => $this->dt($employed),
            'updated_at' => $this->dt($rankSince->max($employed)),
        ]);
        $this->staffUsers[$id] = $userId;

        return $id;
    }

    private function buildNonAcademicStaff(): void
    {
        $techDepts = ['CSC', 'IFT', 'EEE', 'MEE', 'CVE', 'PHY', 'ICH', 'AEC'];
        foreach (Catalogue::NON_ACADEMIC as [$designation, $role, $gl, $count]) {
            for ($n = 0; $n < $count; $n++) {
                $person = $this->kit->person();
                $title = $person['gender'] === 'male' ? 'Mr.' : $this->kit->pick(['Mrs.', 'Miss']);
                $employed = Carbon::create($this->kit->between(Catalogue::FOUNDED, $this->currentYear), $this->kit->pick([1, 4, 7, 10]), 1);
                $userId = $this->user("{$title} {$person['first']} {$person['last']}", $this->kit->email($person['first'], $person['last'], 'futd.edu.ng'), [], $employed);
                $dept = str_contains($designation, 'Technologist') ? $this->departments[$this->kit->pick($techDepts)]['id'] : null;
                $since = $employed->copy()->addYears(min(3, max(0, (int) $employed->diffInYears($this->now) - 1)));
                $this->staffRow($userId, $dept, sprintf('FUTD/NA/%04d', $this->nonAcademicSeq++), 'non_academic', $designation, 'CONTISS', $gl, $employed, $since->min($this->now));
            }
        }
    }

    private function promotionHistory(): void
    {
        $ladder = StaffRanks::UNIVERSITY;
        $registrar = $this->office['registrar'];
        $hr = $this->office['hr-officer'];

        foreach ($this->academics as $dept => $members) {
            $hod = $this->hodUsers[$dept] ?? $hr;

            foreach ($members as $member) {
                $recommender = $member['user'] === $hod ? $hr : $hod;

                foreach (array_reverse($member['history']) as $step) {
                    // Now and then a case fails a year before it succeeds.
                    if ($this->kit->chance(0.15)) {
                        $failed = $step['on']->copy()->subYear();
                        $this->promotion($member['id'], $ladder[$step['from']], $ladder[$step['to']], self::RANK_GL[$step['from']], self::RANK_GL[$step['to']], $failed, 'rejected', $recommender, $registrar, 'Publications not yet up to the required standard for the rank.');
                    }
                    $this->promotion($member['id'], $ladder[$step['from']], $ladder[$step['to']], self::RANK_GL[$step['from']], self::RANK_GL[$step['to']], $step['on'], 'approved', $recommender, $registrar, null);
                }

                // Open cases: due staff recommended in the last few weeks.
                $years = $member['since']->diffInYears($this->now);
                $due = $member['rank'] < 6 && $years >= 3;
                $isDemo = $member['id'] === $this->demoLecturerStaff;
                if ($due && ($isDemo || $this->kit->chance(0.4))) {
                    $effective = Carbon::create($this->currentYear + 1, 10, 1);
                    $id = $this->promotion($member['id'], $ladder[$member['rank']], $ladder[$member['rank'] + 1], self::RANK_GL[$member['rank']], self::RANK_GL[$member['rank'] + 1], $effective, 'pending', $recommender, null, null, $this->now->copy()->subDays($this->kit->between(3, 40)));
                    $this->audit('created', 'App\\Models\\Promotion', $id, null, ['status' => 'pending', 'to_designation' => $ladder[$member['rank'] + 1]], $recommender, $this->now->copy()->subDays($this->kit->between(3, 40)));
                }
            }
        }
    }

    private function promotion(int $staffId, string $from, string $to, int $fromGl, int $toGl, Carbon $effective, string $status, int $by, ?int $decidedBy, ?string $note, ?Carbon $recommendedAt = null): int
    {
        $recommendedAt ??= $effective->copy()->subMonths(4);
        $decidedAt = $status === 'pending' ? null : $effective->copy()->subMonths(1);

        return $this->kit->add('promotions', [
            'staff_id' => $staffId,
            'from_designation' => $from,
            'to_designation' => $to,
            'from_grade_level' => $fromGl,
            'to_grade_level' => $toGl,
            'effective_on' => $effective->toDateString(),
            'status' => $status,
            'justification' => $this->kit->pick([
                'Has met the publication and teaching requirements for the next rank.',
                'Satisfactory assessment by the Appointments and Promotions Committee.',
                'Completed the minimum years in rank with good annual evaluation reports.',
            ]),
            'note' => $note,
            'recommended_by_id' => $by,
            'decided_by_id' => $status === 'pending' ? null : $decidedBy,
            'decided_at' => $this->dt($decidedAt),
            'created_at' => $this->dt($recommendedAt),
            'updated_at' => $this->dt($decidedAt ?? $recommendedAt),
        ]);
    }

    /**
     * One lecturer per course per semester, from the offering department,
     * chosen among staff already employed that session.
     */
    private function buildAllocations(): void
    {
        foreach ($this->sessions as $year => $session) {
            if ($year > $this->currentYear) {
                continue;
            }

            foreach ($session['sem'] as $sem => $semesterId) {
                [$start] = $this->semesterDates($year, $sem);
                foreach ($this->courses as $courseId => $course) {
                    if ($course['sem'] !== $sem || ! $this->courseRunning($course, $year)) {
                        continue;
                    }

                    $pool = array_values(array_filter($this->academics[$course['dept']], fn ($m) => $m['employed']->lte($start) && $m['rank'] > 0));
                    $pool = $pool ?: $this->academics[$course['dept']];
                    $lecturer = $pool[($courseId + $year) % count($pool)];

                    // Dr. Ada Obi teaches the first-year computing courses.
                    if ($course['dept'] === 'CSC' && in_array($course['code'], ['CSC 101', 'CSC 102', 'CSC 103', 'CSC 104'], true)) {
                        $lecturer = ['id' => $this->demoLecturerStaff];
                        if ($year === $this->currentYear && $sem === $this->currentSemester) {
                            $this->demoLecturerCourses[] = $courseId;
                        }
                    }

                    $this->allocations[$semesterId][$courseId] = $lecturer['id'];
                    $this->kit->add('course_allocations', [
                        'course_id' => $courseId,
                        'staff_id' => $lecturer['id'],
                        'semester_id' => $semesterId,
                        'is_coordinator' => true,
                        'created_at' => $this->dt($start->copy()->subWeeks(2)),
                        'updated_at' => $this->dt($start->copy()->subWeeks(2)),
                    ]);
                }
                $this->kit->checkpoint();
            }
        }
    }

    /** Whether any cohort was at this course's level in that session. */
    protected function courseRunning(array $course, int $year): bool
    {
        if ($course['dept'] === 'GST') {
            return true;
        }

        return $year - Catalogue::DEPARTMENTS[$course['dept']][5] >= $course['rank'] - 1;
    }

    private function buildLeave(): void
    {
        $registrar = $this->office['registrar'];
        $this->kit->flush();
        $staff = DB::table('staff')->get(['id', 'user_id', 'department_id', 'type', 'employed_on']);
        $deptCodes = array_flip(array_map(fn ($d) => $d['id'], $this->departments));
        $onLeaveToday = 0;
        $pending = 0;

        foreach ($staff as $member) {
            $dept = $member->department_id ? ($deptCodes[$member->department_id] ?? null) : null;
            $reviewer = $member->type === 'academic' && $dept && ($this->hodUsers[$dept] ?? null) !== $member->user_id ? $this->hodUsers[$dept] : $registrar;

            for ($year = max((int) substr($member->employed_on, 0, 4) + 1, $this->currentYear - 2); $year <= (int) $this->now->format('Y'); $year++) {
                $requests = [];
                if ($this->kit->chance(0.85)) {
                    $requests[] = ['annual', Carbon::create($year, $this->kit->pick([1, 7, 8, 12]), $this->kit->between(2, 20)), $this->kit->between(10, 20)];
                }
                if ($this->kit->chance(0.12)) {
                    $requests[] = ['sick', Carbon::create($year, $this->kit->between(1, 12), $this->kit->between(1, 25)), $this->kit->between(3, 7)];
                }
                if ($this->kit->chance(0.15)) {
                    $requests[] = ['casual', Carbon::create($year, $this->kit->between(1, 12), $this->kit->between(1, 25)), $this->kit->between(2, 4)];
                }
                if ($member->type === 'academic' && $this->kit->chance(0.05)) {
                    $requests[] = ['study', Carbon::create($year, $this->kit->between(1, 12), $this->kit->between(1, 20)), $this->kit->between(10, 20)];
                }

                foreach ($requests as [$type, $start, $days]) {
                    $start = $this->weekday($start);
                    $end = $this->addWorkingDays($start, $days - 1);
                    $filed = $start->copy()->subDays($this->kit->between(7, 30));

                    if ($filed->gt($this->now)) {
                        continue;
                    }

                    $status = $start->gt($this->now) ? 'pending' : ($this->kit->chance(0.05) ? 'rejected' : 'approved');
                    if ($status === 'pending' && $pending >= 10) {
                        continue;
                    }
                    $pending += $status === 'pending' ? 1 : 0;
                    $onLeaveToday += $status === 'approved' && $start->lte($this->now) && $end->gte($this->now) ? 1 : 0;

                    $reviewedAt = $status === 'pending' ? null : $filed->copy()->addDays($this->kit->between(1, 5));
                    $id = $this->kit->add('leave_requests', [
                        'staff_id' => $member->id,
                        'type' => $type,
                        'starts_on' => $start->toDateString(),
                        'ends_on' => $end->toDateString(),
                        'days' => $days,
                        'reason' => $this->kit->pick(Catalogue::LEAVE_REASONS[$type]),
                        'status' => $status,
                        'note' => $status === 'rejected' ? 'Clashes with the examination period; please reschedule.' : null,
                        'reviewed_by_id' => $status === 'pending' ? null : $reviewer,
                        'reviewed_at' => $this->dt($reviewedAt),
                        'created_at' => $this->dt($filed),
                        'updated_at' => $this->dt($reviewedAt ?? $filed),
                    ]);

                    if ($reviewedAt && $reviewedAt->gt($this->now->copy()->subDays(60))) {
                        $this->audit('updated', 'App\\Models\\LeaveRequest', $id, ['status' => 'pending'], ['status' => $status], $reviewer, $reviewedAt);
                    }
                }
            }
        }

        // Make sure the HR dashboard has someone away today.
        if ($onLeaveToday === 0) {
            $member = $staff->random();
            $start = $this->weekday($this->now->copy()->subDays(3));
            $this->kit->add('leave_requests', [
                'staff_id' => $member->id, 'type' => 'annual', 'starts_on' => $start->toDateString(),
                'ends_on' => $this->addWorkingDays($start, 9)->toDateString(), 'days' => 10, 'reason' => 'Annual leave', 'status' => 'approved', 'note' => null,
                'reviewed_by_id' => $registrar, 'reviewed_at' => $this->dt($start->copy()->subWeek()),
                'created_at' => $this->dt($start->copy()->subWeeks(2)), 'updated_at' => $this->dt($start->copy()->subWeek()),
            ]);
        }
    }

    protected function weekday(Carbon $date): Carbon
    {
        $date = $date->copy()->setTime(0, 0);
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }

    protected function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $date = $date->copy();
        while ($days > 0) {
            $date->addDay();
            if (! $date->isWeekend()) {
                $days--;
            }
        }

        return $date;
    }
}
