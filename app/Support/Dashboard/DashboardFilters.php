<?php

namespace App\Support\Dashboard;

use App\Models\AcademicSession;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The session/semester and date-range filters shared by every dashboard.
 * Unknown or out-of-range values fall back to the defaults rather than
 * erroring, so a stale bookmarked URL still opens.
 */
class DashboardFilters
{
    /** Longest date range a dashboard will chart. */
    public const MAX_MONTHS = 24;

    public function __construct(
        public readonly ?AcademicSession $session,
        public readonly ?Semester $semester,
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $session = AcademicSession::query()->find($request->integer('session_id') ?: null) ?? AcademicSession::current();

        $semester = null;
        if ($session !== null) {
            $semesters = $session->semesters()->orderBy('number')->get();
            $semester = $semesters->firstWhere('id', $request->integer('semester_id'))
                ?? $semesters->firstWhere('is_current', true)
                ?? $semesters->first();
        }

        $to = self::date($request->query('to'))?->endOfDay() ?? Carbon::now()->endOfDay();
        $from = self::date($request->query('from'))?->startOfDay() ?? $to->copy()->startOfMonth()->subMonths(5);

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $earliest = $to->copy()->startOfMonth()->subMonths(self::MAX_MONTHS - 1);
        if ($from->lt($earliest)) {
            $from = $earliest;
        }

        return new self($session, $semester, $from, $to);
    }

    /**
     * Buckets for a time series over the range: days for a range of a month
     * or less, calendar months otherwise. Keys match bucketKey().
     *
     * @return array<string, string> key => label
     */
    public function buckets(): array
    {
        $buckets = [];

        if ($this->daily()) {
            for ($day = $this->from->copy(); $day->lte($this->to); $day->addDay()) {
                $buckets[$day->format('Y-m-d')] = $day->format('j M');
            }

            return $buckets;
        }

        for ($month = $this->from->copy()->startOfMonth(); $month->lte($this->to); $month->addMonth()) {
            $buckets[$month->format('Y-m')] = $month->format('M Y');
        }

        return $buckets;
    }

    public function bucketKey(Carbon $at): string
    {
        return $at->format($this->daily() ? 'Y-m-d' : 'Y-m');
    }

    public function daily(): bool
    {
        return $this->from->diffInDays($this->to) <= 31;
    }

    public function rangeLabel(): string
    {
        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'session_id' => $this->session?->id,
            'semester_id' => $this->semester?->id,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }

    /** Sessions (newest first) with their semesters, for the pickers. */
    public static function sessionOptions(): array
    {
        return AcademicSession::query()
            ->with(['semesters' => fn ($q) => $q->orderBy('number')])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicSession $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'semesters' => $s->semesters->map(fn (Semester $sem) => ['id' => $sem->id, 'name' => $sem->name])->values(),
            ])
            ->all();
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
