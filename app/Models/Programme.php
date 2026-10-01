<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    public function entryLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'entry_level_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'programme_courses')
            ->withPivot(['level_id', 'semester_number', 'type'])
            ->withTimestamps();
    }

    public function curriculum(): HasMany
    {
        return $this->hasMany(ProgrammeCourse::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
