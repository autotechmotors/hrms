<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Holiday;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class OperationsModulesTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_hr_can_record_a_shift_holiday_punch_and_payroll(): void
    {
        $org = $this->makeCompany('OPS');
        $employee = $this->makeEmployee($org, 'E9');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->post(route('shifts.store'), [
                'name' => 'General',
                'code' => 'GEN',
                'starts_at' => '09:00',
                'ends_at' => '18:00',
                'break_minutes' => 60,
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(Shift::query()->where('code', 'GEN')->where('company_id', $org['company']->id)->exists());

        $this->post(route('holidays.store'), [
            'holiday_date' => '2026-10-02',
            'name' => 'Gandhi Jayanti',
            'kind' => 'public',
        ])->assertRedirect();
        $this->assertTrue(Holiday::query()->where('name', 'Gandhi Jayanti')->exists());

        $this->post(route('attendance.punch'), [
            'employee_id' => $employee->id,
            'work_date' => '2026-10-16',
            'punch_in' => '09:05',
            'punch_out' => '18:00',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('present', AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-10-16')->value('status'));
        $this->assertSame('holiday', AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-10-02')->value('status'));

        foreach (['attendance.index', 'shifts.index', 'leave.index', 'holidays.index', 'hours-pay.index', 'salary-sheets.index', 'advances.index', 'devices.index', 'reports.index', 'bank.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_attendance_rules_can_turn_selfie_storage_off(): void
    {
        $org = $this->makeCompany('SEL');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Store the scan selfie on the attendance punch')
            ->assertSee('name="store_selfie"', false);

        $this->post(route('attendance.rule'), [
            'grace_minutes' => 5,
            'half_day_minutes' => '',
            'full_day_minutes' => '',
            'mark_absent_without_punch' => 1,
        ])->assertRedirect();

        $this->assertFalse((bool) AttendanceRule::query()->where('company_id', $org['company']->id)->value('store_selfie'));
    }

    public function test_holiday_calendar_exposes_clicked_and_outside_dates(): void
    {
        $org = $this->makeCompany('CAL');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->get(route('holidays.index', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee('October 2026', false)
            ->assertSee('data-days="31"', false)
            ->assertSee('data-date="2026-10-17"', false)
            ->assertSee('data-iso="2026-10-17"', false)
            ->assertDontSee('data-iso="2025-12-29"', false)
            ->assertSee('data-bs-target="#holidayModal"', false);
    }

    public function test_manager_cannot_change_shifts(): void
    {
        $org = $this->makeCompany('OPS');
        $manager = $this->makeUser('manager', $org['company']);

        $this->actingAsScoped($manager)->get(route('shifts.index'))->assertOk();
        $this->post(route('shifts.store'), [
            'name' => 'General',
            'code' => 'GEN',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ])->assertForbidden();
    }
}
