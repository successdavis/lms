<?php

namespace App\Models;

use App\Enums\AcademicStanding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_quality_points' => 'decimal:2',
            'gpa' => 'decimal:2',
            'cumulative_quality_points' => 'decimal:2',
            'cgpa' => 'decimal:2',
            'standing' => AcademicStanding::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
