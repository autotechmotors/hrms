<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\DepartmentWorkHour;
use App\Models\Employee;
use App\Models\GatePass;
use App\Services\Devices\DeviceSync;
use App\Services\Operations\AttendanceCalculator;
use App\Services\Operations\GatePassAccess;
use App\Services\Operations\PayDayMath;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class BreakGateTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_personal_time_inside_the_break_is_not_cut_and_time_outside_is(): void
    {
        [$employee] = $this->person();
        $this->pass($employee, '13:00:00', '14:00:00', 'personal');
        $this->day($employee, '09:00:00', '18:00:00');

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));

        $this->assertSame(0, $day->minutes_personal);
        $this->assertSame(480, $day->minutes_worked);
        $this->assertSame(0, $this->early($employee, $day));
    }

    public function test_personal_time_outside_the_break_is_cut(): void
    {
        [$employee] = $this->person();
        $this->pass($employee, '11:00:00', '12:00:00', 'personal');
        $this->day($employee, '09:00:00', '18:00:00');

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));

        $this->assertSame(60, $day->minutes_personal);
        $this->assertSame(60, $this->early($employee, $day));
    }

    public function test_a_late_return_from_break_cuts_only_the_extra_minutes(): void
    {
        [$employee] = $this->person();
        $this->pass($employee, '13:00:00', '14:30:00', 'personal');
        $this->day($employee, '09:00:00', '18:00:00');

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));

        $this->assertSame(30, $day->minutes_personal);
        $this->assertSame(30, $this->early($employee, $day));
    }

    public function test_office_work_is_not_cut(): void
    {
        [$employee] = $this->person();
        $this->pass($employee, '11:00:00', '12:30:00', 'office');
        $this->day($employee, '09:00:00', '18:00:00');

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));

        $this->assertSame(0, $day->minutes_personal);
        $this->assertSame(90, $day->minutes_office);
        $this->assertSame(480, $day->minutes_worked);
        $this->assertSame(0, $this->early($employee, $day));
    }

    public function test_office_work_still_outside_does_not_cut_the_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 15:00:00', 'Asia/Kolkata'));
        [$employee] = $this->person();
        $this->day($employee, '09:00:00', null);
        $this->pass($employee, '11:00:00', null, 'office', Carbon::parse('2026-11-18 12:00:00', 'Asia/Kolkata'));

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));

        $this->assertSame('present', $day->status);
        $this->assertNull($day->punch_out);
        $this->assertSame(0, $day->minutes_personal);
        $this->assertSame(480, $day->minutes_worked);
        $this->assertSame(0, $this->early($employee, $day->fresh()));
    }

    public function test_a_real_punch_out_after_office_work_still_cuts_only_the_early_leave(): void
    {
        [$employee] = $this->person();
        $this->day($employee, '09:00:00', '17:00:00');
        $this->pass($employee, '11:00:00', null, 'office');

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));

        $this->assertSame('17:00:00', $day->punch_out);
        $this->assertSame(0, $day->minutes_personal);
        $this->assertSame(420, $day->minutes_worked);
        $this->assertSame(60, $this->early($employee, $day->fresh()));
    }

    public function test_a_personal_pass_with_no_return_is_early_leave(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 15:00:00', 'Asia/Kolkata'));
        [$employee] = $this->person();
        $this->day($employee, '09:00:00', null);
        GatePass::query()->create([
            'company_id' => $employee->company_id,
            'number' => 'GP-OPEN',
            'employee_id' => $employee->id,
            'date' => '2026-11-18',
            'out_time' => '13:00:00',
            'duration_minutes' => 60,
            'work_type' => 'personal',
            'reason' => 'Lunch errand',
            'status' => GatePass::STATUS_APPROVED,
            'expires_at' => Carbon::parse('2026-11-18 14:00:00', 'Asia/Kolkata'),
            'out_at' => Carbon::parse('2026-11-18 13:00:00', 'Asia/Kolkata'),
            'machine_status' => 'ready',
        ]);

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));

        $this->assertSame('half_day', $day->status);
        $this->assertSame('13:00:00', $day->punch_out);
        $this->assertSame(240, $this->early($employee, $day->fresh()));
    }

    public function test_a_manual_day_stays_when_the_terminal_is_rebuilt(): void
    {
        [$employee] = $this->person();
        $day = $this->day($employee, '09:00:00', '18:00:00');
        $day->update(['source' => 'manual']);

        app(DeviceSync::class)->rebuild($employee, Carbon::parse('2026-11-18'));

        $day->refresh();
        $this->assertSame('manual', $day->source);
        $this->assertSame('09:00:00', $day->punch_in);
        $this->assertSame('18:00:00', $day->punch_out);
    }

    public function test_out_machine_allows_a_pass_or_the_last_ten_minutes_after_an_in(): void
    {
        [$employee] = $this->person();
        $access = app(GatePassAccess::class);
        $early = Carbon::parse('2026-11-18 16:00:00', 'Asia/Kolkata');
        $nearEnd = Carbon::parse('2026-11-18 17:50:00', 'Asia/Kolkata');

        $this->assertFalse($access->outAllowed($employee, $nearEnd));

        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 09:00:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'in-1',
        ]);

        $this->assertFalse($access->outAllowed($employee, $early));
        $this->assertTrue($access->outAllowed($employee, $nearEnd));

        $this->pass($employee, '16:00:00', null, 'personal', $early->copy()->addHour());
        $this->assertTrue($access->outAllowed($employee, $early));
    }

    /**
     * @return array{0: Employee}
     */
    private function person(): array
    {
        $org = $this->makeCompany('BRK');
        $employee = $this->makeEmployee($org, 'B1');
        DepartmentWorkHour::query()->create([
            'department_id' => $org['department']->id,
            'hours_per_day' => 9,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
            'weekday_break_start' => '13:00:00',
            'weekday_break_end' => '14:00:00',
        ]);

        return [$employee];
    }

    private function day(Employee $employee, string $in, ?string $out): AttendanceDay
    {
        return AttendanceDay::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-11-18',
            'punch_in' => $in,
            'punch_out' => $out,
            'status' => 'present',
            'source' => 'manual',
        ]);
    }

    private function pass(Employee $employee, string $out, ?string $in, string $work, ?Carbon $expires = null): void
    {
        GatePass::query()->create([
            'company_id' => $employee->company_id,
            'number' => 'GP-'.$work.'-'.$out,
            'employee_id' => $employee->id,
            'date' => '2026-11-18',
            'out_time' => $out,
            'duration_minutes' => 60,
            'work_type' => $work,
            'reason' => 'Visit',
            'status' => GatePass::STATUS_APPROVED,
            'expires_at' => $expires ?? Carbon::parse('2026-11-18 '.$out, 'Asia/Kolkata')->addHour(),
            'out_at' => Carbon::parse('2026-11-18 '.$out, 'Asia/Kolkata'),
            'in_at' => $in ? Carbon::parse('2026-11-18 '.$in, 'Asia/Kolkata') : null,
            'machine_status' => 'ready',
        ]);
    }

    private function early(Employee $employee, AttendanceDay $day): int
    {
        return (int) app(PayDayMath::class)->day($employee, Carbon::parse('2026-11-18'), 30000, 0, $day, 'working')['grid']['early'];
    }
}
