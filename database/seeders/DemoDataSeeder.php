<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\FinancialYear;
use App\Models\NumberSequence;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Models\WorkCalendar;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        AuditLogger::withoutAuditing(function (): void {
            $company = Company::query()->updateOrCreate(['code' => 'AT'], [
                'name' => 'Autotech',
                'legal_name' => 'Autotech',
                'email' => 'hr@autotech.test',
                'phone' => '07940000000',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'address' => 'SG Highway',
                'primary_color' => '#1d4ed8',
                'is_active' => true,
            ]);

            $branch = Branch::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'HO'], [
                'name' => 'Head Office',
                'city' => 'Ahmedabad',
                'is_active' => true,
            ]);

            $location = $branch->locations()->updateOrCreate(['company_id' => $company->id, 'code' => 'HO-01'], [
                'name' => 'Head Office Floor 1',
                'is_active' => true,
            ]);

            $hr = Department::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'HR'], ['name' => 'Human Resources', 'is_active' => true]);
            $ops = Department::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'OPS'], ['name' => 'Operations', 'is_active' => true]);
            $permanent = EmployeeType::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'PERM'], ['name' => 'Permanent', 'is_active' => true]);
            $managerTitle = Designation::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'MGR'], ['name' => 'Manager', 'employee_type_id' => $permanent->id, 'is_active' => true]);
            $executive = Designation::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'EXE'], ['name' => 'Executive', 'employee_type_id' => $permanent->id, 'is_active' => true]);
            $confirmed = EmploymentStatus::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'CONF'], ['name' => 'Confirmed', 'is_exit' => false, 'is_active' => true]);
            EmploymentStatus::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'PROB'], ['name' => 'Probation', 'is_exit' => false, 'is_active' => true]);
            EmploymentStatus::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'RES'], ['name' => 'Resigned', 'is_exit' => true, 'is_active' => true]);

            FinancialYear::query()->updateOrCreate(['company_id' => $company->id, 'name' => 'FY 2026-27'], [
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_current' => true,
            ]);

            $calendar = WorkCalendar::query()->updateOrCreate(['company_id' => $company->id, 'name' => 'Head office week'], [
                'branch_id' => null,
                'effective_from' => '2026-04-01',
                'is_active' => true,
            ]);
            foreach (range(0, 6) as $weekday) {
                $calendar->days()->updateOrCreate(['weekday' => $weekday], ['is_working' => $weekday >= 1 && $weekday <= 5]);
            }

            EmployeeCustomField::query()->updateOrCreate(['company_id' => $company->id, 'code' => 'BLOOD'], [
                'name' => 'Blood group',
                'field_type' => 'select',
                'options' => ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'],
                'is_required' => false,
                'sort_order' => 1,
                'is_active' => true,
            ]);

            $password = env('SEED_ADMIN_PASSWORD', 'Admin@12345');

            $admin = User::query()->updateOrCreate(['email' => 'admin@hrms.test'], [
                'name' => 'Super Admin',
                'password' => $password,
                'is_active' => true,
            ]);
            $admin->roles()->sync([Role::query()->where('slug', 'super-admin')->value('id')]);

            $hrUser = User::query()->updateOrCreate(['email' => 'hr@hrms.test'], [
                'name' => 'HR Admin',
                'password' => $password,
                'is_active' => true,
            ]);
            $hrUser->roles()->sync([Role::query()->where('slug', 'hr-admin')->value('id')]);
            UserScope::query()->updateOrCreate([
                'user_id' => $hrUser->id,
                'scope_type' => 'company',
                'scope_id' => $company->id,
            ]);

            $rahul = $this->employee($company->id, $branch->id, $location->id, $ops->id, $managerTitle->id, $permanent->id, $confirmed->id, null, 'AT0001', 'Rahul', 'Mehta', 'rahul@autotech.test');
            $this->employee($company->id, $branch->id, $location->id, $ops->id, $executive->id, $permanent->id, $confirmed->id, $rahul->id, 'AT0002', 'Anita', 'Desai', 'anita@autotech.test');
            $this->employee($company->id, $branch->id, $location->id, $ops->id, $executive->id, $permanent->id, $confirmed->id, $rahul->id, 'AT0003', 'Kiran', 'Patel', 'kiran@autotech.test');
            $this->employee($company->id, $branch->id, $location->id, $hr->id, $executive->id, $permanent->id, $confirmed->id, null, 'AT0004', 'Sonal', 'Joshi', 'sonal@autotech.test');

            $managerUser = User::query()->updateOrCreate(['email' => 'manager@hrms.test'], [
                'name' => 'Rahul Mehta',
                'password' => $password,
                'is_active' => true,
            ]);
            $managerUser->roles()->sync([Role::query()->where('slug', 'manager')->value('id')]);
            $rahul->update(['user_id' => $managerUser->id]);

            NumberSequence::query()->updateOrCreate(['company_id' => $company->id, 'sequence_key' => 'employee'], [
                'prefix' => 'AT',
                'padding' => 4,
                'next_number' => 5,
            ]);
        });
    }

    private function employee(int $companyId, int $branchId, int $locationId, int $departmentId, int $designationId, int $typeId, int $statusId, ?int $managerId, string $code, string $first, string $last, string $email): Employee
    {
        return Employee::query()->updateOrCreate(['company_id' => $companyId, 'employee_code' => $code], [
            'branch_id' => $branchId,
            'location_id' => $locationId,
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'employee_type_id' => $typeId,
            'employment_status_id' => $statusId,
            'reporting_manager_id' => $managerId,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'joining_date' => '2026-04-01',
            'probation_days' => 90,
            'notice_period_days' => 30,
        ]);
    }
}
