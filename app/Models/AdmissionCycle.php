<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionCycle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'default_cutoff' => 'decimal:2',
            'application_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(AdmissionList::class)->orderBy('sort');
    }

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }

    public function isOpen(): bool
    {
        $now = now();

        return $this->is_active
            && ($this->opens_at === null || $now->gte($this->opens_at))
            && ($this->closes_at === null || $now->lte($this->closes_at));
    }
}
