<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\EmployeeWorkHour;
use App\Services\Devices\DeviceSync;
use App\Services\Operations\ShiftSchedule;
use App\Services\Operations\WorkHourResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class ShiftScheduleTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    public function test_a_month_must_be_filled_and_then_each_date_uses_that_clock(): void
    {
        $this->seedAccess();
        $org = $this->makeCompany('SHF');
        $parent = $this->makeEmployee($org, 'PAR');
        $child = $this->makeEmployee($org, 'CHD', $parent->id);
        $parentUser = $this->makeUser('manager', $org['company']);
        $childUser = $this->makeUser('employee', $org['company']);
        $other = $this->makeUser('manager', $org['company']);
        $parent->update(['user_id' => $parentUser->id]);
        $child->update(['user_id' => $childUser->id]);
        EmployeeWorkHour::query()->create([
            'employee_id' => $child->id,
            'uses_department' => false,
            'shift_kind' => 'day',
            'works_sunday' => true,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
            'night_weekday_start' => '21:00:00',
            'night_weekday_end' => '06:00:00',
        ]);

        $this->actingAsScoped($childUser)->postJson(route('employees.shifts.update', $child), [
            'year' => 2026,
            'month' => 11,
            'continuous' => 'day',
        ])->assertForbidden();

        $this->actingAsScoped($other)->postJson(route('employees.shifts.update', $child), [
            'year' => 2026,
            'month' => 11,
            'ranges' => [
                ['kind' => 'day', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-10'],
            ],
        ])->assertForbidden();

        $this->actingAsScoped($parentUser)->postJson(route('employees.shifts.update', $child), [
            'year' => 2026,
            'month' => 11,
            'ranges' => [
                ['kind' => 'day', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-10'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('ranges');

        $this->actingAsScoped($parentUser)->postJson(route('employees.shifts.update', $child), [
            'year' => 2026,
            'month' => 11,
            'ranges' => [
                ['kind' => 'day', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-10'],
                ['kind' => 'night', 'starts_on' => '2026-11-11', 'ends_on' => '2026-11-30'],
            ],
        ])->assertOk();

        $child->unsetRelation('workHour');
        $schedule = app(ShiftSchedule::class);
        $this->assertSame('day', $schedule->kindOn($child, Carbon::parse('2026-11-10')));
        $this->assertSame('night', $schedule->kindOn($child, Carbon::parse('2026-11-11')));

        $hours = app(WorkHourResolver::class);
        $this->assertSame('09:00', $hours->windowFor($child, Carbon::parse('2026-11-10'))['start']);
        $this->assertSame('21:00', $hours->windowFor($child, Carbon::parse('2026-11-11'))['start']);

        $this->actingAsScoped($parentUser)->postJson(route('employees.shifts.update', $child), [
            'year' => 2028,
            'month' => 2,
            'ranges' => [
                ['kind' => 'day', 'starts_on' => '2028-02-01', 'ends_on' => '2028-02-28'],
            ],
        ])->assertStatus(422);

        $this->actingAsScoped($parentUser)->postJson(route('employees.shifts.update', $child), [
            'year' => 2026,
            'month' => 12,
            'continuous' => 'night',
        ])->assertOk();

        $child->unsetRelation('workHour');
        $this->assertSame('night', $schedule->kindOn($child->fresh(), Carbon::parse('2026-12-01')));
        $this->assertSame('night', $schedule->kindOn($child->fresh(), Carbon::parse('2027-01-15')));
        $this->assertSame('day', $schedule->kindOn($child->fresh(), Carbon::parse('2026-11-01')));
    }

    public function test_a_night_out_stays_on_the_night_date_when_the_next_day_is_day(): void
    {
        $org = $this->makeCompany('MIX');
        $person = $this->makeEmployee($org, 'MX');
        EmployeeWorkHour::query()->create([
            'employee_id' => $person->id,
            'uses_department' => false,
            'shift_kind' => 'day',
            'works_sunday' => true,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
            'night_weekday_start' => '21:00:00',
            'night_weekday_end' => '06:00:00',
            'night_weekday_break_start' => '01:00:00',
            'night_weekday_break_end' => '02:00:00',
        ]);
        app(ShiftSchedule::class)->saveMonth($person, 2026, 11, null, [
            ['kind' => 'night', 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-18'],
            ['kind' => 'day', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-17'],
            ['kind' => 'day', 'starts_on' => '2026-11-19', 'ends_on' => '2026-11-30'],
        ]);

        AttendancePunch::query()->create([
            'employee_id' => $person->id,
            'punched_at' => Carbon::parse('2026-11-18 20:00:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'mix-in',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $person->id,
            'punched_at' => Carbon::parse('2026-11-19 06:00:00', 'Asia/Kolkata'),
            'direction' => 'out',
            'external_key' => 'mix-out',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $person->id,
            'punched_at' => Carbon::parse('2026-11-19 09:05:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'mix-day-in',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $person->id,
            'punched_at' => Carbon::parse('2026-11-19 18:00:00', 'Asia/Kolkata'),
            'direction' => 'out',
            'external_key' => 'mix-day-out',
        ]);

        $sync = app(DeviceSync::class);
        $sync->rebuild($person, Carbon::parse('2026-11-18'));
        $sync->rebuild($person, Carbon::parse('2026-11-19'));

        $night = AttendanceDay::query()->where('employee_id', $person->id)->whereDate('work_date', '2026-11-18')->first();
        $day = AttendanceDay::query()->where('employee_id', $person->id)->whereDate('work_date', '2026-11-19')->first();

        $this->assertSame('20:00:00', substr((string) $night->punch_in, 0, 8));
        $this->assertSame('06:00:00', substr((string) $night->punch_out, 0, 8));
        $this->assertSame('09:05:00', substr((string) $day->punch_in, 0, 8));
        $this->assertSame('18:00:00', substr((string) $day->punch_out, 0, 8));
    }
}
