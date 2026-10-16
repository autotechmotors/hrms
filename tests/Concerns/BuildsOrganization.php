<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use Database\Seeders\RoleAndPermissionSeeder;

trait BuildsOrganization
{
    protected function seedAccess(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function makeCompany(string $code): array
    {
        $company = Company::query()->create([
            'name' => $code.' Company',
            'code' => $code,
            'is_active' => true,
        ]);
        $branch = Branch::query()->create(['company_id' => $company->id, 'name' => 'Branch', 'code' => 'BR', 'is_active' => true]);
        $department = Department::query()->create(['company_id' => $company->id, 'name' => 'Dept', 'code' => 'DEP', 'is_active' => true]);
        $type = EmployeeType::query()->create(['company_id' => $company->id, 'name' => 'Permanent', 'code' => 'PERM', 'is_active' => true]);
        $designation = Designation::query()->create(['company_id' => $company->id, 'name' => 'Role', 'code' => 'DES', 'employee_type_id' => $type->id, 'is_active' => true]);
        $status = EmploymentStatus::query()->create(['company_id' => $company->id, 'name' => 'Confirmed', 'code' => 'CONF', 'is_exit' => false, 'is_active' => true]);

        return compact('company', 'branch', 'department', 'designation', 'type', 'status');
    }

    protected function makeEmployee(array $org, string $code, ?int $managerId = null): Employee
    {
        return Employee::query()->create([
            'company_id' => $org['company']->id,
            'branch_id' => $org['branch']->id,
            'department_id' => $org['department']->id,
            'designation_id' => $org['designation']->id,
            'employee_type_id' => $org['type']->id,
            'employment_status_id' => $org['status']->id,
            'reporting_manager_id' => $managerId,
            'employee_code' => $code,
            'first_name' => 'Test',
            'last_name' => $code,
            'joining_date' => '2026-04-01',
        ]);
    }

    protected function makeUser(string $roleSlug, ?Company $company = null): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::query()->where('slug', $roleSlug)->value('id')]);
        if ($company) {
            UserScope::query()->create([
                'user_id' => $user->id,
                'scope_type' => 'company',
                'scope_id' => $company->id,
            ]);
        }

        return $user->refresh();
    }

    protected function actingAsScoped(User $user): static
    {
        return $this->actingAs($user)->withSession(['mfa_passed' => true]);
    }
}
