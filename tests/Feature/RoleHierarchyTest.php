<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_manager_role_is_seeded_with_parent_and_child_flags(): void
    {
        $manager = Role::query()->where('slug', 'manager')->firstOrFail();
        $hr = Role::query()->where('slug', 'hr-admin')->firstOrFail();

        $this->assertTrue($manager->has_parent);
        $this->assertTrue($manager->has_child);
        $this->assertTrue($manager->is_active);
        $this->assertFalse($hr->has_parent);
        $this->assertTrue($hr->has_child);
    }

    public function test_role_form_saves_hierarchy_flags(): void
    {
        $admin = $this->makeUser('super-admin');
        $role = Role::query()->where('slug', 'manager')->firstOrFail();

        $this->actingAsScoped($admin)
            ->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee('It has parent')
            ->assertSee('It has child')
            ->assertSee('employee form ma Parent select required');

        $this->actingAsScoped($admin)
            ->put(route('roles.update', $role), [
                'name' => 'Manager',
                'slug' => 'manager',
                'has_parent' => '1',
                'has_child' => '0',
                'is_active' => '1',
                'requires_mfa' => '0',
            ])
            ->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertTrue($role->has_parent);
        $this->assertFalse($role->has_child);
    }

    public function test_has_child_role_makes_employee_a_supervisor(): void
    {
        $org = $this->makeCompany('RL1');
        $employee = $this->makeEmployee($org, 'MGR1');
        $user = $this->makeUser('manager', $org['company']);
        $employee->update(['user_id' => $user->id]);

        $this->assertTrue($employee->fresh()->canBeReportingManager());
        $this->assertTrue($employee->fresh()->isSupervisor());
        $this->assertTrue($employee->fresh()->needsReportingManager());
    }

    public function test_employee_with_parent_role_must_select_a_manager_from_child_roles(): void
    {
        $org = $this->makeCompany('RL2');
        $shop = $this->makeEmployee($org, 'SHOP');
        $lead = $this->makeEmployee($org, 'LEAD');
        $member = $this->makeEmployee($org, 'MEMB');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $leadUser = $this->makeUser('director', $org['company']);
        $memberUser = $this->makeUser('manager', $org['company']);
        $lead->update(['user_id' => $leadUser->id]);
        $member->update(['user_id' => $memberUser->id]);

        $this->actingAsScoped($hr)
            ->get(route('employees.edit', $member))
            ->assertOk()
            ->assertSee('LEAD')
            ->assertDontSee('SHOP')
            ->assertSee('role has a parent');

        $payload = [
            'department_id' => $org['department']->id,
            'designation_id' => $org['designation']->id,
            'employee_type_id' => $org['type']->id,
            'employment_status_id' => $org['status']->id,
            'first_name' => 'Test',
            'last_name' => 'MEMB',
            'joining_date' => '2026-04-01',
        ];

        $this->actingAsScoped($hr)
            ->put(route('employees.update', $member), $payload)
            ->assertSessionHasErrors('reporting_manager_id');

        $this->actingAsScoped($hr)
            ->put(route('employees.update', $member), $payload + [
                'reporting_manager_id' => $lead->id,
                'movement_reason' => 'Assign parent required by role',
            ])
            ->assertRedirect();

        $this->assertSame($lead->id, $member->fresh()->reporting_manager_id);
        $this->assertInstanceOf(Employee::class, $shop);
    }

    public function test_create_form_lists_roles_and_stores_the_chosen_role(): void
    {
        $org = $this->makeCompany('RL3');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $hrRole = Role::query()->where('slug', 'hr-admin')->firstOrFail();
        $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();

        $this->actingAsScoped($hr)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('name="role_id"', false)
            ->assertSee('HR Admin')
            ->assertSee('Manager')
            ->assertSee('can be a parent');

        $base = [
            'department_id' => $org['department']->id,
            'designation_id' => $org['designation']->id,
            'employee_type_id' => $org['type']->id,
            'employment_status_id' => $org['status']->id,
            'first_name' => 'Lead',
            'last_name' => 'Parent',
            'joining_date' => '2026-04-01',
            'role_id' => $hrRole->id,
        ];

        $this->actingAsScoped($hr)
            ->post(route('employees.store'), $base)
            ->assertRedirect();

        $lead = Employee::query()->where('employee_code', '!=', '')->where('first_name', 'Lead')->firstOrFail();
        $this->assertSame($hrRole->id, $lead->role_id);
        $this->assertTrue($lead->canBeReportingManager());

        $this->actingAsScoped($hr)
            ->post(route('employees.store'), [
                'department_id' => $org['department']->id,
                'designation_id' => $org['designation']->id,
                'employee_type_id' => $org['type']->id,
                'employment_status_id' => $org['status']->id,
                'first_name' => 'New',
                'last_name' => 'Manager',
                'joining_date' => '2026-04-02',
                'role_id' => $managerRole->id,
            ])
            ->assertSessionHasErrors('reporting_manager_id');

        $this->actingAsScoped($hr)
            ->post(route('employees.store'), [
                'department_id' => $org['department']->id,
                'designation_id' => $org['designation']->id,
                'employee_type_id' => $org['type']->id,
                'employment_status_id' => $org['status']->id,
                'first_name' => 'New',
                'last_name' => 'Manager',
                'joining_date' => '2026-04-02',
                'role_id' => $managerRole->id,
                'reporting_manager_id' => $lead->id,
            ])
            ->assertRedirect();

        $saved = Employee::query()->where('last_name', 'Manager')->firstOrFail();
        $this->assertSame($managerRole->id, $saved->role_id);
        $this->assertSame($lead->id, $saved->reporting_manager_id);

        $this->actingAsScoped($hr)
            ->get(route('employees.create'))
            ->assertSee('Lead Parent')
            ->assertSee('HR Admin');
    }

    public function test_users_module_is_removed_and_employee_role_creates_staff_login(): void
    {
        $org = $this->makeCompany('RL4');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();
        $lead = $this->makeEmployee($org, 'LEAD4');
        $lead->update(['role_id' => Role::query()->where('slug', 'hr-admin')->value('id')]);

        $this->actingAsScoped($hr)
            ->get('/users')
            ->assertRedirect('/employees');

        $this->actingAsScoped($hr)
            ->get(route('more'))
            ->assertOk()
            ->assertDontSee('User Management');

        $this->actingAsScoped($hr)
            ->post(route('employees.store'), [
                'department_id' => $org['department']->id,
                'designation_id' => $org['designation']->id,
                'employee_type_id' => $org['type']->id,
                'employment_status_id' => $org['status']->id,
                'first_name' => 'Kajal',
                'last_name' => 'Login',
                'email' => 'kajal.login@autotechmotors.com',
                'joining_date' => '2026-04-01',
                'role_id' => $managerRole->id,
                'reporting_manager_id' => $lead->id,
            ])
            ->assertRedirect();

        $saved = Employee::query()->where('email', 'kajal.login@autotechmotors.com')->firstOrFail();
        $this->assertSame($managerRole->id, $saved->role_id);
        $this->assertNotNull($saved->user);
        $this->assertTrue($saved->user->hasRole('manager'));
        $this->assertSame('kajal.login@autotechmotors.com', $saved->user->email);
    }
}
