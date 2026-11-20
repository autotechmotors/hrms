<?php

namespace Tests\Feature;

use App\Models\GatePass;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class RoleLoginScopeTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_employee_login_sees_only_self_and_applies_for_a_gate_pass(): void
    {
        $org = $this->makeCompany('OWN');
        $parent = $this->makeEmployee($org, 'LEAD');
        $self = $this->makeEmployee($org, 'SELF', $parent->id);
        $other = $this->makeEmployee($org, 'OTHR');
        $user = $this->makeUser('employee', $org['company']);
        $self->update(['user_id' => $user->id, 'role_id' => $user->roles()->value('id')]);

        $this->actingAsScoped($user->fresh())
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('SELF')
            ->assertDontSee('OTHR')
            ->assertDontSee('Payroll');

        $this->actingAsScoped($user->fresh())->get(route('employees.show', $self))->assertOk();
        $this->actingAsScoped($user->fresh())->get(route('employees.show', $other))->assertNotFound();
        $this->actingAsScoped($user->fresh())->get(route('hours-pay.index'))->assertForbidden();
        $this->actingAsScoped($user->fresh())->get(route('devices.index'))->assertForbidden();

        $this->actingAsScoped($user->fresh())
            ->get(route('gate-passes.create'))
            ->assertOk()
            ->assertSee('SELF')
            ->assertDontSee('OTHR');

        $this->actingAsScoped($user->fresh())
            ->post(route('gate-passes.store'), [
                'employee_id' => $self->id,
                'date' => now()->toDateString(),
                'out_time' => '15:00',
                'leave_type' => 'return',
                'duration_time' => '01:00',
                'work_type' => 'personal',
                'reason' => 'Bank work',
            ])
            ->assertRedirect();

        $pass = GatePass::query()->where('employee_id', $self->id)->first();
        $this->assertNotNull($pass);
        $this->assertSame(GatePass::STATUS_PENDING, $pass->status);

        $this->actingAsScoped($user->fresh())
            ->post(route('gate-passes.store'), [
                'employee_id' => $other->id,
                'date' => now()->toDateString(),
                'out_time' => '15:00',
                'leave_type' => 'return',
                'duration_time' => '01:00',
                'work_type' => 'personal',
                'reason' => 'Should not apply',
            ])
            ->assertNotFound();
    }

    public function test_head_sees_the_reporting_tree_and_not_the_rest_of_the_company(): void
    {
        $org = $this->makeCompany('HED');
        $headEmployee = $this->makeEmployee($org, 'HEAD');
        $manager = $this->makeEmployee($org, 'MGR', $headEmployee->id);
        $staff = $this->makeEmployee($org, 'STAF', $manager->id);
        $other = $this->makeEmployee($org, 'OTHR');
        $user = $this->makeUser('head');
        $headEmployee->update(['user_id' => $user->id]);

        $this->actingAsScoped($user->fresh())->get(route('employees.show', $staff))->assertOk();
        $this->actingAsScoped($user->fresh())->get(route('employees.show', $manager))->assertOk();
        $this->actingAsScoped($user->fresh())->get(route('employees.show', $other))->assertNotFound();
        $this->actingAsScoped($user->fresh())->get(route('hours-pay.index'))->assertForbidden();
    }

    public function test_every_parent_above_a_manager_sees_the_people_under_that_manager(): void
    {
        $org = $this->makeCompany('TREE');
        $director = $this->makeEmployee($org, 'DIR');
        $head = $this->makeEmployee($org, 'HED', $director->id);
        $manager = $this->makeEmployee($org, 'MGR', $head->id);
        $worker = $this->makeEmployee($org, 'WRK', $manager->id);
        $helperPerson = $this->makeEmployee($org, 'HLP', $manager->id);
        $outsider = $this->makeEmployee($org, 'OUT');

        $helperRole = Role::query()->create([
            'name' => 'Helper',
            'slug' => 'helper',
            'is_system' => false,
            'requires_mfa' => false,
            'has_parent' => true,
            'has_child' => false,
            'is_active' => true,
        ]);
        $helperRole->permissions()->sync(
            Permission::query()->whereIn('slug', [
                'dashboard.view', 'employees.view', 'attendance.view', 'leave.view', 'holidays.view',
                'gate_passes.view', 'gate_passes.create',
            ])->pluck('id')
        );

        $directorUser = $this->makeUser('director', $org['company']);
        $headUser = $this->makeUser('head', $org['company']);
        $managerUser = $this->makeUser('manager', $org['company']);
        $workerUser = $this->makeUser('employee', $org['company']);
        $helperUser = $this->makeUser('employee', $org['company']);
        $helperUser->roles()->sync([$helperRole->id]);

        $director->update(['user_id' => $directorUser->id]);
        $head->update(['user_id' => $headUser->id]);
        $manager->update(['user_id' => $managerUser->id]);
        $worker->update(['user_id' => $workerUser->id]);
        $helperPerson->update(['user_id' => $helperUser->id, 'role_id' => $helperRole->id]);

        $this->actingAsScoped($directorUser->fresh())->get(route('employees.index'))
            ->assertOk()
            ->assertSee('HED')
            ->assertSee('MGR')
            ->assertSee('WRK')
            ->assertSee('HLP')
            ->assertDontSee('OUT');
        $this->actingAsScoped($headUser->fresh())->get(route('employees.index'))
            ->assertOk()
            ->assertSee('WRK')
            ->assertSee('HLP')
            ->assertDontSee('OUT')
            ->assertDontSee('DIR');
        $this->actingAsScoped($managerUser->fresh())->get(route('employees.index'))
            ->assertOk()
            ->assertSee('WRK')
            ->assertSee('HLP')
            ->assertDontSee('OUT')
            ->assertDontSee('DIR');
        $this->actingAsScoped($workerUser->fresh())->get(route('employees.index'))
            ->assertOk()
            ->assertSee('WRK')
            ->assertDontSee('HLP')
            ->assertDontSee('MGR');
        $this->actingAsScoped($helperUser->fresh())->get(route('employees.show', $worker))->assertNotFound();
        $this->actingAsScoped($directorUser->fresh())->get(route('employees.show', $outsider))->assertNotFound();
        $this->actingAsScoped($directorUser->fresh())->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('HLP')
            ->assertDontSee('OUT');

        $type = LeaveType::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Casual',
            'code' => 'CL',
            'is_paid' => true,
            'annual_days' => 0,
            'is_active' => true,
        ]);
        $this->actingAsScoped($helperUser->fresh())->postJson(route('leave.store'), [
            'employee_id' => $helperPerson->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-20',
            'reason' => 'Home work',
        ])->assertOk();

        $leave = LeaveRequest::query()->where('employee_id', $helperPerson->id)->first();
        $this->actingAsScoped($directorUser->fresh())->postJson(route('leave.decide', $leave), [
            'decision' => 'rejected',
            'decision_note' => 'Need the helper on the line',
        ])->assertOk();
        $this->assertSame('rejected', $leave->fresh()->status);
    }
}
