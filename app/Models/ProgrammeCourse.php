<?php

namespace App\Models;

use App\Enums\CourseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeCourse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => CourseType::class,
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
