<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantDocument extends Model
{
    public const TYPES = [
        'olevel_result' => "O'Level Result (WAEC/NECO/NABTEB)",
        'jamb_result' => 'JAMB UTME Result Slip',
        'birth_certificate' => 'Birth Certificate / Declaration of Age',
        'lga_identification' => 'LGA / State of Origin Certificate',
        'other' => 'Other Supporting Document',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }
}
