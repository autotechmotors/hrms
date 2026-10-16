<?php

namespace App\Services\Operations;

use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ShiftRoster;
use Carbon\Carbon;

class AttendanceCalculator
{
    public function __construct(private readonly WorkCalendarResolver $calendar)
    {
    }

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
        $leave = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();

        $in = $day->punch_in ? Carbon::parse($date->toDateString().' '.$day->punch_in) : null;
        $out = $day->punch_out ? Carbon::parse($date->toDateString().' '.$day->punch_out) : null;
        if ($in && $out && $out->lessThanOrEqualTo($in)) {
            $out = $out->copy()->addDay();
        }
        $minutes = ($in && $out && $out->greaterThan($in)) ? $in->diffInMinutes($out) : 0;
        if ($roster?->shift && $roster->shift->break_minutes && $minutes > 0) {
            $minutes = max(0, $minutes - (int) $roster->shift->break_minutes);
        }

        $status = 'absent';
        if ($holiday && $holiday->kind === 'public') {
            $status = 'holiday';
        } elseif ($leave) {
            $status = 'on_leave';
        } elseif ($roster?->is_off || (! $roster && ! $this->calendar->isWorkingDay((int) $employee->company_id, $date))) {
            $status = 'weekly_off';
        } elseif ($in && $out) {
            $status = 'present';
            if ($rule->half_day_minutes && $minutes < $rule->half_day_minutes) {
                $status = 'half_day';
            } elseif ($roster?->shift && ! $roster->shift->is_flexible) {
                $start = Carbon::parse($date->toDateString().' '.$roster->shift->starts_at)->addMinutes((int) $rule->grace_minutes);
                if ($in->greaterThan($start)) {
                    $status = 'late';
                }
            }
        } elseif (! $rule->mark_absent_without_punch && ! $in) {
            $status = 'unmarked';
        }

        $day->fill([
            'shift_id' => $roster?->shift_id,
            'status' => $status,
            'minutes_worked' => $minutes,
            'source' => $day->source ?: 'manual',
        ])->save();

        return $day;
    }
}
