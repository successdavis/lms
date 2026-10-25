<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'late_registration_closes_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public static function current(): ?self
    {
        return static::query()->where('is_current', true)->first();
    }

    public function isRegistrationOpen(): bool
    {
        $now = now();
        $closes = $this->late_registration_closes_at ?? $this->registration_closes_at;

        return $this->registration_opens_at !== null
            && $now->gte($this->registration_opens_at)
            && ($closes === null || $now->lte($closes));
    }
}
