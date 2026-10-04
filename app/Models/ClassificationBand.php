<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationBand extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_cgpa' => 'decimal:2',
            'max_cgpa' => 'decimal:2',
        ];
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }
}
