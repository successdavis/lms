<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $guarded = [];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'hod_staff_id');
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
