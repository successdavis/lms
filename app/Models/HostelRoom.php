<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostelRoom extends Model
{
    protected $guarded = [];

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class);
    }

    /**
     * Bed spaces taken this session: confirmed allocations plus unexpired
     * reservations. Lapsed reservations free their space automatically.
     */
    public function occupiedSpaces(int $sessionId): int
    {
        return $this->allocations()
            ->where('academic_session_id', $sessionId)
            ->where(fn (Builder $q) => HostelAllocation::scopeHoldingSpace($q))
            ->count();
    }

    public function hasSpace(int $sessionId): bool
    {
        return $this->occupiedSpaces($sessionId) < $this->capacity;
    }
}
