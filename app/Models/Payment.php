<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Payments are made either by a student (against an invoice) or by an
     * applicant (application fee). These helpers give gateways one payer API.
     */
    public function payerUser(): ?User
    {
        return $this->student?->user ?? $this->applicant?->user;
    }

    public function description(): string
    {
        return $this->invoice !== null
            ? 'School fees payment '.$this->invoice->number
            : 'Application fee '.($this->applicant?->application_no ?? $this->reference);
    }

    public function verificationUrl(): string
    {
        return $this->applicant_id !== null
            ? route('applicant.fees.verify', $this)
            : route('student.fees.verify', $this);
    }
}
