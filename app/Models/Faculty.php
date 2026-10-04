<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    protected $guarded = [];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function dean(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dean_staff_id');
    }
}
