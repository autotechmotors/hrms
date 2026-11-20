<?php

use App\Models\DepartmentWorkHour;
use App\Models\EmployeeWorkHour;
use App\Services\Operations\WorkHourResolver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $hours = app(WorkHourResolver::class);
        DepartmentWorkHour::query()->orderBy('id')->each(function (DepartmentWorkHour $row) use ($hours): void {
            $this->fill($hours, $row);
        });
        EmployeeWorkHour::query()->orderBy('id')->each(function (EmployeeWorkHour $row) use ($hours): void {
            $this->fill($hours, $row);
        });
    }

    public function down(): void
    {
        //
    }

    private function fill(WorkHourResolver $hours, DepartmentWorkHour|EmployeeWorkHour $row): void
    {
        $dirty = false;
        $pairs = [
            ['weekday_start', 'weekday_end', 'weekday_break_start', 'weekday_break_end', '13:00', '14:00'],
            ['night_weekday_start', 'night_weekday_end', 'night_weekday_break_start', 'night_weekday_break_end', '01:00', '02:00'],
            ['night_sunday_start', 'night_sunday_end', 'night_sunday_break_start', 'night_sunday_break_end', '01:00', '02:00'],
        ];
        foreach ($pairs as [$start, $end, $breakStart, $breakEnd, $from, $to]) {
            if ($row->{$breakStart} || $row->{$breakEnd}) {
                continue;
            }
            $windowStart = $hours->clockOrEmpty($row->{$start});
            $windowEnd = $hours->clockOrEmpty($row->{$end});
            if ($windowStart === '' || $windowEnd === '') {
                continue;
            }
            if (! $hours->breakFits($windowStart, $windowEnd, $from, $to)) {
                continue;
            }
            $row->{$breakStart} = $from.':00';
            $row->{$breakEnd} = $to.':00';
            $dirty = true;
        }
        if ($dirty) {
            $row->save();
        }
    }
};
