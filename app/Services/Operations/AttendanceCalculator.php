<?php

namespace App\Services\Operations;

use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\GatePass;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    public function __construct(
        private readonly WorkCalendarResolver $calendar,
        private readonly WorkHourResolver $hours,
    ) {}

    public function recalculate(Employee $employee, Carbon $date): AttendanceDay
    {
        $rule = AttendanceRule::query()->firstOrCreate(
            ['company_id' => $employee->company_id],
            ['grace_minutes' => 0, 'mark_absent_without_punch' => true],
        );
        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->first() ?? new AttendanceDay([
                'employee_id' => $employee->id,
                'work_date' => $date->toDateString(),
            ]);
        $roster = ShiftRoster::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->first();
        $holiday = Holiday::query()->where('company_id', $employee->company_id)->whereDate('holiday_date', $date)->first();
        $employeeHoliday = EmployeeHoliday::query()->where('employee_id', $employee->id)->whereDate('holiday_date', $date)->exists();
        $leave = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();

        $passes = GatePass::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->whereIn('status', [GatePass::STATUS_APPROVED, GatePass::STATUS_EXPIRED])
            ->get();

        $in = $day->punch_in ? Carbon::parse($date->toDateString().' '.$day->punch_in) : null;
        $out = $day->punch_out ? Carbon::parse($date->toDateString().' '.$day->punch_out) : null;
        $open = $passes->first(fn (GatePass $pass) => $pass->isOpenOuting());
        $officeStillOut = (bool) ($open?->isOfficeWork() && $open->out_at);
        if ($in && ! $out && $open?->out_at && ! $officeStillOut) {
            $out = $open->out_at->copy()->timezone('Asia/Kolkata');
        }
        if ($in && $out && $out->lessThanOrEqualTo($in)) {
            $out = $out->copy()->addDay();
        }
        $leftAt = ($officeStillOut && $open?->out_at)
            ? $open->out_at->copy()->timezone('Asia/Kolkata')
            : null;
        $backAfterOffice = $leftAt && $out && $out->greaterThan($leftAt);
        $coverOpenOffice = $officeStillOut && $in && ! $backAfterOffice;
        $measureOut = $coverOpenOffice ? $this->shiftEnd($employee, $date) : $out;
        $gross = ($in && $measureOut && $measureOut->greaterThan($in)) ? (int) round($in->diffInMinutes($measureOut, true)) : 0;

        $clocks = $this->hours->breakClocks($employee, $date);
        $away = 0;
        $office = 0;
        $personal = 0;
        foreach ($passes as $pass) {
            if (! $pass->out_at || ! $pass->in_at) {
                continue;
            }
            $left = $pass->out_at->copy()->timezone('Asia/Kolkata');
            $back = $pass->in_at->copy()->timezone('Asia/Kolkata');
            if ($back->lessThanOrEqualTo($left)) {
                continue;
            }
            $span = (int) round($left->diffInMinutes($back, true));
            $away += $span;
            if ($pass->isOfficeWork()) {
                $office += $span;

                continue;
            }
            $personal += $this->hours->minutesOutsideBreak($left, $back, $clocks['start'], $clocks['end']);
        }
        if ($coverOpenOffice && $open?->out_at && $measureOut && $measureOut->greaterThan($open->out_at)) {
            $left = $open->out_at->copy()->timezone('Asia/Kolkata');
            $span = (int) round($left->diffInMinutes($measureOut, true));
            $away += $span;
            $office += $this->hours->minutesOutsideBreak($left, $measureOut, $clocks['start'], $clocks['end']);
        }
        $break = 0;
        if ($in && $measureOut && $measureOut->greaterThan($in)) {
            $break = $this->hours->coveredBreakMinutes($in, $measureOut, $clocks['start'], $clocks['end']);
        }
        $minutes = max(0, $gross - $personal - $break);

        $status = 'absent';
        if (($holiday && $holiday->closesTheCompany()) || $employeeHoliday) {
            $status = 'holiday';
        } elseif ($leave) {
            $status = 'on_leave';
        } elseif ($this->sundayOff($employee, $date)) {
            $status = $in ? 'present' : 'weekly_off';
        } elseif ($roster?->is_off || (! $roster && ! $this->calendar->isWorkingDay((int) $employee->company_id, $date))) {
            $status = 'weekly_off';
        } elseif ($this->isHalfDayEarlyLeave($passes, $date, $in)) {
            $status = 'half_day';
        } elseif ($in) {
            $status = 'present';
        } elseif (! $rule->mark_absent_without_punch && ! $in) {
            $status = 'unmarked';
        }

        $storedOut = $day->punch_out;
        $day->fill([
            'shift_id' => $roster?->shift_id,
            'status' => $status,
            'minutes_worked' => $minutes,
            'minutes_away' => $away,
            'minutes_personal' => $personal,
            'minutes_office' => $office,
            'source' => $day->source ?: 'manual',
            'punch_out' => $coverOpenOffice ? null : ($storedOut ?: $out?->format('H:i:s')),
        ])->save();

        return $day;
    }

    private function shiftEnd(Employee $employee, Carbon $date): Carbon
    {
        $window = $this->hours->windowFor($employee, $date);
        $start = Carbon::parse($date->toDateString().' '.$window['start'], 'Asia/Kolkata');
        $end = Carbon::parse($date->toDateString().' '.$window['end'], 'Asia/Kolkata');
        if (($window['overnight'] ?? false) && $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end;
    }

    private function sundayOff(Employee $employee, Carbon $date): bool
    {
        if ((int) $date->dayOfWeek !== Carbon::SUNDAY) {
            return false;
        }

        return ! $this->hours->worksSunday($employee, $date);
    }

    /**
     * @param  Collection<int, GatePass>  $passes
     */
    private function isHalfDayEarlyLeave($passes, Carbon $date, ?Carbon $in): bool
    {
        if (! $in) {
            return false;
        }

        return $passes->contains(function (GatePass $pass) {
            if (! $pass->isOpenOuting() || $pass->isOfficeWork()) {
                return false;
            }

            return $pass->isEarlyLeave()
                || $pass->status === GatePass::STATUS_EXPIRED
                || ($pass->expires_at !== null && $pass->expires_at->lte(now()));
        });
    }
}
