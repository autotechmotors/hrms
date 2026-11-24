<?php

namespace Tests\Feature;

use App\Models\EmployeeSalary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class SalaryTypeSheetTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_generate_requires_hours_type_and_monthly_amount(): void
    {
        $org = $this->makeCompany('STY');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $ready = $this->makeEmployee($org, 'RDY');
        $piece = $this->makeEmployee($org, 'PCE');
        $piece->update(['salary_type' => 'piece']);
        $noPay = $this->makeEmployee($org, 'NOP');
        EmployeeSalary::query()->create([
            'employee_id' => $ready->id,
            'gross_monthly' => 20000,
            'effective_from' => '2026-11-01',
        ]);

        $this->actingAsScoped($hr)
            ->post(route('salary-sheets.store'), [
                'starts_on' => '2026-11-01',
                'ends_on' => '2026-11-07',
                'department_id' => $org['department']->id,
                'employee_ids' => [$piece->id],
            ])
            ->assertSessionHasErrors('employee_ids');

        $this->actingAsScoped($hr)
            ->post(route('salary-sheets.store'), [
                'starts_on' => '2026-11-01',
                'ends_on' => '2026-11-07',
                'department_id' => $org['department']->id,
                'employee_ids' => [$noPay->id],
            ])
            ->assertSessionHasErrors('employee_ids');

        $this->actingAsScoped($hr)
            ->post(route('salary-sheets.store'), [
                'starts_on' => '2026-11-01',
                'ends_on' => '2026-11-07',
                'department_id' => $org['department']->id,
                'employee_ids' => [$ready->id],
            ])
            ->assertRedirect();
    }

    public function test_piece_based_person_cannot_save_monthly_salary(): void
    {
        $org = $this->makeCompany('PCS');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'PC1');
        $employee->update(['salary_type' => 'piece']);

        $this->actingAsScoped($hr)
            ->postJson(route('hours-pay.employees.salary', $employee), [
                'gross_monthly' => 15000,
                'effective_from' => '2026-11-01',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('employee_salaries', ['employee_id' => $employee->id]);
    }

    public function test_employee_form_requires_salary_type(): void
    {
        $org = $this->makeCompany('FRM');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->post(route('employees.store'), [
                'department_id' => $org['department']->id,
                'designation_id' => $org['designation']->id,
                'employee_type_id' => $org['type']->id,
                'employment_status_id' => $org['status']->id,
                'first_name' => 'Kiran',
                'last_name' => 'Shah',
                'joining_date' => '2026-04-01',
            ])
            ->assertSessionHasErrors('salary_type');
    }
}
