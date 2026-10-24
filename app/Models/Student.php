<?php

namespace App\Models;

use App\Enums\EntryMode;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Student extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'entry_mode' => EntryMode::class,
            'date_of_birth' => 'date',
            'is_indigene' => 'boolean',
            'graduated_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function entrySession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'entry_session_id');
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function semesterResults(): HasMany
    {
        return $this->hasMany(SemesterResult::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transcriptRequests(): HasMany
    {
        return $this->hasMany(TranscriptRequest::class);
    }

    public function clearanceRequest(): HasOne
    {
        return $this->hasOne(ClearanceRequest::class);
    }

    public function hostelAllocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class);
    }

    public function siwesPlacements(): HasMany
    {
        return $this->hasMany(SiwesPlacement::class);
    }

    /**
     * Final-year (or beyond) on the programme's normal duration, e.g. 400L of
     * an 8-semester B.Sc, ND II of a 4-semester ND.
     */
    public function isInFinalYear(): bool
    {
        return $this->level->rank >= intdiv($this->programme->duration_semesters, 2);
    }

    /**
     * Courses failed in senate-approved results and not yet passed — the
     * student's outstanding carryovers.
     *
     * @return Collection<int, Course>
     */
    public function outstandingCarryovers(): Collection
    {
        $attempts = Result::query()
            ->whereHas('registeredCourse.courseRegistration', fn ($q) => $q->where('student_id', $this->id))
            ->whereNotNull('is_passed')
            ->with('registeredCourse.course')
            ->get()
            ->groupBy(fn (Result $r) => $r->registeredCourse->course_id);

        return $attempts
            ->filter(fn ($results) => ! $results->contains(fn (Result $r) => $r->is_passed))
            ->map(fn ($results) => $results->first()->registeredCourse->course)
            ->values();
    }
}
