<?php

namespace Tests\Feature;

use App\Models\GatePass;
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
        $this->actingAsScoped($user->fresh())->get(route('payroll.index'))->assertForbidden();
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
        $this->actingAsScoped($user->fresh())->get(route('payroll.index'))->assertForbidden();
    }
}
