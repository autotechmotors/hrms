<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\EmployeeWorkHour;
use App\Services\Devices\DeviceSync;
use App\Services\Operations\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class NightDutyTest extends TestCase
{
    use BuildsOrganization;
    use RefreshDatabase;

    public function test_a_night_in_and_next_morning_out_count_on_the_in_date(): void
    {
        $org = $this->makeCompany('NITE');
        $person = $this->makeEmployee($org, 'N18');
        EmployeeWorkHour::query()->create([
            'employee_id' => $person->id,
            'uses_department' => false,
            'shift_kind' => 'night',
            'works_sunday' => false,
            'night_weekday_break_start' => '01:00:00',
            'night_weekday_break_end' => '02:00:00',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $person->id,
            'punched_at' => Carbon::parse('2026-11-18 20:00:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'n18-in',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $person->id,
            'punched_at' => Carbon::parse('2026-11-19 08:00:00', 'Asia/Kolkata'),
            'direction' => 'out',
            'external_key' => 'n18-out',
        ]);

        $sync = app(DeviceSync::class);
        $sync->rebuild($person, Carbon::parse('2026-11-18'));
        $sync->rebuild($person, Carbon::parse('2026-11-19'));

        $inDay = AttendanceDay::query()->where('employee_id', $person->id)->whereDate('work_date', '2026-11-18')->first();
        $nextDay = AttendanceDay::query()->where('employee_id', $person->id)->whereDate('work_date', '2026-11-19')->first();

        $this->assertNotNull($inDay);
        $this->assertSame('20:00:00', substr((string) $inDay->punch_in, 0, 8));
        $this->assertSame('08:00:00', substr((string) $inDay->punch_out, 0, 8));
        $this->assertSame(11 * 60, (int) $inDay->minutes_worked);
        $this->assertTrue($nextDay === null || $nextDay->punch_in === null);
    }

    public function test_a_day_break_comes_off_the_attended_minutes(): void
    {
        $org = $this->makeCompany('BRK');
        $person = $this->makeEmployee($org, 'B1');
        EmployeeWorkHour::query()->create([
            'employee_id' => $person->id,
            'uses_department' => false,
            'shift_kind' => 'day',
            'works_sunday' => true,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
            'weekday_break_start' => '13:00:00',
            'weekday_break_end' => '14:00:00',
            'hours_per_day' => 9,
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $person->id,
            'work_date' => '2026-11-18',
            'punch_in' => '09:00:00',
            'punch_out' => '18:00:00',
            'status' => 'present',
            'source' => 'manual',
        ]);

        $day = app(AttendanceCalculator::class)->recalculate($person, Carbon::parse('2026-11-18'));

        $this->assertSame(8 * 60, (int) $day->minutes_worked);
    }
}
