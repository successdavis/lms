<?php

namespace App\Models;

use App\Enums\StaffType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use Auditable;

    protected $table = 'staff';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => StaffType::class,
            'employed_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function courseAllocations(): HasMany
    {
        return $this->hasMany(CourseAllocation::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
