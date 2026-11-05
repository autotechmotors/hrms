<?php

namespace Tests\Feature;

use App\Models\DepartmentWorkHour;
use App\Models\EmployeeSalary;
use App\Models\EmployeeWorkHour;
use App\Models\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class HoursPayTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_department_hours_apply_until_a_person_sets_their_own(): void
    {
        $org = $this->makeCompany('HRS');
        $user = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'E1');

        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.departments.store', $org['department']), ['hours_per_day' => 8])
            ->assertOk()
            ->assertJsonPath('hours', '8 h');

        $this->assertDatabaseHas('department_work_hours', [
            'department_id' => $org['department']->id,
            'hours_per_day' => 8,
        ]);

        $this->actingAsScoped($user)
            ->get(route('hours-pay.show', $employee))
            ->assertOk()
            ->assertSee('uses Dept hours')
            ->assertSee('8 h');

        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.employees.hours', $employee), [
                'mode' => 'own',
                'hours_per_day' => 6,
            ])
            ->assertOk();

        $this->assertTrue(
            EmployeeWorkHour::query()->where('employee_id', $employee->id)->where('uses_department', false)->exists()
        );
        $this->assertSame('8.00', DepartmentWorkHour::query()->where('department_id', $org['department']->id)->value('hours_per_day'));
    }

    public function test_monthly_salary_is_stored_for_payroll(): void
    {
        $org = $this->makeCompany('PAY');
        $user = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'E2');
        $structure = SalaryStructure::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Staff',
            'code' => 'STAFF',
            'is_active' => true,
        ]);

        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.employees.salary', $employee), [
                'salary_structure_id' => $structure->id,
                'gross_monthly' => 25000,
                'effective_from' => '2026-11-01',
            ])
            ->assertOk();

        $this->assertTrue(
            EmployeeSalary::query()->where('employee_id', $employee->id)->where('gross_monthly', 25000)->exists()
        );
    }

    public function test_manager_cannot_open_hours_and_pay(): void
    {
        $org = $this->makeCompany('MGR');
        $user = $this->makeUser('manager', $org['company']);

        $this->actingAsScoped($user)->get(route('hours-pay.index'))->assertForbidden();
    }
}
