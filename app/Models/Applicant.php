<?php

namespace App\Models;

use App\Enums\ApplicantStatus;
use App\Enums\CapsStatus;
use App\Enums\EntryMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Applicant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ApplicantStatus::class,
            'caps_status' => CapsStatus::class,
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

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function hasPaidApplicationFee(): bool
    {
        $fee = (float) $this->cycle->application_fee;

        if ($fee <= 0) {
            return true;
        }

        return (float) $this->payments()
            ->where('status', PaymentStatus::Successful)
            ->sum('amount') >= $fee;
    }
}
