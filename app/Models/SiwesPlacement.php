<?php

namespace App\Models;

use App\Enums\SiwesStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiwesPlacement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SiwesStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'logbook_score' => 'decimal:2',
            'report_score' => 'decimal:2',
            'visit_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'is_passed' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
