<?php

namespace App\Services\Operations;

use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\EmployeeSalary;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ShiftRoster;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonthReport
{
    public function __construct(private readonly PayDayMath $pay) {}

    /**
     * @return array<string, mixed>
     */
    public function make(User $user, int $companyId, Carbon $month, ?int $departmentId = null): array
    {
        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $daysInMonth = $start->daysInMonth;

        $employees = Employee::query()
            ->visibleTo($user)
            ->where('company_id', $companyId)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->where(function ($query) {
                $query->whereDoesntHave('employmentStatus')
                    ->orWhereHas('employmentStatus', fn ($status) => $status->where('is_exit', false));
            })
            ->with(['department', 'workHour'])
            ->orderBy('employee_code')
            ->get();

        $ids = $employees->pluck('id');
        $grace = (int) AttendanceRule::query()->firstOrCreate(
            ['company_id' => $companyId],
            ['grace_minutes' => 0, 'mark_absent_without_punch' => true],
        )->grace_minutes;

        $attendance = AttendanceDay::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $rows) => $rows->keyBy(fn (AttendanceDay $day) => $day->work_date->toDateString()));

        $public = Holiday::query()
            ->where('company_id', $companyId)
            ->where('kind', 'public')
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $cutoffHolidays = Holiday::query()
            ->where('company_id', $companyId)
            ->where('kind', 'cutoff')
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $personal = EmployeeHoliday::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->get()
            ->mapWithKeys(fn (EmployeeHoliday $row) => [$row->employee_id.'|'.$row->holiday_date->toDateString() => true]);

        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->with('leaveType')
            ->get()
            ->groupBy('employee_id');

        $rosters = ShiftRoster::query()
            ->whereIn('employee_id', $ids)
            ->where('is_off', false)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->with('shift')
            ->get()
            ->keyBy(fn (ShiftRoster $roster) => $roster->employee_id.'|'.$roster->work_date->toDateString());

        $salaries = EmployeeSalary::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('effective_from', '<=', $end)
            ->orderByDesc('effective_from')
            ->get()
            ->unique('employee_id')
            ->keyBy('employee_id');

        $totals = $this->blank();
        $departments = [];
        $people = [];
        $leaveTypes = [];

        foreach ($employees as $employee) {
            $monthly = (float) ($salaries->get($employee->id)?->gross_monthly ?? 0);
            $row = $this->blank();
            $row['shift'] = $employee->workHour?->shift_kind === 'night' ? 'Night' : 'Day';
            $personLeaves = $leaves->get($employee->id, collect());
            $personDays = $attendance->get($employee->id, collect());

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                if ($employee->joining_date && $cursor->lt($employee->joining_date->copy()->startOfDay())) {
                    $cursor->addDay();

                    continue;
                }

                $leave = $personLeaves->first(function (LeaveRequest $leave) use ($cursor) {
                    return $cursor->betweenIncluded($leave->starts_on->copy()->startOfDay(), $leave->ends_on->copy()->startOfDay());
                });
                $kind = 'working';
                if (in_array($key, $public, true) || $personal->has($employee->id.'|'.$key)) {
                    $kind = 'holiday';
                } elseif (in_array($key, $cutoffHolidays, true)) {
                    $kind = 'holiday_cutoff';
                } elseif ($leave) {
                    $kind = $leave->leaveType?->is_paid ? 'paid_leave' : 'unpaid_leave';
                    $type = $leave->leaveType?->name ?? 'Leave';
                    $leaveTypes[$type] = ($leaveTypes[$type] ?? 0) + 1;
                }

                $cell = $this->pay->day(
                    $employee,
                    $cursor,
                    $monthly > 0 ? $monthly : 1,
                    $grace,
                    $personDays->get($key),
                    $kind,
                    $rosters->get($employee->id.'|'.$key)?->shift,
                );
                if ($monthly <= 0) {
                    $cell['cutoff'] = 0.0;
                }

                if ($kind === 'holiday') {
                    $row['holidays']++;
                } elseif ($kind === 'paid_leave') {
                    $row['paid_leave']++;
                } elseif ($kind === 'unpaid_leave') {
                    $row['unpaid_leave']++;
                } elseif (($cell['grid']['status'] ?? '') === 'N') {
                    $row['night_sundays']++;
                } elseif (($cell['grid']['status'] ?? '') === 'P') {
                    $row['present']++;
                } elseif (($cell['grid']['absence'] ?? 0) > 0) {
                    $row['absent']++;
                }

                $row['late'] += (int) $cell['grid']['late'];
                $row['early'] += (int) $cell['grid']['early'];
                $row['cutoff'] += (float) $cell['cutoff'];
                $cursor->addDay();
            }

            $row['cutoff'] = round($row['cutoff'], 2);
            foreach (['present', 'absent', 'holidays', 'paid_leave', 'unpaid_leave', 'night_sundays', 'late', 'early', 'cutoff'] as $field) {
                $totals[$field] += $row[$field];
            }
            if ($row['shift'] === 'Night') {
                $totals['night']++;
            } else {
                $totals['day']++;
            }

            $departmentName = $employee->department?->name ?? 'No department';
            $departments[$departmentName] ??= ['name' => $departmentName, 'people' => 0] + $this->blank();
            $departments[$departmentName]['people']++;
            foreach (['present', 'absent', 'holidays', 'paid_leave', 'unpaid_leave', 'late', 'early', 'cutoff'] as $field) {
                $departments[$departmentName][$field] += $row[$field];
            }

            if ($row['cutoff'] > 0 || $row['absent'] > 0 || $row['late'] > 0 || $row['early'] > 0) {
                $people[] = [
                    'name' => $employee->full_name,
                    'code' => $employee->employee_code,
                    'department' => $departmentName,
                    'shift' => $row['shift'],
                    'absent' => $row['absent'],
                    'late' => $row['late'],
                    'early' => $row['early'],
                    'cutoff' => $row['cutoff'],
                ];
            }
        }

        $totals['cutoff'] = round($totals['cutoff'], 2);
        $departments = collect($departments)->sortBy('name')->values()->all();
        usort($people, fn (array $left, array $right) => $right['cutoff'] <=> $left['cutoff']);

        return [
            'people' => $employees->count(),
            'calendar_days' => $daysInMonth,
            'public_holidays' => count($public),
            'present' => $totals['present'],
            'absent' => $totals['absent'],
            'holidays' => $totals['holidays'],
            'paid_leave' => $totals['paid_leave'],
            'night_sundays' => $totals['night_sundays'],
            'unpaid_leave' => $totals['unpaid_leave'],
            'late' => $totals['late'],
            'early' => $totals['early'],
            'cutoff' => $totals['cutoff'],
            'day_people' => $totals['day'],
            'night_people' => $totals['night'],
            'departments' => $departments,
            'attention' => array_slice($people, 0, 20),
            'leave' => collect($leaveTypes)->sortDesc(),
        ];
    }

    /**
     * @return array{present: int, absent: int, holidays: int, paid_leave: int, unpaid_leave: int, late: int, early: int, cutoff: float, day: int, night: int, shift: string}
     */
    private function blank(): array
    {
        return [
            'present' => 0,
            'absent' => 0,
            'holidays' => 0,
            'paid_leave' => 0,
            'night_sundays' => 0,
            'unpaid_leave' => 0,
            'late' => 0,
            'early' => 0,
            'cutoff' => 0.0,
            'day' => 0,
            'night' => 0,
            'shift' => 'Day',
        ];
    }

    /**
     * @return Collection<int, Department>
     */
    public function departments(User $user, int $companyId): Collection
    {
        return Department::query()
            ->visibleTo($user)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
