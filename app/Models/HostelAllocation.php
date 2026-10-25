<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelAllocation extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AllocationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'hostel_room_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Confirmed, or reserved and not yet lapsed.
     */
    public static function scopeHoldingSpace(Builder $query): Builder
    {
        return $query->where('status', AllocationStatus::Confirmed)
            ->orWhere(fn (Builder $q) => $q
                ->where('status', AllocationStatus::Reserved)
                ->where('expires_at', '>', now()));
    }

    public function isLapsed(): bool
    {
        return $this->status === AllocationStatus::Reserved
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}
