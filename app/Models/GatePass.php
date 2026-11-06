<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatePass extends Model
{
    use AuditsChanges;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const WORK_PERSONAL = 'personal';

    public const WORK_OFFICE = 'office';

    protected $fillable = [
        'company_id', 'number', 'employee_id', 'created_by', 'date', 'out_time',
        'duration_minutes', 'is_early_leave', 'work_type', 'reason', 'status',
        'decided_by', 'decided_at', 'decision_note', 'expires_at',
        'out_at', 'in_at', 'machine_status', 'machine_note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
            'out_at' => 'datetime',
            'in_at' => 'datetime',
            'duration_minutes' => 'integer',
            'is_early_leave' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isActive(): bool
    {
        return $this->isApproved() && $this->expires_at?->isFuture();
    }

    public function isUsable(): bool
    {
        return $this->isActive();
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isEarlyLeave(): bool
    {
        return (bool) $this->is_early_leave;
    }

    public function isOfficeWork(): bool
    {
        return $this->work_type === self::WORK_OFFICE;
    }

    public function hasLeft(): bool
    {
        return $this->out_at !== null;
    }

    public function hasReturned(): bool
    {
        return $this->in_at !== null;
    }

    public function isOpenOuting(): bool
    {
        return $this->hasLeft() && ! $this->hasReturned();
    }

    public function machineReady(): bool
    {
        return in_array($this->machine_status, ['ready', 'queued'], true) && $this->isUsable();
    }

    public function outingMinutes(?Carbon $until = null): int
    {
        if (! $this->out_at || ! $this->in_at) {
            return 0;
        }

        $end = $this->in_at;
        if ($until && $until->lessThan($end)) {
            $end = $until;
        }

        return max(0, (int) round($this->out_at->diffInMinutes($end, true)));
    }

    public function officeCreditMinutes(): int
    {
        if (! $this->isOfficeWork()) {
            return 0;
        }

        $away = $this->outingMinutes();

        return $this->isEarlyLeave() ? $away : min($away, max(0, (int) $this->duration_minutes));
    }

    public function personalCutMinutes(): int
    {
        return $this->isOfficeWork() ? 0 : $this->outingMinutes();
    }

    public function getMachineLabelAttribute(): string
    {
        if ($this->isRejected()) {
            return 'OUT machine blocked';
        }
        if ($this->isPending()) {
            return 'OUT machine waits for parent approval';
        }
        if ($this->isOpenOuting()) {
            return $this->in_at ? 'Returned' : 'Out — waiting for return punch';
        }
        if ($this->machineReady()) {
            return 'OUT machine available until '.$this->expires_at?->format('h:i A');
        }
        if ($this->status === self::STATUS_EXPIRED) {
            return $this->isOpenOuting()
                ? 'Expired — clock in starts when they return'
                : 'OUT machine access removed';
        }

        return match ($this->machine_status) {
            'queued', 'ready' => 'OUT machine available',
            'revoked' => 'OUT machine access removed',
            default => 'OUT machine not granted',
        };
    }

    public function getWorkTypeLabelAttribute(): string
    {
        return $this->isOfficeWork() ? 'Office Work' : 'Personal Work';
    }

    public function scopeForEmployees(Builder $query, $employeeIds): Builder
    {
        return $query->whereIn('employee_id', $employeeIds);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)->where('expires_at', '>', now());
    }

    public function scopeToday(Builder $query): Builder
    {
        $day = now()->toDateString();

        return $query->where(function (Builder $inner) use ($day) {
            $inner->whereDate('date', $day)
                ->orWhereDate('created_at', $day)
                ->orWhereDate('decided_at', $day);
        });
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like) {
            $inner->where('number', 'like', $like)
                ->orWhere('reason', 'like', $like)
                ->orWhere('work_type', 'like', $like)
                ->orWhereHas('employee', fn (Builder $employee) => $employee
                    ->where('employee_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like));
        });
    }

    public function getDurationLabelAttribute(): string
    {
        if ($this->isEarlyLeave()) {
            return 'Early Leave';
        }

        $minutes = (int) $this->duration_minutes;
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => $rest.' Minutes',
            $rest === 0 => $hours === 1 ? '1 Hour' : $hours.' Hours',
            default => $hours.($hours === 1 ? ' hour ' : ' hours ').$rest.' Min',
        };
    }

    public function getFormattedOutTimeAttribute(): string
    {
        $time = strlen((string) $this->out_time) === 5 ? $this->out_time.':00' : $this->out_time;

        return Carbon::parse((string) $time)->format('h:i A');
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('d M Y');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->isActive() ? 'ACTIVE' : strtoupper((string) $this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->isActive() ? 'ok' : match ($this->status) {
            self::STATUS_PENDING => 'wn',
            self::STATUS_APPROVED => 'ok',
            self::STATUS_REJECTED => 'er',
            default => '',
        };
    }

    public static function generateNumber(): string
    {
        $prefix = 'GP-'.now()->format('Ymd').'-';
        $latest = static::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $sequence = $latest ? ((int) substr((string) $latest, -6)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function expiresAtFromOut(): Carbon
    {
        return self::calculateExpiresAt($this->date->format('Y-m-d'), (string) $this->out_time, (int) $this->duration_minutes);
    }

    public static function calculateExpiresAt(string $date, string $outTime, int $durationMinutes): Carbon
    {
        $time = strlen($outTime) === 5 ? $outTime.':00' : $outTime;

        return Carbon::parse($date.' '.$time)->addMinutes($durationMinutes);
    }

    public static function expireStale(): int
    {
        return app(\App\Services\Operations\GatePassService::class)->expireDue();
    }
}
