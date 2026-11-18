<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;

class WorkWindowDefaults
{
    public const WEEKDAY_START = '09:00:00';

    public const WEEKDAY_END = '18:00:00';

    public const SUNDAY_START = '08:00:00';

    public const SUNDAY_END = '13:00:00';

    public const HOURS_PER_DAY = 9;

    /**
     * Monday–Saturday 09:00–18:00 and Sunday 08:00–13:00 for every active department.
     * People who already have their own hours get the same four times. Salaries are left as they are.
     */
    public static function apply(): void
    {
        $now = now();
        $windows = [
            'weekday_start' => self::WEEKDAY_START,
            'weekday_end' => self::WEEKDAY_END,
            'sunday_start' => self::SUNDAY_START,
            'sunday_end' => self::SUNDAY_END,
            'hours_per_day' => self::HOURS_PER_DAY,
            'updated_at' => $now,
        ];

        foreach (DB::table('departments')->where('is_active', true)->pluck('id') as $departmentId) {
            $exists = DB::table('department_work_hours')->where('department_id', $departmentId)->exists();
            if ($exists) {
                DB::table('department_work_hours')->where('department_id', $departmentId)->update($windows);

                continue;
            }

            DB::table('department_work_hours')->insert($windows + [
                'department_id' => $departmentId,
                'created_at' => $now,
            ]);
        }

        DB::table('employee_work_hours')
            ->where('uses_department', false)
            ->update($windows);

        foreach (DB::table('work_calendars')->where('is_active', true)->pluck('id') as $calendarId) {
            for ($weekday = 0; $weekday <= 6; $weekday++) {
                $found = DB::table('work_calendar_days')
                    ->where('work_calendar_id', $calendarId)
                    ->where('weekday', $weekday)
                    ->first();

                if ($found) {
                    DB::table('work_calendar_days')->where('id', $found->id)->update(['is_working' => true]);

                    continue;
                }

                DB::table('work_calendar_days')->insert([
                    'work_calendar_id' => $calendarId,
                    'weekday' => $weekday,
                    'is_working' => true,
                ]);
            }
        }
    }
}
