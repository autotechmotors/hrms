<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\DepartmentWorkHour;
use App\Models\Device;
use App\Models\Employee;
use App\Models\GatePass;
use App\Services\Devices\DeviceSync;
use App\Services\Devices\FaceAccess;
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

    public function test_a_personal_pass_from_half_past_one_to_three_cuts_one_hour(): void
    {
        [$employee] = $this->person();
        $employee->department->workHour->update([
            'weekday_start' => '08:00:00',
            'weekday_end' => '19:00:00',
        ]);
        $this->pass($employee, '13:30:00', '15:00:00', 'personal');
        $this->day($employee, '08:00:00', '19:00:00');

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-18'));
        $pay = app(PayDayMath::class)->range($employee->fresh(), Carbon::parse('2026-11-18'), Carbon::parse('2026-11-18'));

        $this->assertSame(60, $day->minutes_personal);
        $this->assertSame(60, $this->early($employee, $day));
        $this->assertStringContainsString('Personal Work · 13:30–15:00 · 60 min', $pay['days']['2026-11-18']['gate']);
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

    public function test_a_short_return_stays_on_the_pass_until_it_ends(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 16:06:00', 'Asia/Kolkata'));
        [$employee] = $this->person();
        $this->day($employee, '09:00:00', null);
        $pass = GatePass::query()->create([
            'company_id' => $employee->company_id,
            'number' => 'GP-AGAIN',
            'employee_id' => $employee->id,
            'date' => '2026-11-18',
            'out_time' => '16:00:00',
            'duration_minutes' => 15,
            'work_type' => 'personal',
            'reason' => 'Forgot something',
            'status' => GatePass::STATUS_APPROVED,
            'expires_at' => Carbon::parse('2026-11-18 16:15:00', 'Asia/Kolkata'),
            'machine_status' => 'ready',
        ]);
        $outDevice = Device::query()->create([
            'company_id' => $employee->company_id,
            'name' => 'OUT',
            'register_id' => 'HIKOUT',
            'ip_address' => '192.168.1.160',
            'punch_role' => 'out',
        ]);
        $inDevice = Device::query()->create([
            'company_id' => $employee->company_id,
            'name' => 'IN',
            'register_id' => 'HIKIN',
            'ip_address' => '192.168.1.156',
            'punch_role' => 'in',
        ]);
        $access = app(GatePassAccess::class);

        $left = AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'device_id' => $outDevice->id,
            'punched_at' => '2026-11-18 16:01:00',
            'direction' => 'out',
            'external_key' => 'again-out-1',
        ]);
        $access->attachPunch($employee, $left, $outDevice);

        $back = AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'device_id' => $inDevice->id,
            'punched_at' => '2026-11-18 16:05:00',
            'direction' => 'in',
            'external_key' => 'again-in',
        ]);
        $access->attachPunch($employee, $back, $inDevice);

        $again = AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'device_id' => $outDevice->id,
            'punched_at' => '2026-11-18 16:06:00',
            'direction' => 'out',
            'external_key' => 'again-out-2',
        ]);
        $access->attachPunch($employee, $again, $outDevice);

        $left->refresh();
        $back->refresh();
        $again->refresh();
        $pass->refresh();
        $faces = app(FaceAccess::class);

        $this->assertSame($pass->id, $left->gate_pass_id);
        $this->assertSame('out', $left->direction);
        $this->assertSame($pass->id, $back->gate_pass_id);
        $this->assertSame('in', $back->direction);
        $this->assertSame($pass->id, $again->gate_pass_id);
        $this->assertSame('out', $again->direction);
        $this->assertNull($pass->in_at);
        $this->assertSame('16:01:00', $pass->out_at->timezone('Asia/Kolkata')->format('H:i:s'));
        $this->assertSame('ready', $pass->machine_status);
        $this->assertTrue($faces->allowsFace($employee, Carbon::parse('2026-11-18 16:06:00', 'Asia/Kolkata')));
        $this->assertFalse($faces->allowsFace($employee, Carbon::parse('2026-11-18 16:16:00', 'Asia/Kolkata')));
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

    public function test_an_early_second_scan_is_not_the_out(): void
    {
        [$employee] = $this->person();
        CompanySetting::forCompany($employee->company_id)->update(['out_inactive_minutes' => 30]);
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 09:00:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'in-lead',
        ]);

        $access = app(GatePassAccess::class);

        $this->assertSame(30, $access->leadMinutes((int) $employee->company_id));
        $this->assertFalse($access->outAllowed($employee, Carbon::parse('2026-11-18 17:29:00', 'Asia/Kolkata')));
        $this->assertTrue($access->outAllowed($employee, Carbon::parse('2026-11-18 17:30:00', 'Asia/Kolkata')));
        $this->assertTrue($access->outAllowed($employee, Carbon::parse('2026-11-18 18:01:00', 'Asia/Kolkata')));

        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 10:39:00', 'Asia/Kolkata'),
            'direction' => 'out',
            'external_key' => 'early-scan',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 17:55:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'real-out',
        ]);

        app(DeviceSync::class)->rebuild($employee, Carbon::parse('2026-11-18'));

        $day = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-11-18')->first();
        $this->assertSame('09:00:00', substr((string) $day->punch_in, 0, 8));
        $this->assertSame('17:55:00', substr((string) $day->punch_out, 0, 8));
        $this->assertSame('in', AttendancePunch::query()->where('external_key', 'early-scan')->value('direction'));
    }

    public function test_a_gate_pass_does_not_replace_the_shift_out(): void
    {
        [$employee] = $this->person();
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 09:00:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'shift-in',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 11:15:00', 'Asia/Kolkata'),
            'direction' => 'out',
            'external_key' => 'pass-out',
        ]);
        $this->pass($employee, '11:00:00', null, 'personal', Carbon::parse('2026-11-18 12:00:00', 'Asia/Kolkata'));
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 17:55:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'shift-out',
        ]);

        app(DeviceSync::class)->rebuild($employee, Carbon::parse('2026-11-18'));

        $day = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-11-18')->first();
        $this->assertSame('09:00:00', substr((string) $day->punch_in, 0, 8));
        $this->assertSame('17:55:00', substr((string) $day->punch_out, 0, 8));
    }

    public function test_the_face_stays_off_after_in_until_the_out_window_or_a_pass(): void
    {
        [$employee] = $this->person();
        CompanySetting::forCompany($employee->company_id)->update(['out_inactive_minutes' => 10]);
        AttendanceDay::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-11-18',
            'punch_in' => '10:30:00',
            'status' => 'present',
            'source' => 'device',
        ]);

        $faces = app(FaceAccess::class);
        $this->assertFalse($faces->allowsFace($employee, Carbon::parse('2026-11-18 10:39:00', 'Asia/Kolkata')));
        $this->assertTrue($faces->allowsFace($employee, Carbon::parse('2026-11-18 17:50:00', 'Asia/Kolkata')));

        $this->pass($employee, '11:00:00', null, 'personal', Carbon::parse('2026-11-18 12:00:00', 'Asia/Kolkata'));
        $this->assertFalse($faces->allowsFace($employee, Carbon::parse('2026-11-18 10:39:00', 'Asia/Kolkata')));
        $this->assertTrue($faces->allowsFace($employee, Carbon::parse('2026-11-18 11:10:00', 'Asia/Kolkata')));
    }

    public function test_a_face_the_next_morning_closes_the_open_shift(): void
    {
        [$employee] = $this->person();
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-18 10:00:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'in-next',
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => Carbon::parse('2026-11-19 07:00:00', 'Asia/Kolkata'),
            'direction' => 'in',
            'external_key' => 'out-next',
        ]);

        app(DeviceSync::class)->rebuild($employee, Carbon::parse('2026-11-18'));
        app(DeviceSync::class)->rebuild($employee, Carbon::parse('2026-11-19'));

        $day = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-11-18')->first();
        $next = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-11-19')->first();
        $this->assertSame('10:00:00', substr((string) $day->punch_in, 0, 8));
        $this->assertSame('07:00:00', substr((string) $day->punch_out, 0, 8));
        $this->assertTrue($next === null || $next->punch_in === null);
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
