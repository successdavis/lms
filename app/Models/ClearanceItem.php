<?php

namespace App\Models;

use App\Enums\ClearanceItemStatus;
use App\Enums\ClearanceUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit' => ClearanceUnit::class,
            'status' => ClearanceItemStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function clearanceRequest(): BelongsTo
    {
        return $this->belongsTo(ClearanceRequest::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
