<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

trait AuditsChanges
{
    public static function bootAuditsChanges(): void
    {
        static::created(function ($model): void {
            AuditLogger::record('created', $model, [], $model->getAttributes());
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            $original = [];

            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }

            AuditLogger::record('updated', $model, $original, $changes);
        });

        static::deleted(function ($model): void {
            AuditLogger::record('deleted', $model, $model->getAttributes(), []);
        });
    }
}
