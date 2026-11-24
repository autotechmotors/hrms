<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SalarySheet;
use App\Models\SalarySheetLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class SeparationAccessTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_manager_cannot_open_attendance_or_shifts_outside_the_tree(): void
    {
        $org = $this->makeCompany('SEP');
        $managerEmployee = $this->makeEmployee($org, 'MGR');
        $report = $this->makeEmployee($org, 'REP', $managerEmployee->id);
        $other = $this->makeEmployee($org, 'OUT');
        $user = $this->makeUser('manager');
        $managerEmployee->update(['user_id' => $user->id]);

        $this->actingAsScoped($user->fresh())->get(route('attendance.show', $report))->assertOk();
        $this->actingAsScoped($user->fresh())->get(route('attendance.show', $other))->assertNotFound();
        $this->actingAsScoped($user->fresh())->get(route('employees.shifts.show', $report))->assertOk();
        $this->actingAsScoped($user->fresh())->get(route('employees.shifts.show', $other))->assertNotFound();
        $this->actingAsScoped($user->fresh())->get(route('departments.index'))->assertForbidden();
    }

    public function test_accounts_cannot_change_net_pay(): void
    {
        $org = $this->makeCompany('ACC');
        $accounts = $this->makeUser('accounts', $org['company']);
        $employee = $this->makeEmployee($org, 'A1');
        $sheet = SalarySheet::query()->create([
            'company_id' => $org['company']->id,
            'title' => 'Week',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-07',
            'status' => 'pending',
        ]);
        $line = SalarySheetLine::query()->create([
            'salary_sheet_id' => $sheet->id,
            'employee_id' => $employee->id,
            'earned' => 10000,
            'advance' => 0,
            'deductions' => 0,
            'net' => 10000,
            'days' => [],
            'breakdown' => ['computed_net' => 10000],
        ]);

        $this->actingAsScoped($accounts)
            ->postJson(route('salary-sheets.line.net', [$sheet, $line]), [
                'net' => 11000,
                'reason' => 'Should not work for accounts',
            ])
            ->assertForbidden();
    }

    public function test_department_leave_type_is_enforced(): void
    {
        $org = $this->makeCompany('DLT');
        $otherDept = \App\Models\Department::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Other',
            'code' => 'OTH',
            'is_active' => true,
        ]);
        $employee = $this->makeEmployee($org, 'E1');
        $user = $this->makeUser('employee', $org['company']);
        $employee->update(['user_id' => $user->id]);
        $type = LeaveType::query()->create([
            'company_id' => $org['company']->id,
            'department_id' => $otherDept->id,
            'name' => 'Other dept',
            'code' => 'OD',
            'is_paid' => false,
            'annual_days' => 0,
            'is_active' => true,
        ]);

        $this->actingAsScoped($user->fresh())
            ->postJson(route('leave.store'), [
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'starts_on' => '2026-11-25',
                'ends_on' => '2026-11-25',
                'reason' => 'Wrong department type',
            ])
            ->assertStatus(422);
    }

    public function test_hr_cannot_remove_the_last_super_admin_role(): void
    {
        $org = $this->makeCompany('SAD');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $super = $this->makeUser('super-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'SUP');
        $employee->update([
            'user_id' => $super->id,
            'role_id' => Role::query()->where('slug', 'super-admin')->value('id'),
            'email' => $super->email,
        ]);
        $hrRole = Role::query()->where('slug', 'hr-admin')->value('id');

        $this->actingAsScoped($hr)
            ->put(route('employees.update', $employee), [
                'department_id' => $org['department']->id,
                'designation_id' => $org['designation']->id,
                'employee_type_id' => $org['type']->id,
                'employment_status_id' => $org['status']->id,
                'role_id' => $hrRole,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $super->email,
                'joining_date' => $employee->joining_date?->toDateString() ?? now()->toDateString(),
                'salary_type' => 'hours',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertTrue($super->fresh()->isSuperAdmin());
    }
}
