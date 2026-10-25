<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Append-only: returning false cancels the operation.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an event that has no single model save behind it, e.g. a bulk
     * status change across many rows.
     */
    public static function record(
        string $event,
        Model|string $subject,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $subject instanceof Model ? $subject->getMorphClass() : $subject,
            'auditable_id' => $subject instanceof Model ? $subject->getKey() : null,
            'old_values' => $old,
            'new_values' => $new,
            'description' => $description,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
