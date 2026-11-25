<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\DepartmentWorkHour;
use App\Models\EmployeeCredential;
use App\Models\GatePass;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class DeviceAttendanceTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_entry_without_a_door_photo_shows_the_enrolled_face(): void
    {
        $org = $this->makeCompany('FACE');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, '100033');
        $jpeg = base64_encode(base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q=='));
        $face = EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'kind' => 'face',
            'slot' => '0',
            'content' => $jpeg,
        ]);
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => '2026-10-20 10:50:21',
            'direction' => 'in',
            'external_key' => 'face-fallback',
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-10-20',
            'punch_in' => '10:50:21',
            'status' => 'absent',
            'source' => 'device',
        ]);

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '10:50 AM')
            ->assertJsonPath('days.0.in_note', 'Enrolled face')
            ->assertJsonPath('days.0.in_photo', route('attendance.credential.photo', $face))
            ->assertJsonMissingPath('days.0.out_photo')
            ->assertJsonMissingPath('days.0.punches.0.photo');

        $this->get(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']))
            ->assertOk()
            ->assertSee('10:50 AM')
            ->assertSee('Tue, 20 Oct')
            ->assertDontSee('10:50:21');
    }

    public function test_repeat_face_scans_pair_into_one_clock_in_and_clock_out(): void
    {
        $org = $this->makeCompany('PAIR');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, '100033');
        foreach (['10:50:21', '10:50:26', '11:28:34'] as $time) {
            AttendancePunch::query()->create([
                'employee_id' => $employee->id,
                'punched_at' => '2026-10-20 '.$time,
                'direction' => 'in',
                'external_key' => 'pair-'.$time,
            ]);
        }

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '10:50 AM')
            ->assertJsonPath('days.0.out', null)
            ->assertJsonCount(2, 'days.0.punches')
            ->assertJsonPath('days.0.punches.0.direction', 'In')
            ->assertJsonPath('days.0.punches.1.direction', 'In')
            ->assertJsonPath('days.0.worked', null)
            ->assertJsonPath('days.0.status', 'Present');
    }

    public function test_one_face_stays_clock_in_and_a_later_return_does_not_erase_clock_out(): void
    {
        $org = $this->makeCompany('DAY');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $alone = $this->makeEmployee($org, 'AT0001');
        AttendancePunch::query()->create([
            'employee_id' => $alone->id,
            'punched_at' => '2026-10-20 08:15:00',
            'direction' => 'in',
            'external_key' => 'alone',
        ]);
        $returned = $this->makeEmployee($org, 'AT0006');
        foreach (['09:00:00', '09:10:00', '09:30:00', '09:40:00'] as $time) {
            AttendancePunch::query()->create([
                'employee_id' => $returned->id,
                'punched_at' => '2026-10-20 '.$time,
                'direction' => 'in',
                'external_key' => 'return-'.$time,
            ]);
        }

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $alone, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '08:15 AM')
            ->assertJsonPath('days.0.out', null)
            ->assertJsonPath('days.0.status', 'Present')
            ->assertJsonCount(1, 'days.0.punches');

        $this->getJson(route('attendance.employee.feed', ['employee' => $returned, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '09:00 AM')
            ->assertJsonPath('days.0.out', null)
            ->assertJsonPath('days.0.worked', null)
            ->assertJsonCount(4, 'days.0.punches')
            ->assertJsonPath('days.0.punches.0.direction', 'In')
            ->assertJsonPath('days.0.punches.1.direction', 'In')
            ->assertJsonPath('days.0.punches.2.direction', 'In')
            ->assertJsonPath('days.0.punches.3.direction', 'In');
    }

    public function test_worked_time_follows_the_in_and_out_times(): void
    {
        $org = $this->makeCompany('WORK');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'AT0006');
        $shift = Shift::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Floor',
            'code' => 'FLR',
            'starts_at' => '09:00:00',
            'ends_at' => '18:00:00',
            'break_minutes' => 60,
        ]);
        ShiftRoster::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-10-22',
            'shift_id' => $shift->id,
        ]);
        foreach (['10:17:35' => 'in', '17:55:00' => 'out'] as $time => $direction) {
            AttendancePunch::query()->create([
                'employee_id' => $employee->id,
                'punched_at' => '2026-10-22 '.$time,
                'direction' => $direction,
                'external_key' => 'worked-'.$time,
            ]);
        }

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '10:17 AM')
            ->assertJsonPath('days.0.out', '05:55 PM')
            ->assertJsonPath('days.0.worked', '7h 37m');

        $this->get(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-22']))
            ->assertOk()
            ->assertSee('7h 37m')
            ->assertDontSee('>1m<', false);
    }

    public function test_a_date_can_be_edited_and_deleted_from_the_attendance_record(): void
    {
        $org = $this->makeCompany('EDIT');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'AT0007');
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => '2026-10-20 16:40:00',
            'direction' => 'in',
            'external_key' => 'edit-in',
        ]);

        $this->actingAsScoped($hr)
            ->get(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']))
            ->assertOk()
            ->assertSee('title="Edit"', false)
            ->assertSee('title="Delete"', false);

        $this->post(route('attendance.day.update', $employee), [
            'work_date' => '2026-10-20',
            'punch_in' => '09:00',
            'punch_out' => '18:00',
        ])->assertRedirect(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']));

        $day = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-10-20')->firstOrFail();
        $this->assertSame('09:00:00', (string) $day->punch_in);
        $this->assertSame('18:00:00', (string) $day->punch_out);
        $this->assertDatabaseMissing('attendance_punches', ['external_key' => 'edit-in']);

        $this->delete(route('attendance.day.destroy', $employee), ['work_date' => '2026-10-20'])
            ->assertRedirect(route('attendance.show', ['employee' => $employee, 'date' => '2026-10-20']));

        $this->assertDatabaseMissing('attendance_days', ['employee_id' => $employee->id, 'work_date' => '2026-10-20']);
        $this->assertDatabaseMissing('attendance_punches', ['employee_id' => $employee->id]);
    }

    public function test_a_break_gate_pass_shows_its_out_and_return(): void
    {
        $org = $this->makeCompany('BRK');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'BR1');
        DepartmentWorkHour::query()->create([
            'department_id' => $org['department']->id,
            'hours_per_day' => 9,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
            'weekday_break_start' => '13:00:00',
            'weekday_break_end' => '14:00:00',
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-10-20',
            'punch_in' => '09:00:00',
            'punch_out' => '11:00:00',
            'minutes_worked' => 120,
            'status' => 'present',
            'source' => 'device',
        ]);
        GatePass::query()->create([
            'company_id' => $org['company']->id,
            'number' => 'GP-BRK-1',
            'employee_id' => $employee->id,
            'date' => '2026-10-20',
            'out_time' => '13:10:00',
            'duration_minutes' => 30,
            'work_type' => 'personal',
            'reason' => 'Break',
            'status' => 'approved',
            'expires_at' => '2026-10-20 13:40:00',
            'out_at' => '2026-10-20 13:10:00',
            'in_at' => '2026-10-20 13:40:00',
        ]);
        GatePass::query()->create([
            'company_id' => $org['company']->id,
            'number' => 'GP-BRK-2',
            'employee_id' => $employee->id,
            'date' => '2026-10-20',
            'out_time' => '11:00:00',
            'duration_minutes' => 30,
            'work_type' => 'personal',
            'reason' => 'Errand',
            'status' => 'approved',
            'expires_at' => '2026-10-20 11:30:00',
            'out_at' => '2026-10-20 11:00:00',
            'in_at' => '2026-10-20 11:20:00',
        ]);

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '09:00 AM')
            ->assertJsonPath('days.0.out', null)
            ->assertJsonPath('days.0.worked', null)
            ->assertJsonCount(2, 'days.0.passes')
            ->assertJsonPath('days.0.passes.0.type', 'Personal')
            ->assertJsonPath('days.0.passes.0.out', '11:00 AM')
            ->assertJsonPath('days.0.passes.0.in', '11:20 AM')
            ->assertJsonPath('days.0.passes.1.out', '01:10 PM')
            ->assertJsonPath('days.0.passes.1.in', '01:40 PM');
    }

    public function test_an_early_leave_pass_fills_the_out_column(): void
    {
        $org = $this->makeCompany('ELV');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'EL1');
        DepartmentWorkHour::query()->create([
            'department_id' => $org['department']->id,
            'hours_per_day' => 9,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-10-20',
            'punch_in' => '09:00:00',
            'punch_out' => '15:10:00',
            'minutes_worked' => 370,
            'status' => 'half_day',
            'source' => 'device',
        ]);
        GatePass::query()->create([
            'company_id' => $org['company']->id,
            'number' => 'GP-ELV',
            'employee_id' => $employee->id,
            'date' => '2026-10-20',
            'out_time' => '15:00:00',
            'duration_minutes' => 180,
            'is_early_leave' => true,
            'work_type' => 'personal',
            'reason' => 'Home',
            'status' => 'approved',
            'expires_at' => '2026-10-20 18:00:00',
            'out_at' => '2026-10-20 15:10:00',
        ]);

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.in', '09:00 AM')
            ->assertJsonPath('days.0.out', '03:10 PM')
            ->assertJsonPath('days.0.passes.0.early', true)
            ->assertJsonPath('days.0.passes.0.leave', 'Early leave')
            ->assertJsonPath('days.0.passes.0.out', '03:10 PM');
    }

    public function test_a_gate_pass_scan_is_labelled_apart_from_the_shift_clock(): void
    {
        $org = $this->makeCompany('LBL');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'LB1');
        $personal = GatePass::query()->create([
            'company_id' => $org['company']->id,
            'number' => 'GP-LBL-1',
            'employee_id' => $employee->id,
            'date' => '2026-10-20',
            'out_time' => '11:00:00',
            'duration_minutes' => 30,
            'work_type' => 'personal',
            'reason' => 'Bank',
            'status' => 'approved',
            'expires_at' => '2026-10-20 11:30:00',
            'out_at' => '2026-10-20 11:05:00',
            'in_at' => '2026-10-20 11:25:00',
        ]);
        $office = GatePass::query()->create([
            'company_id' => $org['company']->id,
            'number' => 'GP-LBL-2',
            'employee_id' => $employee->id,
            'date' => '2026-10-20',
            'out_time' => '15:00:00',
            'duration_minutes' => 60,
            'work_type' => 'office',
            'reason' => 'Delivery',
            'status' => 'approved',
            'expires_at' => '2026-10-20 16:00:00',
            'out_at' => '2026-10-20 15:02:00',
            'in_at' => '2026-10-20 15:40:00',
        ]);
        foreach ([
            ['2026-10-20 09:02:00', 'in', null],
            ['2026-10-20 11:05:00', 'out', $personal->id],
            ['2026-10-20 11:25:00', 'in', $personal->id],
            ['2026-10-20 15:02:00', 'out', $office->id],
            ['2026-10-20 15:40:00', 'in', $office->id],
            ['2026-10-20 18:05:00', 'out', null],
        ] as [$at, $direction, $passId]) {
            AttendancePunch::query()->create([
                'employee_id' => $employee->id,
                'punched_at' => $at,
                'direction' => $direction,
                'gate_pass_id' => $passId,
                'external_key' => 'lbl|'.$at,
            ]);
        }

        $this->actingAsScoped($hr)
            ->getJson(route('attendance.employee.feed', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('days.0.punches.0.direction', 'In')
            ->assertJsonPath('days.0.punches.1.direction', 'Personal out')
            ->assertJsonPath('days.0.punches.2.direction', 'Personal in')
            ->assertJsonPath('days.0.punches.3.direction', 'Company out')
            ->assertJsonPath('days.0.punches.4.direction', 'Company in')
            ->assertJsonPath('days.0.punches.5.direction', 'Out');
    }
}
