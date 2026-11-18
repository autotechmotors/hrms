<?php

namespace App\Services\Operations;

use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\EmployeeSalary;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PayDayMath
{
    public function __construct(private readonly WorkHourResolver $hours) {}

    /**
     * Day pay and cutoff for one range. A full calendar month with no cutoff pays the monthly salary exactly.
     *
     * @return array{
     *     earned: float,
     *     cutoff: float,
     *     monthly: float,
     *     day_pay: float,
     *     hours_per_day: float,
     *     cuts: list<array{date: string, window: string, in: ?string, out: ?string, minutes: int, rupees: float, reason: string}>,
     *     days: array<string, array<string, mixed>>,
     *     attended_minutes: int,
     *     late_minutes: int,
     *     early_minutes: int,
     *     break_minutes: int,
     *     absence_days: float,
     *     paid_leave_days: float,
     *     unpaid_leave_days: float,
     *     paid_fraction: float,
     *     lop_fraction: float
     * }
     */
    public function range(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $employee->loadMissing(['workHour', 'department.workHour']);

        $attendance = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->get()
            ->keyBy(fn (AttendanceDay $day) => $day->work_date->toDateString());

        $leaves = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->with('leaveType')
            ->get();

        $public = Holiday::query()
            ->where('company_id', $employee->company_id)
            ->where('kind', 'public')
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $own = EmployeeHoliday::query()
            ->where('employee_id', $employee->id)
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $rosters = ShiftRoster::query()
            ->where('employee_id', $employee->id)
            ->where('is_off', false)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->with('shift')
            ->get()
            ->keyBy(fn (ShiftRoster $roster) => $roster->work_date->toDateString());

        $grace = (int) AttendanceRule::query()->firstOrCreate(
            ['company_id' => $employee->company_id],
            ['grace_minutes' => 0, 'mark_absent_without_punch' => true],
        )->grace_minutes;

        $salaries = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->get();

        $days = [];
        $cuts = [];
        $sumNet = 0.0;
        $sumCutoff = 0.0;
        $attended = 0;
        $late = 0;
        $early = 0;
        $break = 0;
        $absence = 0.0;
        $paidLeave = 0.0;
        $unpaidLeave = 0.0;
        $paidFraction = 0.0;
        $lopFraction = 0.0;
        $counted = 0;
        $monthlyUsed = null;
        $sameSalary = true;

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $monthly = $this->monthlyOn($salaries, $cursor);
            if ($monthlyUsed === null) {
                $monthlyUsed = $monthly;
            } elseif (abs($monthlyUsed - $monthly) > 0.001) {
                $sameSalary = false;
            }

            $beforeJoin = $employee->joining_date && $cursor->lt($employee->joining_date->copy()->startOfDay());
            $leave = $this->leaveOn($leaves, $cursor);
            $kind = 'working';
            if ($beforeJoin) {
                $kind = 'before_join';
            } elseif (in_array($key, $public, true) || in_array($key, $own, true)) {
                $kind = 'holiday';
            } elseif ($leave) {
                $kind = $leave->leaveType?->is_paid ? 'paid_leave' : 'unpaid_leave';
            }

            $cell = $this->day($employee, $cursor, $monthly, $grace, $attendance->get($key), $kind, $rosters->get($key)?->shift);
            $days[$key] = $cell['grid'];
            foreach ($cell['cuts'] as $cut) {
                $cuts[] = $cut;
            }
            $sumNet += $cell['net'];
            $sumCutoff += $cell['cutoff'];
            $attended += $cell['grid']['attended'];
            $late += $cell['grid']['late'];
            $early += $cell['grid']['early'];
            $break += $cell['grid']['break'];
            $absence += $cell['grid']['absence'];
            $paidLeave += $cell['grid']['paid_leave'];
            $unpaidLeave += $cell['grid']['unpaid_leave'];
            if ($kind !== 'before_join' && $cell['day_pay'] > 0) {
                $counted++;
                $paidFraction += $cell['net'] / $cell['day_pay'];
                $lopFraction += $cell['cutoff'] / $cell['day_pay'];
            }
            $cursor->addDay();
        }

        $monthDays = $start->daysInMonth;
        $fullMonth = $start->day === 1 && $end->isSameDay($start->copy()->endOfMonth());
        $monthly = $this->monthlyOn($salaries, $end);
        if ($fullMonth && $sameSalary && $counted === $monthDays && $sumCutoff < 0.0000001) {
            $earned = round($monthly, 2);
        } else {
            $earned = round($sumNet, 2);
        }

        $dayPay = $monthDays > 0 ? $monthly / $monthDays : 0.0;

        return [
            'earned' => $earned,
            'cutoff' => round($sumCutoff, 2),
            'monthly' => $monthly,
            'day_pay' => round($dayPay, 4),
            'hours_per_day' => (float) ($this->hours->forEmployee($employee) ?? 0),
            'cuts' => $cuts,
            'days' => $days,
            'attended_minutes' => $attended,
            'late_minutes' => $late,
            'early_minutes' => $early,
            'break_minutes' => $break,
            'absence_days' => $absence,
            'paid_leave_days' => $paidLeave,
            'unpaid_leave_days' => $unpaidLeave,
            'paid_fraction' => round($paidFraction, 2),
            'lop_fraction' => round($lopFraction, 2),
        ];
    }

    /**
     * @return array{day_pay: float, net: float, cutoff: float, cuts: list<array{date: string, window: string, in: ?string, out: ?string, minutes: int, rupees: float, reason: string}>, grid: array<string, mixed>}
     */
    public function day(Employee $employee, CarbonInterface $date, float $monthly, int $grace, ?AttendanceDay $attendance, string $kind, ?Shift $shift = null): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $days = $day->daysInMonth;
        $dayPay = $days > 0 ? $monthly / $days : 0.0;
        $window = $this->hours->windowFor($employee, $day, $shift);
        $in = $this->hours->clock($attendance?->punch_in, '');
        $out = $this->hours->clock($attendance?->punch_out, '');
        $in = $in === '00:00' && ($attendance?->punch_in === null || $attendance?->punch_in === '') ? null : ($attendance?->punch_in ? $in : null);
        $out = $out === '00:00' && ($attendance?->punch_out === null || $attendance?->punch_out === '') ? null : ($attendance?->punch_out ? $out : null);

        $grid = [
            'date' => $day->toDateString(),
            'in' => $in,
            'out' => $out,
            'late' => 0,
            'ot' => 0,
            'early' => 0,
            'break' => (int) ($attendance->minutes_away ?? 0),
            'attended' => (int) ($attendance->minutes_worked ?? 0),
            'paid' => 0,
            'pay' => 0.0,
            'status' => '—',
            'absence' => 0.0,
            'paid_leave' => 0.0,
            'unpaid_leave' => 0.0,
            'window' => $window['label'],
        ];

        if ($kind === 'before_join' || $monthly <= 0) {
            return ['day_pay' => $dayPay, 'net' => 0.0, 'cutoff' => 0.0, 'cuts' => [], 'grid' => $grid];
        }

        if ($kind === 'holiday') {
            $grid['status'] = 'H';
            $grid['pay'] = $dayPay;

            return [
                'day_pay' => $dayPay,
                'net' => $dayPay,
                'cutoff' => 0.0,
                'cuts' => [[
                    'date' => $day->toDateString(),
                    'window' => $window['label'],
                    'in' => $in,
                    'out' => $out,
                    'minutes' => 0,
                    'rupees' => 0.0,
                    'reason' => 'paid holiday',
                ]],
                'grid' => $grid,
            ];
        }

        if ($this->sundayOff($employee, $day)) {
            $grid['status'] = 'N';
            $grid['window'] = 'Sunday off';
            $grid['pay'] = $dayPay;

            return ['day_pay' => $dayPay, 'net' => $dayPay, 'cutoff' => 0.0, 'cuts' => [], 'grid' => $grid];
        }

        if ($kind === 'paid_leave') {
            $grid['status'] = 'L';
            $grid['paid_leave'] = 1;
            $grid['pay'] = $dayPay;

            return ['day_pay' => $dayPay, 'net' => $dayPay, 'cutoff' => 0.0, 'cuts' => [], 'grid' => $grid];
        }

        if ($kind === 'unpaid_leave') {
            $grid['status'] = 'UL';
            $grid['unpaid_leave'] = 1;
            $grid['absence'] = 1;

            return [
                'day_pay' => $dayPay,
                'net' => 0.0,
                'cutoff' => $dayPay,
                'cuts' => [$this->cut($day, $window['label'], $in, $out, 0, $dayPay, 'absent')],
                'grid' => $grid,
            ];
        }

        if ($in === null || $out === null) {
            $grid['status'] = 'A';
            $grid['absence'] = 1;

            return [
                'day_pay' => $dayPay,
                'net' => 0.0,
                'cutoff' => $dayPay,
                'cuts' => [$this->cut($day, $window['label'], $in, $out, 0, $dayPay, 'absent')],
                'grid' => $grid,
            ];
        }

        $inMinutes = (int) $this->hours->minutes($in);
        $outMinutes = (int) $this->hours->minutes($out);
        $start = (int) $this->hours->minutes($window['start']);
        $finish = (int) $this->hours->minutes($window['end']);
        $overnight = (bool) ($window['overnight'] ?? false);
        $inAt = $this->place($inMinutes, $start, $finish, $overnight);
        $outAt = $this->place($outMinutes, $start, $finish, $overnight);
        $endAt = $overnight ? $finish + 1440 : $finish;
        $late = max(0, $inAt - $start - max(0, $grace));
        $early = (! $overnight && $outMinutes < $inMinutes) ? 0 : max(0, $endAt - $outAt);
        $personal = max(0, (int) ($attendance->minutes_personal ?? 0));
        $early += $personal;

        $grid['late'] = $late;
        $grid['early'] = $early;
        $grid['status'] = 'P';

        $standard = max(1, (int) $window['standard_minutes']);
        $rate = $dayPay / $standard;
        $cuts = [];
        $raw = 0.0;
        if ($late > 0) {
            $amount = $late * $rate;
            $raw += $amount;
            $cuts[] = $this->cut($day, $window['label'], $in, $out, $late, $amount, 'late');
        }
        if ($early > 0) {
            $amount = $early * $rate;
            $raw += $amount;
            $cuts[] = $this->cut($day, $window['label'], $in, $out, $early, $amount, 'left early');
        }

        $cutoff = min($dayPay, $raw);
        if ($raw > $dayPay && $cuts !== []) {
            $this->cap($cuts, $dayPay);
            $cutoff = $dayPay;
        }

        $grid['pay'] = $dayPay - $cutoff;

        return [
            'day_pay' => $dayPay,
            'net' => $dayPay - $cutoff,
            'cutoff' => $cutoff,
            'cuts' => $cuts,
            'grid' => $grid,
        ];
    }

    private function place(int $clock, int $start, int $finish, bool $overnight): int
    {
        if (! $overnight) {
            return $clock;
        }

        if ($clock >= $start || $clock <= $finish) {
            return $clock >= $start ? $clock : $clock + 1440;
        }

        $afterMorning = $clock - $finish;
        $beforeEvening = $start - $clock;

        return $afterMorning < $beforeEvening ? $clock + 1440 : $clock;
    }

    private function sundayOff(Employee $employee, CarbonInterface $day): bool
    {
        if ((int) Carbon::parse($day)->dayOfWeek !== Carbon::SUNDAY) {
            return false;
        }

        return ! $this->hours->worksSunday($employee);
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     */
    private function monthlyOn(Collection $salaries, CarbonInterface $on): float
    {
        $row = $salaries->first(fn (EmployeeSalary $salary) => $salary->effective_from->copy()->startOfDay()->lte(Carbon::parse($on)->startOfDay()));

        return $row ? (float) $row->gross_monthly : 0.0;
    }

    /**
     * @param  Collection<int, LeaveRequest>  $leaves
     */
    private function leaveOn(Collection $leaves, CarbonInterface $day): ?LeaveRequest
    {
        $on = Carbon::parse($day)->startOfDay();

        return $leaves->first(function (LeaveRequest $leave) use ($on) {
            return $on->betweenIncluded($leave->starts_on->copy()->startOfDay(), $leave->ends_on->copy()->startOfDay());
        });
    }

    /**
     * @return array{date: string, window: string, in: ?string, out: ?string, minutes: int, rupees: float, reason: string}
     */
    private function cut(CarbonInterface $day, string $window, ?string $in, ?string $out, int $minutes, float $rupees, string $reason): array
    {
        return [
            'date' => $day->toDateString(),
            'window' => $window,
            'in' => $in,
            'out' => $out,
            'minutes' => $minutes,
            'rupees' => round($rupees, 2),
            'reason' => $reason,
        ];
    }

    /**
     * @param  list<array{rupees: float}>  $cuts
     */
    private function cap(array &$cuts, float $dayPay): void
    {
        $left = round($dayPay, 2);
        $last = count($cuts) - 1;
        foreach ($cuts as $index => &$cut) {
            if ($index === $last) {
                $cut['rupees'] = max(0, $left);
                break;
            }
            $cut['rupees'] = min($cut['rupees'], $left);
            $left = round($left - $cut['rupees'], 2);
        }
    }
}
