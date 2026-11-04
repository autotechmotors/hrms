<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

trait AuditsChanges
{
    public static function bootAuditsChanges(): void
    {
        static::created(function ($model): void {
            AuditLogger::record('created', $model, [], self::withoutSecrets($model->getAttributes()));
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            $original = [];

            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }

            AuditLogger::record('updated', $model, self::withoutSecrets($original), self::withoutSecrets($changes));
        });

        static::deleted(function ($model): void {
            AuditLogger::record('deleted', $model, self::withoutSecrets($model->getAttributes()), []);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function withoutSecrets(array $attributes): array
    {
        unset($attributes['password']);

        return $attributes;
    }
}
