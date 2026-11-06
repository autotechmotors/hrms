<?php

namespace App\Services\Employees;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InactivePunchBlocker
{
    /**
     * @return list<string>
     */
    public const PROTECTED_ROLES = ['super-admin', 'hr-admin'];

    /** @var array<int, Carbon>|null */
    private ?array $activity = null;

    public function apply(?int $companyId = null): int
    {
        $query = CompanySetting::query()->where('block_inactive_enabled', true);
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $blocked = 0;
        $query->get()->each(function (CompanySetting $setting) use (&$blocked): void {
            $this->candidates($setting)->each(function (Employee $employee) use (&$blocked): void {
                if (! $employee->is_blocked) {
                    $employee->update(['is_blocked' => true]);
                    $blocked++;
                }
            });
        });

        return $blocked;
    }

    /**
     * @return Collection<int, Employee>
     */
    public function candidates(CompanySetting $setting): Collection
    {
        $days = max(1, (int) $setting->block_inactive_days);
        $cutoff = now()->startOfDay()->subDays($days);

        $employees = Employee::query()
            ->with(['role', 'department', 'user.roles', 'employmentStatus'])
            ->where('company_id', $setting->company_id)
            ->where('is_blocked', false)
            ->whereHas('employmentStatus', fn ($query) => $query->where('is_exit', false))
            ->orderBy('first_name')
            ->get();

        $this->rememberActivity($employees);

        return $employees
            ->filter(function (Employee $employee) use ($cutoff): bool {
                return ! $this->isProtected($employee)
                    && $this->lastPunchAt($employee)->lt($cutoff);
            })
            ->values();
    }

    public function idleDays(Employee $employee): int
    {
        return (int) $this->lastPunchAt($employee)->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function lastPunchAt(Employee $employee): Carbon
    {
        if ($this->activity !== null && array_key_exists($employee->id, $this->activity)) {
            return $this->activity[$employee->id]->copy();
        }

        $punch = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->max('punched_at');

        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->where(fn ($query) => $query->whereNotNull('punch_in')->orWhereNotNull('punch_out'))
            ->max('work_date');

        return $this->resolveLastPunch($punch, $day, $employee);
    }

    private function rememberActivity(Collection $employees): void
    {
        $this->activity = [];
        $ids = $employees->modelKeys();
        if ($ids === []) {
            return;
        }

        $punches = AttendancePunch::query()
            ->whereIn('employee_id', $ids)
            ->selectRaw('employee_id, MAX(punched_at) as punched_at')
            ->groupBy('employee_id')
            ->pluck('punched_at', 'employee_id');

        $days = AttendanceDay::query()
            ->whereIn('employee_id', $ids)
            ->where(fn ($query) => $query->whereNotNull('punch_in')->orWhereNotNull('punch_out'))
            ->selectRaw('employee_id, MAX(work_date) as work_date')
            ->groupBy('employee_id')
            ->pluck('work_date', 'employee_id');

        foreach ($employees as $employee) {
            $this->activity[$employee->id] = $this->resolveLastPunch(
                $punches[$employee->id] ?? null,
                $days[$employee->id] ?? null,
                $employee,
            );
        }
    }

    private function resolveLastPunch(mixed $punch, mixed $day, Employee $employee): Carbon
    {
        $dates = array_filter([
            $punch ? Carbon::parse($punch) : null,
            $day ? Carbon::parse($day)->endOfDay() : null,
        ]);

        if ($dates !== []) {
            return collect($dates)->reduce(
                fn (?Carbon $best, Carbon $date) => $best && $best->gt($date) ? $best : $date
            );
        }

        return Carbon::parse($employee->joining_date ?? $employee->created_at)->startOfDay();
    }

    public function isProtected(Employee $employee): bool
    {
        $employee->loadMissing(['role', 'user.roles']);

        $slugs = collect([$employee->role?->slug])
            ->merge($employee->user?->roles->pluck('slug') ?? [])
            ->filter()
            ->unique();

        return $slugs->intersect(self::PROTECTED_ROLES)->isNotEmpty();
    }
}
