<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeScale extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_point' => 'decimal:2',
            'pass_mark' => 'decimal:2',
            'probation_cgpa' => 'decimal:2',
            'withdrawal_cgpa' => 'decimal:2',
        ];
    }

    public function bands(): HasMany
    {
        return $this->hasMany(GradeScaleBand::class)->orderBy('sort');
    }

    public function classificationBands(): HasMany
    {
        return $this->hasMany(ClassificationBand::class)->orderBy('sort');
    }
}
