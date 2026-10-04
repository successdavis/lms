<?php

namespace App\Models;

use App\Enums\ApplicantStatus;
use App\Enums\EntryMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Applicant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ApplicantStatus::class,
            'entry_mode' => EntryMode::class,
            'date_of_birth' => 'date',
            'post_utme_score' => 'decimal:2',
            'aggregate_score' => 'decimal:2',
            'o_level_results' => 'array',
            'submitted_at' => 'datetime',
            'admitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'matriculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function admittedProgramme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'admitted_programme_id');
    }

    public function admissionList(): BelongsTo
    {
        return $this->belongsTo(AdmissionList::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
