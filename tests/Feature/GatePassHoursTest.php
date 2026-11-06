<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\Device;
use App\Models\DeviceUser;
use App\Models\GatePass;
use App\Models\User;
use App\Services\Operations\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class GatePassHoursTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        Carbon::setTestNow(Carbon::parse('2026-11-06 16:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pending_pass_is_not_on_gate_machine_until_parent_approves(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $arifUser = $this->linkUser('manager', $arif);
        $parent = $this->linkUser('manager', $manish);
        $gate = $this->makeGate($org);

        $this->actingAsScoped($arifUser)
            ->post(route('gate-passes.store'), $this->payload($arif->id))
            ->assertRedirect();

        $pass = GatePass::query()->first();
        $this->assertSame(GatePass::STATUS_PENDING, $pass->status);
        $this->assertSame('none', $pass->machine_status);
        $this->assertFalse(DeviceUser::query()->where('device_id', $gate->id)->where('employee_id', $arif->id)->exists());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $parent->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAsScoped($parent)
            ->post(route('gate-passes.approve', $pass))
            ->assertRedirect()
            ->assertSessionHas('success');

        $pass->refresh();
        $this->assertSame(GatePass::STATUS_APPROVED, $pass->status);
        $this->assertContains($pass->machine_status, ['ready', 'queued']);
        $this->assertTrue(DeviceUser::query()->where('device_id', $gate->id)->where('employee_id', $arif->id)->exists());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $arifUser->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_reject_keeps_machine_closed_and_notifies_with_reason(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $arifUser = $this->linkUser('manager', $arif);
        $parent = $this->linkUser('manager', $manish);
        $gate = $this->makeGate($org);

        $this->actingAsScoped($arifUser)->post(route('gate-passes.store'), $this->payload($arif->id));
        $pass = GatePass::query()->first();

        $this->actingAsScoped($parent)
            ->post(route('gate-passes.reject', $pass), ['decision_note' => 'Need the visit letter first.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $pass->refresh();
        $this->assertSame(GatePass::STATUS_REJECTED, $pass->status);
        $this->assertSame('revoked', $pass->machine_status);
        $this->assertFalse(DeviceUser::query()->where('device_id', $gate->id)->where('employee_id', $arif->id)->exists());
        $this->assertSame('Need the visit letter first.', $pass->decision_note);

        $notice = $arifUser->notifications()->latest()->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Need the visit letter first.', (string) ($notice->data['body'] ?? ''));
        $this->assertStringContainsString('OUT machine was not opened', (string) ($notice->data['body'] ?? ''));
    }

    public function test_personal_work_cuts_actual_away_and_keeps_early_return_minutes(): void
    {
        $day = $this->dayWithPass('personal', 60, '14:00:00', '14:59:00');
        $this->assertSame('present', $day->status);
        $this->assertSame(59, $day->minutes_away);
        $this->assertSame(59, $day->minutes_personal);
        $this->assertSame(0, $day->minutes_office);
        $this->assertSame(481, $day->minutes_worked);
    }

    public function test_office_work_counts_outing_as_working_hours(): void
    {
        $day = $this->dayWithPass('office', 60, '14:00:00', '15:00:00');
        $this->assertSame('present', $day->status);
        $this->assertSame(60, $day->minutes_away);
        $this->assertSame(60, $day->minutes_office);
        $this->assertSame(540, $day->minutes_worked);
    }

    public function test_expired_still_out_is_half_day_until_they_return(): void
    {
        [$employee, $pass] = $this->approvedPass('personal', 60, '14:00');
        $this->seedAttendance($employee, '09:00:00', null);
        $pass->update([
            'out_at' => Carbon::parse('2026-11-06 14:00:00', 'Asia/Kolkata'),
            'status' => GatePass::STATUS_EXPIRED,
            'expires_at' => Carbon::parse('2026-11-06 15:00:00', 'Asia/Kolkata'),
        ]);

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-06', 'Asia/Kolkata'));
        $this->assertSame('half_day', $day->status);
        $this->assertSame(300, $day->minutes_worked);

        $pass->update(['in_at' => Carbon::parse('2026-11-06 16:05:00', 'Asia/Kolkata')]);
        $day->update(['punch_out' => '18:00:00']);
        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-06', 'Asia/Kolkata'));
        $this->assertSame('present', $day->status);
        $this->assertSame(125, $day->minutes_away);
        $this->assertSame(415, $day->minutes_worked);
    }

    public function test_early_leave_without_return_is_half_day(): void
    {
        [$employee, $pass] = $this->approvedPass('personal', 60, '17:00', true);
        $this->seedAttendance($employee, '09:00:00', null);
        $pass->update(['out_at' => Carbon::parse('2026-11-06 17:00:00', 'Asia/Kolkata')]);

        $day = app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-06', 'Asia/Kolkata'));
        $this->assertSame('half_day', $day->status);
        $this->assertSame(480, $day->minutes_worked);
    }

    public function test_expire_command_revokes_machine_and_notifies_still_out(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $arifUser = $this->linkUser('manager', $arif);
        $parent = $this->linkUser('manager', $manish);
        $this->makeGate($org);

        $this->actingAsScoped($arifUser)->post(route('gate-passes.store'), $this->payload($arif->id));
        $pass = GatePass::query()->first();
        $this->actingAsScoped($parent)->post(route('gate-passes.approve', $pass));
        $pass->update([
            'out_at' => now()->subMinutes(90),
            'expires_at' => now()->subMinute(),
            'status' => GatePass::STATUS_APPROVED,
            'machine_status' => 'queued',
        ]);

        $this->artisan('gatepass:expire')->assertSuccessful();
        $pass->refresh();
        $this->assertSame(GatePass::STATUS_EXPIRED, $pass->status);
        $this->assertSame('revoked', $pass->machine_status);
        $this->assertFalse(DeviceUser::query()->where('employee_id', $arif->id)->exists());
        $this->assertTrue($arifUser->notifications()->get()->contains(fn ($row) => ($row->data['type'] ?? '') === 'gate_pass_still_out'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: \App\Models\Employee, 2: \App\Models\Employee, 3: \App\Models\Employee, 4: \App\Models\Employee}
     */
    private function tree(): array
    {
        $org = $this->makeCompany('GPW');
        $vipul = $this->makeEmployee($org, 'VIP');
        $dharmesh = $this->makeEmployee($org, 'DHA', $vipul->id);
        $manish = $this->makeEmployee($org, 'MAN', $dharmesh->id);
        $arif = $this->makeEmployee($org, 'ARF', $manish->id);

        return [$org, $vipul, $dharmesh, $manish, $arif];
    }

    private function linkUser(string $role, $employee): User
    {
        $user = $this->makeUser($role);
        $employee->update(['user_id' => $user->id]);

        return $user->fresh(['employee', 'roles']);
    }

    private function makeGate(array $org): Device
    {
        return Device::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Factory Gate OUT',
            'register_id' => 'gate-out',
            'gate_kind' => Device::KIND_GATE,
            'status' => 'unknown',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $employeeId, string $workType = 'personal'): array
    {
        return [
            'employee_id' => $employeeId,
            'date' => now()->toDateString(),
            'out_time' => '15:00',
            'leave_type' => 'return',
            'duration_time' => '01:00',
            'work_type' => $workType,
            'reason' => 'Client visit downtown',
        ];
    }

    private function dayWithPass(string $type, int $duration, string $outAt, string $inAt): AttendanceDay
    {
        [$employee, $pass] = $this->approvedPass($type, $duration, '14:00');
        $this->seedAttendance($employee, '09:00:00', '18:00:00');
        $pass->update([
            'out_at' => Carbon::parse('2026-11-06 '.$outAt, 'Asia/Kolkata'),
            'in_at' => Carbon::parse('2026-11-06 '.$inAt, 'Asia/Kolkata'),
        ]);

        return app(AttendanceCalculator::class)->recalculate($employee, Carbon::parse('2026-11-06', 'Asia/Kolkata'));
    }

    /**
     * @return array{0: \App\Models\Employee, 1: GatePass}
     */
    private function approvedPass(string $type, int $duration, string $outTime, bool $early = false): array
    {
        $org = $this->makeCompany('HRS'.substr($type, 0, 1).($early ? 'E' : 'N').$duration);
        $employee = $this->makeEmployee($org, 'EMP'.$duration.substr($type, 0, 1));
        $hr = $this->makeUser('hr-admin', $org['company']);
        $this->actingAsScoped($hr)->post(route('gate-passes.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-11-06',
            'out_time' => $outTime,
            'leave_type' => $early ? 'early_leave' : 'return',
            'duration_time' => sprintf('%02d:00', intdiv($duration, 60) ?: 1),
            'work_type' => $type,
            'reason' => 'Hour rule case',
        ])->assertRedirect();

        return [$employee->fresh(), GatePass::query()->where('employee_id', $employee->id)->first()];
    }

    private function seedAttendance($employee, string $in, ?string $out): void
    {
        AttendanceDay::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => '2026-11-06'],
            ['punch_in' => $in, 'punch_out' => $out, 'status' => 'present', 'source' => 'device']
        );
    }
}
