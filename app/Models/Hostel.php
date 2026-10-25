<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Hostel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HostelRoom::class);
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(HostelAllocation::class, HostelRoom::class);
    }

    public function acceptsGender(?string $gender): bool
    {
        return $this->gender === 'mixed' || $this->gender === $gender;
    }
}
