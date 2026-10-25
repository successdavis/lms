<?php

namespace Database\Seeders\University;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Institution, faculties, departments, programmes, the course catalogue
 * and curricula, every session since founding (plus next session), the
 * fee schedule for each, hostels and admission cycles.
 */
trait BuildsStructure
{
    protected array $faculties = [];

    protected array $departments = [];

    protected array $programmes = [];

    /** @var array<int, int> level rank => level id */
    protected array $levels = [];

    /** @var array<int, array{code: string, title: string, units: int, dept: string, rank: int, sem: int}> */
    protected array $courses = [];

    /** @var array<string, array<int, array<int, list<int>>>> dept => rank => semester => course ids */
    protected array $curriculum = [];

    /** @var array<int, array{id: int, name: string, sem: array<int, int>}> */
    protected array $sessions = [];

    protected array $feeTypes = [];

    /** @var array<int, array<string, mixed>> year => amounts */
    protected array $fees = [];

    protected array $hostels = [];

    /** @var array<int, array{id: int, fee: float, lists: array<string, int>}> */
    protected array $cycles = [];

    /** Session whose admission cycle is open now. */
    protected int $admissionsYear;

    /** NUC grade scale: bands, thresholds and classification bands. */
    protected array $scale = [];

    protected function buildStructure(): void
    {
        $founded = Carbon::create(Catalogue::FOUNDED, 6, 1);

        DB::table('institutions')->insert([
            'name' => 'Federal University of Technology, Demo',
            'short_name' => 'FUTD',
            'type' => 'university',
            'motto' => 'Technology for Development',
            'address' => 'Km 10, Airport Road, Owerri-Port Harcourt Expressway, Imo State',
            'email' => 'registrar@futd.edu.ng',
            'phone' => '08031234500',
            'matric_format' => '{SHORT}/{YY}/{DEPT}/{SEQ:4}',
            'min_fee_percent_for_registration' => 100,
            'created_at' => $this->dt($founded),
            'updated_at' => $this->dt($founded),
        ]);

        $this->levels = DB::table('levels')->whereIn('code', ['100', '200', '300', '400', '500'])->pluck('id', 'rank')->all();

        $nuc = DB::table('grade_scales')->where('slug', 'nuc-5-point')->first();
        $this->scale = [
            'id' => $nuc->id,
            'probation' => (float) $nuc->probation_cgpa,
            'withdrawal' => $nuc->withdrawal_cgpa === null ? null : (float) $nuc->withdrawal_cgpa,
            'bands' => DB::table('grade_scale_bands')->where('grade_scale_id', $nuc->id)->orderByDesc('min_score')->get()
                ->map(fn ($b) => ['letter' => $b->letter, 'min' => (float) $b->min_score, 'point' => (float) $b->point, 'pass' => (bool) $b->is_pass])->all(),
            'classes' => DB::table('classification_bands')->where('grade_scale_id', $nuc->id)->orderBy('sort')->get()
                ->map(fn ($c) => ['name' => $c->name, 'min' => (float) $c->min_cgpa])->all(),
        ];

        $this->buildFaculties($founded);
        $this->buildCourses($founded);
        $this->buildSessions();
        $this->buildFees();
        $this->buildHostels($founded);
        $this->buildCycles();
    }

    private function buildFaculties(Carbon $at): void
    {
        foreach (Catalogue::FACULTIES as $code => [$name, $departments]) {
            $facultyId = $this->kit->add('faculties', ['name' => $name, 'code' => $code, 'created_at' => $this->dt($at), 'updated_at' => $this->dt($at)]);
            $this->faculties[$code] = $facultyId;

            foreach ($departments as $deptCode) {
                [$deptName, $programme, $award, $years, $intake, $first, $group] = Catalogue::DEPARTMENTS[$deptCode];
                $deptId = $this->kit->add('departments', ['faculty_id' => $facultyId, 'name' => $deptName, 'code' => $deptCode, 'created_at' => $this->dt($at), 'updated_at' => $this->dt($at)]);
                $this->departments[$deptCode] = ['id' => $deptId, 'faculty' => $code, 'faculty_id' => $facultyId, 'name' => $deptName];

                if ($programme === null) {
                    continue;
                }

                $opened = Carbon::create($first, 6, 1);
                $this->programmes[$deptCode] = [
                    'id' => $this->kit->add('programmes', [
                        'department_id' => $deptId,
                        'grade_scale_id' => $this->scale['id'],
                        'name' => $programme,
                        'code' => $award.'-'.$deptCode,
                        'award' => $award,
                        'duration_semesters' => $years * 2,
                        'entry_level_id' => $this->levels[1],
                        'min_units_per_semester' => 15,
                        'max_units_per_semester' => 24,
                        'is_active' => true,
                        'created_at' => $this->dt($opened),
                        'updated_at' => $this->dt($opened),
                    ]),
                    'dept' => $deptCode,
                    'name' => $programme,
                    'award' => $award,
                    'years' => $years,
                    'intake' => $intake,
                    'first' => $first,
                    'group' => $group,
                ];
            }
        }
    }

    private function buildCourses(Carbon $at): void
    {
        $gst = [];
        foreach (Catalogue::GST as [$code, $title, $units, $rank, $sem]) {
            $gst[$rank][$sem][] = $this->course('GST', $code, $title, $units, $rank, $sem, $at);
        }

        foreach ($this->programmes as $deptCode => $programme) {
            $topics = Catalogue::TOPICS[$deptCode];
            $offset = 0;

            for ($rank = 1; $rank <= $programme['years']; $rank++) {
                $count = $rank <= 2 ? 5 : 6;
                $levelTopics = array_slice($topics, $offset, $count);
                $offset += $count;

                foreach ([1, 2] as $sem) {
                    foreach ($levelTopics as $i => $topic) {
                        $number = $rank * 100 + $i * 2 + $sem;
                        $title = $topic === 'Final Year Project' ? ($sem === 1 ? 'Final Year Project (Proposal and Seminar)' : 'Final Year Project (Report and Defence)') : $topic.($sem === 1 ? ' I' : ' II');
                        $units = $topic === 'Final Year Project' ? 3 : Catalogue::UNITS[$i];
                        $this->curriculum[$deptCode][$rank][$sem][] = $this->course($deptCode, "{$deptCode} {$number}", $title, $units, $rank, $sem, $at);
                    }

                    foreach ($gst[$rank][$sem] ?? [] as $courseId) {
                        $this->curriculum[$deptCode][$rank][$sem][] = $courseId;
                    }
                }
            }

            // Curriculum rows, typed core/required/general.
            foreach ($this->curriculum[$deptCode] as $rank => $semesters) {
                foreach ($semesters as $sem => $courseIds) {
                    foreach ($courseIds as $i => $courseId) {
                        $type = $this->courses[$courseId]['dept'] === 'GST' ? 'general' : ($i < 3 ? 'core' : 'required');
                        $this->kit->add('programme_courses', [
                            'programme_id' => $programme['id'],
                            'course_id' => $courseId,
                            'level_id' => $this->levels[$rank],
                            'semester_number' => $sem,
                            'type' => $type,
                            'created_at' => $this->dt($at),
                            'updated_at' => $this->dt($at),
                        ]);
                    }
                }
            }
        }

        // Second-semester "II" courses build on their "I" counterpart.
        $firstSemester = [];
        foreach ($this->courses as $id => $course) {
            if ($course['sem'] === 1) {
                $firstSemester[$course['dept'].'|'.$course['title']] = $id;
            }
        }
        foreach ($this->courses as $id => $course) {
            $key = $course['dept'].'|'.substr($course['title'], 0, -1);
            if ($course['sem'] === 2 && str_ends_with($course['title'], ' II') && isset($firstSemester[$key])) {
                $this->kit->add('course_prerequisites', ['course_id' => $id, 'prerequisite_id' => $firstSemester[$key], 'created_at' => $this->dt($at), 'updated_at' => $this->dt($at)]);
            }
        }
    }

    private function course(string $dept, string $code, string $title, int $units, int $rank, int $sem, Carbon $at): int
    {
        $id = $this->kit->add('courses', [
            'department_id' => $this->departments[$dept]['id'],
            'code' => $code,
            'title' => $title,
            'credit_units' => $units,
            'semester_number' => $sem,
            'ca_weight' => 30,
            'exam_weight' => 70,
            'is_active' => true,
            'created_at' => $this->dt($at),
            'updated_at' => $this->dt($at),
        ]);
        $this->courses[$id] = compact('code', 'title', 'units', 'dept', 'rank', 'sem');

        return $id;
    }

    private function buildSessions(): void
    {
        for ($year = Catalogue::FOUNDED; $year <= $this->currentYear + 1; $year++) {
            $created = Carbon::create($year, 9, 1);
            $sessionId = $this->kit->add('academic_sessions', [
                'name' => $this->sessionName($year),
                'starts_on' => Carbon::create($year, 11, 3)->toDateString(),
                'ends_on' => Carbon::create($year + 1, 10, 31)->toDateString(),
                'is_current' => $year === $this->currentYear,
                'created_at' => $this->dt($created),
                'updated_at' => $this->dt($created),
            ]);

            $semesters = [];
            foreach ([1 => 'First Semester (Harmattan)', 2 => 'Second Semester (Rain)'] as $number => $name) {
                [$start, $end] = $this->semesterDates($year, $number);
                $semesters[$number] = $this->kit->add('semesters', [
                    'academic_session_id' => $sessionId,
                    'number' => $number,
                    'name' => $name,
                    'starts_on' => $start->toDateString(),
                    'ends_on' => $end->toDateString(),
                    'registration_opens_at' => $this->dt($start->copy()->setTime(8, 0)),
                    'registration_closes_at' => $this->dt($start->copy()->addWeeks(4)->setTime(23, 59, 59)),
                    'late_registration_closes_at' => $this->dt($start->copy()->addWeeks(6)->setTime(23, 59, 59)),
                    'is_current' => $year === $this->currentYear && $number === $this->currentSemester,
                    'created_at' => $this->dt($created),
                    'updated_at' => $this->dt($created),
                ]);
            }

            $this->sessions[$year] = ['id' => $sessionId, 'name' => $this->sessionName($year), 'sem' => $semesters];
        }
    }

    /** Fees rise every session; acceptance is one-off, the rest recur. */
    private function buildFees(): void
    {
        $founded = Carbon::create(Catalogue::FOUNDED, 6, 1);
        foreach ([
            'SCH' => ['School Fees', true, true],
            'ACC' => ['Acceptance Fee', true, false],
            'ICT' => ['ICT/Portal Charge', false, true],
            'LAB' => ['Laboratory/Workshop Levy', false, true],
            'SIW' => ['SIWES Charge', false, true],
            'HST' => ['Hostel Accommodation', false, true],
            'TRN' => ['Transcript Fee', false, true],
        ] as $code => [$name, $blocks, $recurring]) {
            $this->feeTypes[$code] = $this->kit->add('fee_types', [
                'name' => $name, 'code' => $code, 'blocks_registration' => $blocks, 'is_recurring' => $recurring,
                'created_at' => $this->dt($founded), 'updated_at' => $this->dt($founded),
            ]);
        }

        for ($year = Catalogue::FOUNDED; $year <= $this->currentYear + 1; $year++) {
            $i = $year - Catalogue::FOUNDED;
            $amounts = [
                'SCH' => 40000 + 4500 * $i,
                'ACC' => 25000 + 2500 * $i,
                'ICT' => 5000 + 500 * $i,
                'LAB' => ['science' => 10000 + 800 * $i, 'engineering' => 15000 + 1000 * $i],
                'SIW' => 7500 + 250 * $i,
            ];
            $this->fees[$year] = $amounts;

            // The bursary publishes the schedule ahead of each session.
            $published = Carbon::create($year, 8, $this->kit->between(4, 28), 10);
            $base = ['academic_session_id' => $this->sessions[$year]['id'], 'programme_id' => null, 'level_id' => null, 'entry_mode' => null, 'indigene_scope' => null, 'created_at' => $this->dt($published), 'updated_at' => $this->dt($published)];

            foreach (['SCH', 'ACC', 'ICT'] as $code) {
                $id = $this->kit->add('fee_structures', ['fee_type_id' => $this->feeTypes[$code], 'amount' => $amounts[$code]] + $base);
                if ($year === $this->currentYear + 1) {
                    $this->audit('created', 'App\\Models\\FeeStructure', $id, null, ['fee_type' => $code, 'amount' => $amounts[$code]], null, $published, null);
                }
            }

            foreach ($this->programmes as $dept => $programme) {
                if (isset($amounts['LAB'][$programme['group']])) {
                    $this->kit->add('fee_structures', ['fee_type_id' => $this->feeTypes['LAB'], 'amount' => $amounts['LAB'][$programme['group']], 'programme_id' => $programme['id']] + $base);
                }
                if (isset(Catalogue::SIWES_LEVEL[$dept])) {
                    $this->kit->add('fee_structures', ['fee_type_id' => $this->feeTypes['SIW'], 'amount' => $amounts['SIW'], 'programme_id' => $programme['id'], 'level_id' => $this->levels[Catalogue::SIWES_LEVEL[$dept]]] + $base);
                }
            }
        }
    }

    /** Invoice lines for a student in a session, as the invoice generator would build them. */
    protected function feeLines(int $year, string $dept, int $rank, bool $firstSession): array
    {
        $fees = $this->fees[$year];
        $group = $this->programmes[$dept]['group'];
        $lines = [['SCH', 'School Fees', $fees['SCH']]];

        if ($firstSession) {
            $lines[] = ['ACC', 'Acceptance Fee', $fees['ACC']];
        }
        $lines[] = ['ICT', 'ICT/Portal Charge', $fees['ICT']];
        if (isset($fees['LAB'][$group])) {
            $lines[] = ['LAB', 'Laboratory/Workshop Levy', $fees['LAB'][$group]];
        }
        if ((Catalogue::SIWES_LEVEL[$dept] ?? null) === $rank) {
            $lines[] = ['SIW', 'SIWES Charge', $fees['SIW']];
        }

        return $lines;
    }

    private function buildHostels(Carbon $at): void
    {
        foreach ([
            ['Nnamdi Azikiwe Hall', 'male', 35000, 'NA', 24, 4],
            ['Obafemi Awolowo Hall', 'male', 45000, 'OA', 20, 4],
            ['Ahmadu Bello Hall', 'male', 60000, 'AB', 10, 2],
            ['Queen Amina Hall', 'female', 35000, 'QA', 24, 4],
            ['Funmilayo Ransome-Kuti Hall', 'female', 45000, 'FR', 18, 4],
            ['Margaret Ekpo Hall', 'female', 60000, 'ME', 10, 2],
        ] as [$name, $gender, $fee, $prefix, $rooms, $beds]) {
            $hostelId = $this->kit->add('hostels', ['name' => $name, 'gender' => $gender, 'fee' => $fee, 'is_active' => true, 'created_at' => $this->dt($at), 'updated_at' => $this->dt($at)]);
            $roomList = [];
            for ($n = 1; $n <= $rooms; $n++) {
                $number = $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                $roomList[] = ['id' => $this->kit->add('hostel_rooms', ['hostel_id' => $hostelId, 'number' => $number, 'capacity' => $beds, 'created_at' => $this->dt($at), 'updated_at' => $this->dt($at)]), 'capacity' => $beds, 'number' => $number];
            }
            $this->hostels[] = ['id' => $hostelId, 'name' => $name, 'gender' => $gender, 'fee' => $fee, 'rooms' => $roomList];
        }
    }

    private function buildCycles(): void
    {
        // The next session's cycle opens in February; before that the
        // current session's cycle is still the active one.
        $nextOpens = Carbon::create($this->currentYear + 1, 2, 1, 8);
        $this->admissionsYear = $this->now->gte($nextOpens) ? $this->currentYear + 1 : $this->currentYear;

        for ($year = Catalogue::FOUNDED; $year <= $this->currentYear + 1; $year++) {
            $opens = Carbon::create($year, 2, 1, 8);
            $fee = $year >= 2022 ? 2500 : 2000;
            $cycleId = $this->kit->add('admission_cycles', [
                'academic_session_id' => $this->sessions[$year]['id'],
                'opens_at' => $this->dt($opens),
                'closes_at' => $this->dt(Carbon::create($year, 12, 15, 23, 59, 59)),
                'utme_weight' => 60,
                'post_utme_weight' => 40,
                'default_cutoff' => 50,
                'application_fee' => $fee,
                'is_active' => $year === $this->admissionsYear,
                'created_at' => $this->dt($opens->copy()->subWeeks(2)),
                'updated_at' => $this->dt($opens->copy()->subWeeks(2)),
            ]);

            $lists = [];
            if ($year >= $this->currentYear - 2 && $year <= $this->admissionsYear) {
                $merit = Carbon::create($year, 10, 5, 12);
                $supplementary = Carbon::create($year, 10, 26, 12);
                foreach ([['Merit List', 1, $merit], ['Supplementary List', 2, $supplementary]] as [$name, $sort, $published]) {
                    $published = $published->lte($this->now) ? $published : null;
                    $lists[$name] = $this->kit->add('admission_lists', [
                        'admission_cycle_id' => $cycleId, 'name' => $name, 'sort' => $sort, 'published_at' => $this->dt($published),
                        'created_at' => $this->dt(Carbon::create($year, 9, 20)), 'updated_at' => $this->dt($published ?? Carbon::create($year, 9, 20)),
                    ]);
                }
            }

            $this->cycles[$year] = ['id' => $cycleId, 'fee' => $fee, 'lists' => $lists];
        }
    }
}
