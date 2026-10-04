<?php

namespace App\Models;

use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ca_score' => 'decimal:2',
            'exam_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'quality_points' => 'decimal:2',
            'is_passed' => 'boolean',
            'status' => ResultStatus::class,
        ];
    }

    public function registeredCourse(): BelongsTo
    {
        return $this->belongsTo(RegisteredCourse::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'entered_by_id');
    }
}
