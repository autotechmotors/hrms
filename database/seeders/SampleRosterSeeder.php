<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;

class SampleRosterSeeder extends Seeder
{
    public function run(): void
    {
        AuditLogger::withoutAuditing(function (): void {
            $company = Company::query()->where('code', 'AT')->first()
                ?? Company::query()->orderBy('id')->first();

            if (! $company) {
                $this->command?->warn('No company found. Sample roster was not added.');

                return;
            }

            $permanent = EmployeeType::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'PERM'],
                ['name' => 'Permanent', 'is_active' => true]
            );
            $confirmed = EmploymentStatus::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'CONF'],
                ['name' => 'Confirmed', 'is_exit' => false, 'is_active' => true]
            );

            $departments = [];
            foreach ($this->departments() as $code => $name) {
                $departments[$code] = Department::query()->updateOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    ['name' => $name, 'is_active' => true]
                );
            }

            $designations = [];
            foreach ($this->designations() as $code => $name) {
                $designations[$code] = Designation::query()->updateOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    ['name' => $name, 'employee_type_id' => $permanent->id, 'is_active' => true]
                );
            }

            $roles = Role::query()->whereIn('slug', [
                'hr-admin', 'director', 'head', 'manager', 'employee', 'accounts', 'payroll-admin',
            ])->get()->keyBy('slug');

            $people = [];
            foreach ($this->people() as $row) {
                $people[$row['code']] = Employee::query()->updateOrCreate(
                    ['company_id' => $company->id, 'employee_code' => $row['code']],
                    [
                        'department_id' => $departments[$row['department']]->id,
                        'designation_id' => $designations[$row['designation']]->id,
                        'employee_type_id' => $permanent->id,
                        'employment_status_id' => $confirmed->id,
                        'role_id' => $row['role'] ? $roles[$row['role']]->id : null,
                        'first_name' => $row['first'],
                        'last_name' => $row['last'],
                        'email' => strtolower($row['code']).'@autotech.test',
                        'phone' => '980000'.substr($row['code'], -2),
                        'joining_date' => '2026-04-01',
                        'probation_days' => 90,
                        'notice_period_days' => 30,
                    ]
                );
            }

            foreach ($this->people() as $row) {
                $people[$row['code']]->update([
                    'reporting_manager_id' => $row['parent'] ? $people[$row['parent']]->id : null,
                ]);
            }

            $this->command?->info('Sample roster ready: 10 departments, 12 designations, 13 employees (CHK01–CHK13).');
        });
    }

    /**
     * @return array<string, string>
     */
    private function departments(): array
    {
        return [
            'HR' => 'Human Resources',
            'OPS' => 'Operations',
            'FIN' => 'Finance',
            'SAL' => 'Sales',
            'PRD' => 'Production',
            'QLT' => 'Quality',
            'ACC' => 'Accounts',
            'STR' => 'Stores',
            'ADM' => 'Administration',
            'IT' => 'Information Technology',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function designations(): array
    {
        return [
            'DIR' => 'Director',
            'HED' => 'Head',
            'MGR' => 'Manager',
            'EXE' => 'Executive',
            'HRO' => 'HR Officer',
            'SUP' => 'Supervisor',
            'ENG' => 'Engineer',
            'TEC' => 'Technician',
            'OFC' => 'Officer',
            'AST' => 'Assistant',
            'CLK' => 'Clerk',
            'OPR' => 'Operator',
        ];
    }

    /**
     * @return list<array{code: string, first: string, last: string, department: string, designation: string, role: ?string, parent: ?string}>
     */
    private function people(): array
    {
        return [
            ['code' => 'CHK01', 'first' => 'Nisha', 'last' => 'Shah', 'department' => 'HR', 'designation' => 'HRO', 'role' => 'hr-admin', 'parent' => null],
            ['code' => 'CHK02', 'first' => 'Amit', 'last' => 'Desai', 'department' => 'OPS', 'designation' => 'DIR', 'role' => 'director', 'parent' => 'CHK01'],
            ['code' => 'CHK13', 'first' => 'Mehul', 'last' => 'Patel', 'department' => 'OPS', 'designation' => 'HED', 'role' => 'head', 'parent' => 'CHK02'],
            ['code' => 'CHK03', 'first' => 'Priya', 'last' => 'Patel', 'department' => 'OPS', 'designation' => 'MGR', 'role' => 'manager', 'parent' => 'CHK13'],
            ['code' => 'CHK04', 'first' => 'Ravi', 'last' => 'Shah', 'department' => 'FIN', 'designation' => 'MGR', 'role' => 'manager', 'parent' => 'CHK13'],
            ['code' => 'CHK05', 'first' => 'Kiran', 'last' => 'Joshi', 'department' => 'SAL', 'designation' => 'MGR', 'role' => 'manager', 'parent' => 'CHK13'],
            ['code' => 'CHK06', 'first' => 'Neha', 'last' => 'Trivedi', 'department' => 'PRD', 'designation' => 'SUP', 'role' => 'employee', 'parent' => 'CHK03'],
            ['code' => 'CHK07', 'first' => 'Hardik', 'last' => 'Mehta', 'department' => 'PRD', 'designation' => 'ENG', 'role' => 'employee', 'parent' => 'CHK03'],
            ['code' => 'CHK08', 'first' => 'Sonal', 'last' => 'Parmar', 'department' => 'QLT', 'designation' => 'OFC', 'role' => 'employee', 'parent' => 'CHK04'],
            ['code' => 'CHK09', 'first' => 'Jay', 'last' => 'Modi', 'department' => 'ACC', 'designation' => 'AST', 'role' => 'accounts', 'parent' => 'CHK04'],
            ['code' => 'CHK10', 'first' => 'Pooja', 'last' => 'Dave', 'department' => 'STR', 'designation' => 'CLK', 'role' => 'employee', 'parent' => 'CHK05'],
            ['code' => 'CHK11', 'first' => 'Manish', 'last' => 'Rao', 'department' => 'IT', 'designation' => 'TEC', 'role' => 'employee', 'parent' => 'CHK05'],
            ['code' => 'CHK12', 'first' => 'Bhavna', 'last' => 'Patel', 'department' => 'ADM', 'designation' => 'OPR', 'role' => 'payroll-admin', 'parent' => 'CHK01'],
        ];
    }
}
