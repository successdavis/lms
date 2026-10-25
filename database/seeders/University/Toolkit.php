<?php

namespace Database\Seeders\University;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers for the full seed: seeded randomness, Nigerian people, and bulk
 * inserts with IDs assigned up front (so related rows can be built before
 * anything is written). Bulk inserts skip model events on purpose: ten
 * years of history should not raise audit entries or notifications.
 */
final class Toolkit
{
    /** @var array<string, int> next id per table */
    private array $ids = [];

    /** @var array<string, list<array<string, mixed>>> rows waiting to be written */
    private array $buffer = [];

    private array $usedEmails = [];

    /** @var array<string, array<string, int>> */
    private array $columns = [];

    private ?bool $sqlite = null;

    private int $buffered = 0;

    /** Insert order: every table after the tables it references. */
    private const ORDER = [
        'users', 'model_has_roles', 'faculties', 'departments', 'programmes', 'courses', 'programme_courses', 'course_prerequisites',
        'academic_sessions', 'semesters', 'fee_types', 'fee_structures', 'hostels', 'hostel_rooms', 'admission_cycles', 'admission_lists',
        'staff', 'promotions', 'course_allocations', 'leave_requests', 'students', 'invoices', 'invoice_items', 'course_registrations',
        'registered_courses', 'results', 'semester_results', 'clearance_requests', 'clearance_items', 'applicants', 'applicant_documents',
        'payments', 'hostel_allocations', 'siwes_placements', 'transcript_requests', 'notifications', 'audit_logs',
    ];

    public function __construct(int $seed)
    {
        mt_srand($seed);
    }

    // ── randomness ────────────────────────────────────────────────────

    public function chance(float $p): bool
    {
        return mt_rand() / mt_getrandmax() < $p;
    }

    public function between(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    /** Weighted pick from [value => weight]. */
    public function weighted(array $weights): string|int
    {
        $roll = mt_rand(1, (int) array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    public function normal(float $mean, float $sd): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    /** A random moment between two dates, during working hours. */
    public function moment(CarbonInterface $from, CarbonInterface $to): Carbon
    {
        $start = $from->getTimestamp();
        $end = max($start, $to->getTimestamp());

        return Carbon::createFromTimestamp(mt_rand($start, $end))->setTime($this->between(8, 17), $this->between(0, 59), $this->between(0, 59));
    }

    public function alnum(int $length): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $out;
    }

    public function digits(int $length): string
    {
        $out = (string) mt_rand(1, 9);
        for ($i = 1; $i < $length; $i++) {
            $out .= mt_rand(0, 9);
        }

        return $out;
    }

    // ── people ────────────────────────────────────────────────────────

    /**
     * @return array{first: string, last: string, gender: string, state: string, lga: string}
     */
    public function person(?string $gender = null): array
    {
        $group = Catalogue::PEOPLE[$this->weighted(Catalogue::MIX)];
        $gender ??= $this->chance(0.52) ? 'male' : 'female';
        $state = array_rand($group['states']);

        return [
            'first' => $this->pick($group[$gender]),
            'last' => $this->pick($group['surnames']),
            'gender' => $gender,
            'state' => $state,
            'lga' => $this->pick($group['states'][$state]),
        ];
    }

    public function phone(): string
    {
        return $this->pick(['080', '081', '070', '090', '091', '080', '081']).$this->digits(8);
    }

    public function email(string $first, string $last, string $domain): string
    {
        $base = strtolower(preg_replace('/[^a-z]/i', '', $first).'.'.preg_replace('/[^a-z]/i', '', $last));
        $email = "{$base}@{$domain}";
        $n = 1;
        while (isset($this->usedEmails[$email])) {
            $email = $base.(++$n)."@{$domain}";
        }
        $this->usedEmails[$email] = true;

        return $email;
    }

    public function reserveEmail(string $email): void
    {
        $this->usedEmails[$email] = true;
    }

    // ── bulk writes ───────────────────────────────────────────────────

    public function nextId(string $table): int
    {
        if (! isset($this->ids[$table])) {
            $this->ids[$table] = (int) DB::table($table)->max('id') + 1;
        }

        return $this->ids[$table]++;
    }

    /** Queue a row (assigning its id unless the table has none); returns the id. */
    public function add(string $table, array $row, bool $withId = true): int
    {
        if ($withId && ! isset($row['id'])) {
            $row = ['id' => $this->nextId($table)] + $row;
        }

        $this->buffer[$table][] = $row;
        $this->buffered++;

        return is_int($row['id'] ?? null) ? $row['id'] : 0;
    }

    /**
     * Write everything once enough has piled up. Call only between whole
     * records, when every row's parents have been queued too.
     */
    public function checkpoint(int $threshold = 3000): void
    {
        if ($this->buffered >= $threshold) {
            $this->flush();
        }
    }

    /** Write all queued rows, parents before children, so foreign keys hold. */
    public function flush(): void
    {
        $tables = array_keys($this->buffer);
        usort($tables, fn ($a, $b) => (array_search($a, self::ORDER, true) === false ? 999 : array_search($a, self::ORDER, true))
            <=> (array_search($b, self::ORDER, true) === false ? 999 : array_search($b, self::ORDER, true)));

        foreach ($tables as $name) {
            $rows = $this->buffer[$name] ?? [];
            if ($rows === []) {
                continue;
            }

            // Keep only real columns (some pivot tables have no timestamps).
            $this->columns[$name] ??= array_flip(Schema::getColumnListing($name));
            $rows = array_map(fn ($row) => array_intersect_key($row, $this->columns[$name]), $rows);

            // Store plain dates the way Eloquent's date casts write them on
            // SQLite ("Y-m-d 00:00:00"), so string comparisons in the app
            // treat seeded and app-written rows alike.
            if ($this->sqlite ??= DB::connection()->getDriverName() === 'sqlite') {
                $rows = array_map(fn ($row) => array_map(
                    fn ($v) => is_string($v) && strlen($v) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v.' 00:00:00' : $v,
                    $row,
                ), $rows);
            }

            // SQLite caps bound parameters; keep each statement well under it.
            $perStatement = max(1, intdiv(30000, count($rows[0])));
            foreach (array_chunk($rows, min(500, $perStatement)) as $chunk) {
                DB::table($name)->insert($chunk);
            }

            $this->buffer[$name] = [];
        }
        $this->buffered = 0;
    }
}
