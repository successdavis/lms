<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Notifications\PaymentReceived;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable;

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

    protected static function booted(): void
    {
        // Every settlement path (gateways, webhooks, bursary) ends in a save,
        // so the receipt notice lives here rather than at each call site.
        static::saved(function (Payment $payment) {
            if ($payment->status === PaymentStatus::Successful
                && ($payment->wasRecentlyCreated || $payment->wasChanged('status'))) {
                $payment->payerUser()?->notify(PaymentReceived::for($payment));
            }
        });
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
