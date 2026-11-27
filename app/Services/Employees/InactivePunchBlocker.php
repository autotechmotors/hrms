<?php

namespace App\Services\Employees;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Services\Devices\FaceAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InactivePunchBlocker
{
    /**
     * @return list<string>
     */
    public const PROTECTED_ROLES = ['super-admin', 'hr-admin'];

    /** @var array<int, array{at: Carbon, from_punch: bool}>|null */
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
                if ($employee->in_suspended) {
                    return;
                }
                $employee->update(['in_suspended' => true]);
                app(FaceAccess::class)->syncIn($employee);
                $blocked++;
            });
        });

        return $blocked;
    }

    /**
     * @return Collection<int, Employee>
     */
    public function candidates(CompanySetting $setting): Collection
    {
        $companyDays = max(1, (int) $setting->block_inactive_days);

        $employees = Employee::query()
            ->with(['role', 'department', 'user.roles', 'employmentStatus'])
            ->where('company_id', $setting->company_id)
            ->where('in_suspended', false)
            ->whereHas('employmentStatus', fn ($query) => $query->where('is_exit', false))
            ->orderBy('first_name')
            ->get();

        $this->rememberActivity($employees);

        return $employees
            ->filter(function (Employee $employee) use ($companyDays): bool {
                $limit = $employee->block_inactive_days !== null
                    ? max(1, (int) $employee->block_inactive_days)
                    : $companyDays;

                return ! $this->isProtected($employee)
                    && $this->idleDays($employee) >= $limit;
            })
            ->values();
    }

    public function idleDays(Employee $employee): int
    {
        $mark = $this->mark($employee);
        $today = now()->startOfDay();
        $anchor = $mark['at']->copy()->startOfDay();
        if ($anchor->gt($today)) {
            return 0;
        }

        $span = (int) round($anchor->diffInDays($today, true));
        if ($mark['from_punch']) {
            return max(0, $span - 1);
        }

        return $span;
    }

    public function lastPunchAt(Employee $employee): Carbon
    {
        return $this->mark($employee)['at']->copy();
    }

    /**
     * @return array{at: Carbon, from_punch: bool}
     */
    private function mark(Employee $employee): array
    {
        if ($this->activity !== null && array_key_exists($employee->id, $this->activity)) {
            return [
                'at' => $this->activity[$employee->id]['at']->copy(),
                'from_punch' => $this->activity[$employee->id]['from_punch'],
            ];
        }

        $punch = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->max('punched_at');

        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->where(fn ($query) => $query->whereNotNull('punch_in')->orWhereNotNull('punch_out'))
            ->max('work_date');

        return $this->resolveMark($punch, $day, $employee);
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
            $this->activity[$employee->id] = $this->resolveMark(
                $punches[$employee->id] ?? null,
                $days[$employee->id] ?? null,
                $employee,
            );
        }
    }

    /**
     * A punch day is not an empty day. With no punch, the joining date is the first empty day.
     * Today is never counted, because it is not a full day yet.
     *
     * @return array{at: Carbon, from_punch: bool}
     */
    private function resolveMark(mixed $punch, mixed $day, Employee $employee): array
    {
        $dates = array_filter([
            $punch ? Carbon::parse($punch) : null,
            $day ? Carbon::parse($day)->endOfDay() : null,
        ]);

        if ($dates !== []) {
            return [
                'at' => collect($dates)->reduce(
                    fn (?Carbon $best, Carbon $date) => $best && $best->gt($date) ? $best : $date
                ),
                'from_punch' => true,
            ];
        }

        return [
            'at' => Carbon::parse($employee->joining_date ?? $employee->created_at)->startOfDay(),
            'from_punch' => false,
        ];
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
