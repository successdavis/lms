<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RegisteredCourse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_carryover' => 'boolean',
        ];
    }

    public function courseRegistration(): BelongsTo
    {
        return $this->belongsTo(CourseRegistration::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }
}
