<?php

namespace Database\Seeders;

use Database\Seeders\University\BuildsAdmissions;
use Database\Seeders\University\BuildsCampusLife;
use Database\Seeders\University\BuildsStaff;
use Database\Seeders\University\BuildsStructure;
use Database\Seeders\University\Catalogue;
use Database\Seeders\University\SimulatesStudents;
use Database\Seeders\University\Toolkit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * A university that has been running since 2014: six faculties, twelve
 * degree programmes, about 130 staff, and every student who has passed
 * through — registrations, graded results and GPA snapshots for each
 * semester, fees and payments each session, carryovers, probation and
 * withdrawals, graduations with clearance and NYSC batches, transcripts,
 * hostels, SIWES, admissions cycles, promotions and leave.
 *
 * The calendar follows today's date: sessions run November to October, so
 * the current session and semester are whatever "now" falls in. A late
 * current semester has results moving through the approval chain; the
 * next session's admissions are open.
 *
 * Run on an empty database: php artisan migrate:fresh --seed
 * Every account's password is "password".
 */
class FullUniversitySeeder extends Seeder
{
    use BuildsAdmissions;
    use BuildsCampusLife;
    use BuildsStaff;
    use BuildsStructure;
    use SimulatesStudents;

    protected Toolkit $kit;

    protected Carbon $now;

    protected string $password;

    /** Start year of the current session, e.g. 2025 for 2025/2026. */
    protected int $currentYear;

    protected int $currentSemester;

    /** How far through the current semester we are (0–1). */
    protected float $semesterProgress;

    /** @var array<string, int> role name => id */
    protected array $roleIds = [];

    /** @var list<array{event: string, type: string, id: ?int, old: ?array, new: ?array, user: ?int, at: Carbon, description: ?string}> */
    protected array $auditTrail = [];

    public function run(): void
    {
        if (DB::table('students')->exists()) {
            throw new RuntimeException('FullUniversitySeeder expects an empty database. Run: php artisan migrate:fresh --seed');
        }

        $this->call([RoleSeeder::class, GradeScaleSeeder::class, LevelSeeder::class]);

        // Scans left by an earlier seed belong to applicants that no longer exist.
        Storage::disk('local')->deleteDirectory('applicant-documents');

        $started = microtime(true);
        $this->kit = new Toolkit(Catalogue::FOUNDED);
        $this->now = Carbon::now();
        $this->password = Hash::make('password');
        $this->roleIds = DB::table('roles')->pluck('id', 'name')->all();
        $this->resolveCalendar();

        Schema::disableForeignKeyConstraints();
        DB::connection()->disableQueryLog();
        DB::beginTransaction();

        try {
            $this->step('Academic structure, sessions and fees', fn () => $this->buildStructure());
            $this->step('Staff, promotions and leave', fn () => $this->buildStaff());
            $this->step('Students, results and payments', fn () => $this->simulateStudents());
            $this->step('Admissions', fn () => $this->buildAdmissions());
            $this->step('Hostels, SIWES, clearance, transcripts', fn () => $this->buildCampusLife());
            $this->step('Notifications and audit trail', fn () => $this->buildActivityFeeds());
            $this->kit->flush();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Seeded %s students (%s active), %s staff, %s results, %s payments in %.1fs.',
            number_format(DB::table('students')->count()),
            number_format(DB::table('students')->whereIn('status', ['active', 'probation', 'spillover'])->count()),
            number_format(DB::table('staff')->count()),
            number_format(DB::table('results')->count()),
            number_format(DB::table('payments')->count()),
            microtime(true) - $started,
        ));
    }

    private function step(string $label, callable $work): void
    {
        $t = microtime(true);
        $work();
        $this->kit->flush();
        $this->command?->line(sprintf('  %-45s %5.1fs', $label, microtime(true) - $t));
    }

    /**
     * Sessions run 3 Nov – 31 Oct: first semester Nov–Mar, second Apr–Oct.
     */
    private function resolveCalendar(): void
    {
        $year = (int) $this->now->format('Y');
        $this->currentYear = $this->now->lt(Carbon::create($year, 11, 3)) ? $year - 1 : $year;

        $second = Carbon::create($this->currentYear + 1, 4, 13);
        $this->currentSemester = $this->now->gte($second) ? 2 : 1;

        [$start, $end] = $this->semesterDates($this->currentYear, $this->currentSemester);
        $this->semesterProgress = max(0, min(1, $start->diffInSeconds($this->now) / max(1, $start->diffInSeconds($end))));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function semesterDates(int $year, int $semester): array
    {
        return $semester === 1
            ? [Carbon::create($year, 11, 3), Carbon::create($year + 1, 3, 13)]
            : [Carbon::create($year + 1, 4, 13), Carbon::create($year + 1, 10, 30)];
    }

    protected function sessionName(int $year): string
    {
        return $year.'/'.($year + 1);
    }

    /** Whether a semester has finished and its results are released. */
    protected function isClosed(int $year, int $semester): bool
    {
        return $year < $this->currentYear || ($year === $this->currentYear && $semester < $this->currentSemester);
    }

    protected function dt(?Carbon $at): ?string
    {
        return $at?->format('Y-m-d H:i:s');
    }

    /** Create a user (shared password hash) and give it roles; returns the id. */
    protected function user(string $name, string $email, array $roles, Carbon $createdAt): int
    {
        $this->kit->reserveEmail($email);
        $id = $this->kit->add('users', [
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $this->dt($createdAt),
            'password' => $this->password,
            'created_at' => $this->dt($createdAt),
            'updated_at' => $this->dt($createdAt),
        ]);

        foreach ($roles as $role) {
            $this->kit->add('model_has_roles', ['role_id' => $this->roleIds[$role], 'model_type' => 'App\\Models\\User', 'model_id' => $id], false);
        }

        return $id;
    }

    protected function audit(string $event, string $type, ?int $id, ?array $old, ?array $new, ?int $user, Carbon $at, ?string $description = null): void
    {
        $this->auditTrail[] = compact('event', 'type', 'id', 'old', 'new', 'user', 'at', 'description');
    }
}
