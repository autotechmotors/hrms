<?php

namespace Tests\Feature;

use App\Models\GatePass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class GatePassWorkflowTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_hr_creating_for_employee_auto_approves(): void
    {
        $org = $this->makeCompany('GP1');
        $employee = $this->makeEmployee($org, 'E1');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->post(route('gate-passes.store'), $this->payload($employee->id))
            ->assertRedirect();

        $pass = GatePass::query()->first();
        $this->assertNotNull($pass);
        $this->assertSame(GatePass::STATUS_APPROVED, $pass->status);
        $this->assertFalse($pass->is_early_leave);
        $this->assertSame(GatePass::WORK_PERSONAL, $pass->work_type);
        $this->assertSame($hr->id, $pass->decided_by);
    }

    public function test_manager_creating_for_child_auto_approves_and_self_stays_pending(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $manager = $this->linkUser('manager', $manish);
        $head = $this->linkUser('director', $dharmesh);

        $this->actingAsScoped($manager)
            ->post(route('gate-passes.store'), $this->payload($arif->id))
            ->assertRedirect();

        $this->assertSame(GatePass::STATUS_APPROVED, GatePass::query()->value('status'));

        $this->actingAsScoped($manager)
            ->post(route('gate-passes.store'), $this->payload($manish->id))
            ->assertRedirect();

        $self = GatePass::query()->where('employee_id', $manish->id)->first();
        $this->assertSame(GatePass::STATUS_PENDING, $self->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $head->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_full_ancestor_chain_can_approve_and_self_cannot(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $arifUser = $this->linkUser('manager', $arif);
        $manishUser = $this->linkUser('manager', $manish);
        $dharmeshUser = $this->linkUser('director', $dharmesh);
        $vipulUser = $this->linkUser('director', $vipul);

        $this->actingAsScoped($arifUser)
            ->post(route('gate-passes.store'), $this->payload($arif->id))
            ->assertRedirect();

        $pass = GatePass::query()->first();
        $this->assertSame(GatePass::STATUS_PENDING, $pass->status);

        $this->actingAsScoped($arifUser)
            ->post(route('gate-passes.approve', $pass))
            ->assertSessionHasErrors('status');

        $this->actingAsScoped($vipulUser)
            ->post(route('gate-passes.approve', $pass))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(GatePass::STATUS_APPROVED, $pass->fresh()->status);
        $this->assertTrue($manishUser->canDecideGatePassFor($arif));
        $this->assertTrue($dharmeshUser->canDecideGatePassFor($arif));
    }

    public function test_office_work_type_is_saved_and_shown(): void
    {
        $org = $this->makeCompany('GPW');
        $employee = $this->makeEmployee($org, 'EW1');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->post(route('gate-passes.store'), $this->payload($employee->id, 'office'))
            ->assertRedirect();

        $pass = GatePass::query()->first();
        $this->assertSame(GatePass::WORK_OFFICE, $pass->work_type);
        $this->assertSame('Office Work', $pass->work_type_label);

        $this->actingAsScoped($hr)
            ->get(route('gate-passes.show', $pass))
            ->assertOk()
            ->assertSee('Office Work');
    }

    public function test_early_leave_hides_max_duration_and_sets_day_end(): void
    {
        $org = $this->makeCompany('GP2');
        $employee = $this->makeEmployee($org, 'E2');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->post(route('gate-passes.store'), [
                'employee_id' => $employee->id,
                'date' => now()->toDateString(),
                'out_time' => '18:00',
                'leave_type' => 'early_leave',
                'work_type' => 'personal',
                'reason' => 'Doctor visit, not returning',
            ])
            ->assertRedirect();

        $pass = GatePass::query()->first();
        $this->assertTrue($pass->is_early_leave);
        $this->assertSame('Early Leave', $pass->duration_label);
        $this->assertSame(now()->toDateString().' 23:59:00', $pass->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_expire_command_marks_stale_passes_every_run(): void
    {
        $org = $this->makeCompany('GPX');
        $employee = $this->makeEmployee($org, 'EX1');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)->post(route('gate-passes.store'), $this->payload($employee->id));
        $pass = GatePass::query()->first();
        $pass->update(['expires_at' => now()->subMinute(), 'status' => GatePass::STATUS_APPROVED]);

        $this->artisan('gatepass:expire')->assertSuccessful();
        $this->assertSame(GatePass::STATUS_EXPIRED, $pass->fresh()->status);
    }

    public function test_pending_duplicate_is_blocked(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $arifUser = $this->linkUser('manager', $arif);

        $this->actingAsScoped($arifUser)
            ->post(route('gate-passes.store'), $this->payload($arif->id))
            ->assertRedirect();

        $this->actingAsScoped($arifUser)
            ->post(route('gate-passes.store'), $this->payload($arif->id))
            ->assertSessionHasErrors('date');
    }

    public function test_manager_dashboard_today_pending_excludes_self(): void
    {
        [$org, $vipul, $dharmesh, $manish, $arif] = $this->tree();
        $manishUser = $this->linkUser('manager', $manish);
        $arifUser = $this->linkUser('manager', $arif);

        $this->actingAsScoped($arifUser)->post(route('gate-passes.store'), $this->payload($arif->id));
        $this->actingAsScoped($manishUser)->post(route('gate-passes.store'), $this->payload($manish->id));

        $this->actingAsScoped($manishUser)
            ->get(route('gate-passes.index'))
            ->assertOk()
            ->assertSee($arif->employee_code)
            ->assertSee("Today's pending passes")
            ->assertDontSee('No pending Gate Pass from people under you today.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: \App\Models\Employee, 2: \App\Models\Employee, 3: \App\Models\Employee, 4: \App\Models\Employee}
     */
    private function tree(): array
    {
        $org = $this->makeCompany('TREE');
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
}
