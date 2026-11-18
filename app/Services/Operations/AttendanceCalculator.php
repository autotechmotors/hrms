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
        if ($in && ! $out && $open?->out_at) {
            $out = $open->out_at->copy()->timezone('Asia/Kolkata');
        }
        if ($in && $out && $out->lessThanOrEqualTo($in)) {
            $out = $out->copy()->addDay();
        }
        $gross = ($in && $out && $out->greaterThan($in)) ? (int) round($in->diffInMinutes($out, true)) : 0;

        $away = 0;
        $office = 0;
        foreach ($passes as $pass) {
            $away += $pass->outingMinutes($out);
            $office += $pass->officeCreditMinutes();
        }
        $personal = max(0, $away - $office);
        $break = 0;
        if ($in && $out && $out->greaterThan($in)) {
            $clocks = $this->hours->breakClocks($employee, $date);
            $break = $this->hours->coveredBreakMinutes($in, $out, $clocks['start'], $clocks['end']);
        }
        $minutes = max(0, $gross - $away + $office - $break);

        $status = 'absent';
        if (($holiday && $holiday->kind === 'public') || $employeeHoliday) {
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

        $day->fill([
            'shift_id' => $roster?->shift_id,
            'status' => $status,
            'minutes_worked' => $minutes,
            'minutes_away' => $away,
            'minutes_personal' => $personal,
            'minutes_office' => $office,
            'source' => $day->source ?: 'manual',
        ])->save();

        return $day;
    }

    private function sundayOff(Employee $employee, Carbon $date): bool
    {
        if ((int) $date->dayOfWeek !== Carbon::SUNDAY) {
            return false;
        }

        return ! $this->hours->worksSunday($employee);
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
            if (! $pass->isOpenOuting()) {
                return false;
            }

            return $pass->isEarlyLeave() || $pass->status === GatePass::STATUS_EXPIRED;
        });
    }
}
