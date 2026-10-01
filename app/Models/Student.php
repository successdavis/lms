<?php

namespace App\Models;

use App\Enums\EntryMode;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
