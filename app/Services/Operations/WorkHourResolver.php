<?php

namespace App\Services\Operations;

use App\Models\DepartmentWorkHour;
use App\Models\Employee;
use App\Models\EmployeeWorkHour;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class WorkHourResolver
{
    /**
     * @return array{hours: ?float, source: string, department_hours: ?float}
     */
    public function resolve(?DepartmentWorkHour $department, ?EmployeeWorkHour $employee): array
    {
        $departmentHours = $department?->hours_per_day !== null ? (float) $department->hours_per_day : null;

        if ($employee && ! $employee->uses_department && $employee->hours_per_day !== null) {
            return [
                'hours' => (float) $employee->hours_per_day,
                'source' => 'own',
                'department_hours' => $departmentHours,
            ];
        }

        if ($departmentHours !== null) {
            return [
                'hours' => $departmentHours,
                'source' => 'department',
                'department_hours' => $departmentHours,
            ];
        }

        return [
            'hours' => null,
            'source' => 'unset',
            'department_hours' => null,
        ];
    }

    /**
     * Null keeps the older rule: a night person is off on Sunday, a day person works it.
     */
    public function worksSunday(Employee $employee): bool
    {
        $employee->loadMissing('workHour');
        $row = $employee->workHour;
        if ($row && $row->works_sunday !== null) {
            return (bool) $row->works_sunday;
        }

        return ($row?->shift_kind ?? 'day') !== 'night';
    }

    /**
     * Clocks shown on the employee page. Night people see their night window.
     *
     * @return array{weekday_start: string, weekday_end: string, sunday_start: string, sunday_end: string, works_sunday: bool, shift_kind: string, day_start: string, day_end: string, night_start: string, night_end: string, day_sunday_start: string, day_sunday_end: string, night_sunday_start: string, night_sunday_end: string}
     */
    public function editor(Employee $employee): array
    {
        $employee->loadMissing(['workHour', 'department.workHour']);
        $row = $employee->workHour;
        $night = ($row?->shift_kind ?? 'day') === 'night';
        $source = ($row && ! $row->uses_department) ? $row : $employee->department?->workHour;
        $dayStart = $this->clock($source?->weekday_start ?? null, '09:00');
        $dayEnd = $this->clock($source?->weekday_end ?? null, '18:00');
        $daySundayStart = $this->clock($source?->sunday_start ?? null, '08:00');
        $daySundayEnd = $this->clock($source?->sunday_end ?? null, '13:00');
        $nightStart = $this->clock($source?->night_weekday_start ?? null, '21:00');
        $nightEnd = $this->clock($source?->night_weekday_end ?? null, '06:00');
        $nightSundayStart = $this->clock($source?->night_sunday_start ?? null, '21:00');
        $nightSundayEnd = $this->clock($source?->night_sunday_end ?? null, '02:00');

        return [
            'weekday_start' => $night ? $nightStart : $dayStart,
            'weekday_end' => $night ? $nightEnd : $dayEnd,
            'sunday_start' => $night ? $nightSundayStart : $daySundayStart,
            'sunday_end' => $night ? $nightSundayEnd : $daySundayEnd,
            'works_sunday' => $this->worksSunday($employee),
            'shift_kind' => $night ? 'night' : 'day',
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
            'night_start' => $nightStart,
            'night_end' => $nightEnd,
            'day_sunday_start' => $daySundayStart,
            'day_sunday_end' => $daySundayEnd,
            'night_sunday_start' => $nightSundayStart,
            'night_sunday_end' => $nightSundayEnd,
            'weekday_break_start' => $night
                ? $this->clockOrEmpty($source?->night_weekday_break_start ?? null)
                : $this->clockOrEmpty($source?->weekday_break_start ?? null),
            'weekday_break_end' => $night
                ? $this->clockOrEmpty($source?->night_weekday_break_end ?? null)
                : $this->clockOrEmpty($source?->weekday_break_end ?? null),
            'sunday_break_start' => $night
                ? $this->clockOrEmpty($source?->night_sunday_break_start ?? null)
                : $this->clockOrEmpty($source?->sunday_break_start ?? null),
            'sunday_break_end' => $night
                ? $this->clockOrEmpty($source?->night_sunday_break_end ?? null)
                : $this->clockOrEmpty($source?->sunday_break_end ?? null),
            'day_break_start' => $this->clockOrEmpty($source?->weekday_break_start ?? null),
            'day_break_end' => $this->clockOrEmpty($source?->weekday_break_end ?? null),
            'night_break_start' => $this->clockOrEmpty($source?->night_weekday_break_start ?? null),
            'night_break_end' => $this->clockOrEmpty($source?->night_weekday_break_end ?? null),
            'day_sunday_break_start' => $this->clockOrEmpty($source?->sunday_break_start ?? null),
            'day_sunday_break_end' => $this->clockOrEmpty($source?->sunday_break_end ?? null),
            'night_sunday_break_start' => $this->clockOrEmpty($source?->night_sunday_break_start ?? null),
            'night_sunday_break_end' => $this->clockOrEmpty($source?->night_sunday_break_end ?? null),
        ];
    }

    /**
     * @param  array{weekday_start: string, weekday_end: string, sunday_start?: string, sunday_end?: string, works_sunday: bool, shift_kind?: string}  $input
     */
    public function saveForEmployee(Employee $employee, array $input): void
    {
        $employee->loadMissing(['workHour', 'department.workHour']);
        $current = $employee->workHour;
        $department = $employee->department?->workHour;
        $weekStart = $this->clock($input['weekday_start'], '09:00');
        $weekEnd = $this->clock($input['weekday_end'], '18:00');
        $kind = $input['shift_kind'] ?? null;
        $night = $kind === 'night' || ($kind !== 'day' && $this->crossesMidnight($weekStart, $weekEnd));
        $payload = [
            'uses_department' => false,
            'shift_kind' => $night ? 'night' : 'day',
            'works_sunday' => (bool) $input['works_sunday'],
        ];

        if ($night) {
            $payload['night_weekday_start'] = $weekStart;
            $payload['night_weekday_end'] = $weekEnd;
            $payload['weekday_start'] = $this->clock($current?->weekday_start ?? $department?->weekday_start, '09:00');
            $payload['weekday_end'] = $this->clock($current?->weekday_end ?? $department?->weekday_end, '18:00');
            $payload['hours_per_day'] = $this->hoursFromWindow($payload['weekday_start'], $payload['weekday_end']);
        } else {
            $payload['weekday_start'] = $weekStart;
            $payload['weekday_end'] = $weekEnd;
            $payload['hours_per_day'] = $this->hoursFromWindow($weekStart, $weekEnd);
        }

        $weekBreak = $this->pair($input['weekday_break_start'] ?? null, $input['weekday_break_end'] ?? null);
        if ($night) {
            $payload['night_weekday_break_start'] = $weekBreak['start'];
            $payload['night_weekday_break_end'] = $weekBreak['end'];
        } else {
            $payload['weekday_break_start'] = $weekBreak['start'];
            $payload['weekday_break_end'] = $weekBreak['end'];
        }

        if ($payload['works_sunday']) {
            $sunStart = $this->clock($input['sunday_start'] ?? null, '08:00');
            $sunEnd = $this->clock($input['sunday_end'] ?? null, '13:00');
            $payload['sunday_start'] = $sunStart;
            $payload['sunday_end'] = $sunEnd;
            $sunBreak = $this->pair($input['sunday_break_start'] ?? null, $input['sunday_break_end'] ?? null);
            if ($night) {
                $payload['night_sunday_start'] = $sunStart;
                $payload['night_sunday_end'] = $sunEnd;
                $payload['night_sunday_break_start'] = $sunBreak['start'];
                $payload['night_sunday_break_end'] = $sunBreak['end'];
            } else {
                $payload['sunday_break_start'] = $sunBreak['start'];
                $payload['sunday_break_end'] = $sunBreak['end'];
            }
        }

        EmployeeWorkHour::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            $payload
        );
    }

    /**
     * Night duty runs from noon on the in date until noon the next day.
     * A morning out belongs to the previous evening's in.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function attendanceSpan(Employee $employee, CarbonInterface $date): array
    {
        $day = Carbon::parse($date)->timezone('Asia/Kolkata')->startOfDay();
        $employee->loadMissing('workHour');
        if (($employee->workHour?->shift_kind ?? 'day') === 'night') {
            return [
                'from' => $day->copy()->setTime(12, 0),
                'to' => $day->copy()->addDay()->setTime(12, 0),
            ];
        }

        return [
            'from' => $day->copy(),
            'to' => $day->copy()->addDay()->startOfDay(),
        ];
    }

    /**
     * @return array{start: ?string, end: ?string}
     */
    public function breakClocks(Employee $employee, CarbonInterface $date): array
    {
        $employee->loadMissing(['workHour', 'department.workHour']);
        $own = $employee->workHour;
        $source = ($own && ! $own->uses_department) ? $own : $employee->department?->workHour;
        $sunday = (int) Carbon::parse($date)->dayOfWeek === Carbon::SUNDAY;
        $night = ($own?->shift_kind ?? 'day') === 'night';
        if ($night && $sunday && ! $this->worksSunday($employee)) {
            return ['start' => null, 'end' => null];
        }
        if ($night) {
            $start = $sunday ? ($source?->night_sunday_break_start ?? null) : ($source?->night_weekday_break_start ?? null);
            $end = $sunday ? ($source?->night_sunday_break_end ?? null) : ($source?->night_weekday_break_end ?? null);
        } else {
            $start = $sunday ? ($source?->sunday_break_start ?? null) : ($source?->weekday_break_start ?? null);
            $end = $sunday ? ($source?->sunday_break_end ?? null) : ($source?->weekday_break_end ?? null);
        }

        return [
            'start' => $this->clockOrEmpty($start) ?: null,
            'end' => $this->clockOrEmpty($end) ?: null,
        ];
    }

    public function coveredBreakMinutes(CarbonInterface $in, CarbonInterface $out, ?string $breakStart, ?string $breakEnd): int
    {
        $startMinutes = $this->minutes($breakStart);
        $endMinutes = $this->minutes($breakEnd);
        if ($startMinutes === null || $endMinutes === null || $startMinutes === $endMinutes) {
            return 0;
        }

        $breakFrom = Carbon::parse($in)->copy()->startOfDay()->addMinutes($startMinutes);
        $breakTo = Carbon::parse($in)->copy()->startOfDay()->addMinutes($endMinutes);
        if ($breakTo->lessThanOrEqualTo($breakFrom)) {
            $breakTo->addDay();
        }
        if ($breakTo->lessThanOrEqualTo($in)) {
            $breakFrom->addDay();
            $breakTo->addDay();
        }

        $from = $breakFrom->greaterThan($in) ? $breakFrom : Carbon::parse($in);
        $to = $breakTo->lessThan($out) ? $breakTo : Carbon::parse($out);
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        return (int) round($from->diffInMinutes($to, true));
    }

    /**
     * @return array{start: ?string, end: ?string}
     */
    public function pair(mixed $start, mixed $end): array
    {
        $startText = trim((string) $start);
        $endText = trim((string) $end);
        if ($startText === '' && $endText === '') {
            return ['start' => null, 'end' => null];
        }

        return [
            'start' => $startText === '' ? null : $this->clock($startText, '00:00'),
            'end' => $endText === '' ? null : $this->clock($endText, '00:00'),
        ];
    }

    public function breakFits(string $windowStart, string $windowEnd, ?string $breakStart, ?string $breakEnd): bool
    {
        if ($breakStart === null || $breakEnd === null) {
            return $breakStart === null && $breakEnd === null;
        }
        if ($this->span($breakStart, $breakEnd) < 1) {
            return false;
        }

        $overnight = $this->crossesMidnight($windowStart, $windowEnd);
        $windowFrom = (int) $this->minutes($windowStart);
        $windowTo = (int) $this->minutes($windowEnd);
        $breakFrom = (int) $this->minutes($breakStart);
        $breakTo = (int) $this->minutes($breakEnd);
        $windowFinish = $overnight ? $windowTo + 1440 : $windowTo;
        $breakAt = $this->onWindow($breakFrom, $windowFrom, $windowTo, $overnight);
        $breakFinish = $this->onWindow($breakTo, $windowFrom, $windowTo, $overnight);
        if ($breakFinish <= $breakAt) {
            $breakFinish += 1440;
        }

        return $breakAt >= $windowFrom && $breakFinish <= $windowFinish && $breakFinish > $breakAt;
    }

    public function clockOrEmpty(mixed $value): string
    {
        return $this->minutes($value) === null ? '' : $this->clock($value, '00:00');
    }

    private function onWindow(int $clock, int $start, int $finish, bool $overnight): int
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

    public function forEmployee(Employee $employee): ?float
    {
        $employee->loadMissing(['workHour', 'department.workHour']);

        return $this->resolve($employee->department?->workHour, $employee->workHour)['hours'];
    }

    /**
     * Clock window for one date. A roster night shift wins for that day.
     * Otherwise the person’s day or night choice selects the Hours & pay clocks.
     * The minute rate always uses the Monday–Saturday day length.
     *
     * @return array{start: string, end: string, label: string, standard_minutes: int, overnight: bool}
     */
    public function windowFor(Employee $employee, CarbonInterface $date, ?Shift $shift = null): array
    {
        $employee->loadMissing(['workHour', 'department.workHour']);
        $own = $employee->workHour;
        $source = ($own && ! $own->uses_department) ? $own : $employee->department?->workHour;
        $sunday = (int) $date->dayOfWeek === 0;
        $standardStart = $this->clock($source?->weekday_start ?? null, '09:00');
        $standardEnd = $this->clock($source?->weekday_end ?? null, '18:00');
        $standard = $this->span($standardStart, $standardEnd);

        if ($shift && $this->crossesMidnight($shift->starts_at, $shift->ends_at)) {
            $start = $this->clock($shift->starts_at, '21:00');
            $end = $this->clock($shift->ends_at, '06:00');

            return $this->window($start, $end, $standard);
        }

        $night = ($own?->shift_kind ?? 'day') === 'night';
        if ($night) {
            $start = $this->clock($sunday ? ($source?->night_sunday_start ?? null) : ($source?->night_weekday_start ?? null), '21:00');
            $end = $this->clock($sunday ? ($source?->night_sunday_end ?? null) : ($source?->night_weekday_end ?? null), $sunday ? '02:00' : '06:00');
        } else {
            $start = $this->clock($sunday ? ($source?->sunday_start ?? null) : ($source?->weekday_start ?? null), $sunday ? '08:00' : '09:00');
            $end = $this->clock($sunday ? ($source?->sunday_end ?? null) : ($source?->weekday_end ?? null), $sunday ? '13:00' : '18:00');
        }

        return $this->window($start, $end, $standard);
    }

    /**
     * @return array{start: string, end: string, label: string, standard_minutes: int, overnight: bool}
     */
    private function window(string $start, string $end, int $standard): array
    {
        return [
            'start' => $start,
            'end' => $end,
            'label' => $start.'–'.$end,
            'standard_minutes' => max(1, $standard),
            'overnight' => $this->crossesMidnight($start, $end),
        ];
    }

    public function crossesMidnight(mixed $start, mixed $end): bool
    {
        $from = $this->minutes($start);
        $to = $this->minutes($end);

        return $from !== null && $to !== null && $to <= $from;
    }

    public function hoursFromWindow(string $start, string $end): float
    {
        return round($this->span($start, $end) / 60, 2);
    }

    public function clock(mixed $value, string $fallback): string
    {
        $minutes = $this->minutes($value);

        return $minutes === null ? $fallback : sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function minutes(mixed $value): ?int
    {
        if ($value instanceof CarbonInterface) {
            return ((int) $value->format('H')) * 60 + (int) $value->format('i');
        }

        if ($value instanceof Model) {
            return null;
        }

        $text = substr(trim((string) $value), 0, 5);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $text, $match)) {
            return null;
        }

        return ((int) $match[1]) * 60 + (int) $match[2];
    }

    public function span(string $start, string $end): int
    {
        $from = $this->minutes($start);
        $to = $this->minutes($end);
        if ($from === null || $to === null || $to === $from) {
            return 0;
        }

        if ($to < $from) {
            return (1440 - $from) + $to;
        }

        return $to - $from;
    }

    public function label(?float $hours): string
    {
        if ($hours === null) {
            return 'Not set';
        }

        $text = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

        return $text.' h';
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'own' => 'Own hours',
            'department' => 'Department',
            default => 'Not set',
        };
    }
}
