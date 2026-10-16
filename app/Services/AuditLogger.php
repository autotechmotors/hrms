<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static bool $enabled = true;

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(
        string $event,
        ?Model $model = null,
        array $old = [],
        array $new = [],
        ?int $companyId = null,
    ): void {
        if (! self::$enabled) {
            return;
        }

        $old = self::clean($old);
        $new = self::clean($new);

        if ($event === 'updated' && $old === [] && $new === []) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'company_id' => $companyId ?? self::companyId($model),
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'event' => $event,
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function clean(array $values): array
    {
        $sensitive = [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'account_number',
        ];

        foreach ($sensitive as $key) {
            unset($values[$key]);
        }

        unset($values['updated_at'], $values['created_at']);

        return $values;
    }

    private static function companyId(?Model $model): ?int
    {
        if ($model === null) {
            return null;
        }

        if (isset($model->company_id)) {
            return (int) $model->company_id;
        }

        if (isset($model->employee_id)) {
            return Employee::query()->whereKey($model->employee_id)->value('company_id');
        }

        return null;
    }
}
