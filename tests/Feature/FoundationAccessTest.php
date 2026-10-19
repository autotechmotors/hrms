<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class FoundationAccessTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_manager_cannot_open_company_master(): void
    {
        $user = $this->makeUser('manager');

        $this->actingAsScoped($user)->get(route('companies.index'))->assertForbidden();
    }

    public function test_company_scope_hides_another_company_employee(): void
    {
        $first = $this->makeCompany('AAA');
        $second = $this->makeCompany('BBB');
        $hidden = $this->makeEmployee($second, 'E1');
        $hr = $this->makeUser('hr-admin', $first['company']);

        $this->actingAsScoped($hr)->get(route('employees.show', $hidden))->assertNotFound();
        $this->actingAsScoped($hr)->get(route('departments.index'))->assertOk()->assertDontSee('BBB');
    }

    public function test_manager_sees_direct_reports_only(): void
    {
        $org = $this->makeCompany('MMM');
        $managerEmployee = $this->makeEmployee($org, 'MGR');
        $report = $this->makeEmployee($org, 'REP', $managerEmployee->id);
        $other = $this->makeEmployee($org, 'OTH');

        $user = $this->makeUser('manager');
        $managerEmployee->update(['user_id' => $user->id]);
        $user->unsetRelation('employee');

        $this->actingAsScoped($user->fresh())->get(route('employees.show', $report))->assertOk();
        $this->actingAsScoped($user->fresh())->get(route('employees.show', $other))->assertNotFound();
    }

    public function test_employee_code_is_auto_generated_and_locked(): void
    {
        $org = $this->makeCompany('AT');
        $org['company']->update(['name' => 'Autotech']);
        $hr = $this->makeUser('hr-admin', $org['company']);
        $payload = [
            'company_id' => $org['company']->id,
            'branch_id' => $org['branch']->id,
            'department_id' => $org['department']->id,
            'designation_id' => $org['designation']->id,
            'employee_type_id' => $org['type']->id,
            'employment_status_id' => $org['status']->id,
            'first_name' => 'Asha',
            'last_name' => 'Shah',
            'joining_date' => '2026-04-01',
        ];

        $this->actingAsScoped($hr)->post(route('employees.store'), $payload)->assertRedirect();
        $this->actingAsScoped($hr)->post(route('employees.store'), array_merge($payload, [
            'first_name' => 'Ravi',
            'last_name' => 'Patel',
        ]))->assertRedirect();

        $codes = Employee::query()->where('company_id', $org['company']->id)->orderBy('id')->pluck('employee_code')->all();
        $this->assertSame(['AT0001', 'AT0002'], $codes);
        $this->assertTrue(collect($codes)->every(fn (string $code) => strlen($code) === 6));

        $employee = Employee::query()->where('employee_code', 'AT0001')->firstOrFail();
        $this->actingAsScoped($hr)->put(route('employees.update', $employee), array_merge($payload, [
            'employee_code' => 'HACK01',
            'first_name' => 'Asha',
            'movement_reason' => null,
        ]))->assertRedirect();

        $this->assertSame('AT0001', $employee->fresh()->employee_code);
    }

    public function test_super_admin_can_see_every_company(): void
    {
        $first = $this->makeCompany('SA1');
        $second = $this->makeCompany('SA2');
        $admin = $this->makeUser('super-admin');

        $this->actingAsScoped($admin)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('SA1')
            ->assertSee('SA2');

        $this->assertInstanceOf(User::class, $admin);
        $this->assertNotNull($first['company']->id);
        $this->assertNotNull($second['company']->id);
    }
}
