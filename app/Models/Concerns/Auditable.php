<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an audit_logs row whenever the model is created, updated or deleted
 * through Eloquent. Query-builder bulk updates bypass model events; record
 * those explicitly with AuditLog::record().
 */
trait Auditable
{
    /** @var list<string> */
    protected static array $auditExcluded = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLog::record('created', $model, null, static::auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = static::auditValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getRawOriginal(), $changes);
            AuditLog::record('updated', $model, $old, $changes);
        });

        static::deleted(function (Model $model) {
            AuditLog::record('deleted', $model, static::auditValues($model->getRawOriginal()), null);
        });
    }

    private static function auditValues(array $values): array
    {
        return array_diff_key($values, array_flip(static::$auditExcluded));
    }
}
