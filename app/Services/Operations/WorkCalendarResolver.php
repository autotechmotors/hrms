<?php

namespace App\Services\Operations;

use App\Models\Holiday;
use App\Models\WorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class WorkCalendarResolver
{
    public function isWorkingDay(int $companyId, CarbonInterface $date): bool
    {
        if (Holiday::query()->where('company_id', $companyId)->whereDate('holiday_date', $date)->where('kind', 'public')->exists()) {
            return false;
        }

        $calendar = WorkCalendar::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->with('days')
            ->first();

        if (! $calendar || $calendar->days->isEmpty()) {
            return ! in_array($date->dayOfWeek, [0, 6], true);
        }

        $day = $calendar->days->firstWhere('weekday', $date->dayOfWeek);

        return (bool) ($day?->is_working);
    }

    public function workingDaysInMonth(int $companyId, int $year, int $month): int
    {
        $cursor = now()->setDate($year, $month, 1)->startOfDay();
        $end = $cursor->copy()->endOfMonth();
        $count = 0;

        while ($cursor->lte($end)) {
            if ($this->isWorkingDay($companyId, $cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function leaveDays(int $companyId, CarbonInterface $start, CarbonInterface $end, bool $countWeeklyOff, bool $countHolidays): float
    {
        $days = 0.0;
        $cursor = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $holiday = Holiday::query()->where('company_id', $companyId)->whereDate('holiday_date', $cursor)->exists();
            $working = $this->isWorkingDay($companyId, $cursor);
            $skipHoliday = $holiday && ! $countHolidays;
            $skipWeeklyOff = ! $working && ! $holiday && ! $countWeeklyOff;
            if (! $skipHoliday && ! $skipWeeklyOff) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }
}
