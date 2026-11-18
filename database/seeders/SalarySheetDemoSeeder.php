<?php

namespace Database\Seeders;

use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\Company;
use App\Models\Department;
use App\Models\DepartmentWorkHour;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalaryHead;
use App\Models\SalarySheet;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureLine;
use App\Models\Shift;
use App\Services\AuditLogger;
use App\Services\Operations\SalarySheetBuilder;
use Illuminate\Database\Seeder;

class SalarySheetDemoSeeder extends Seeder
{
    public function run(): void
    {
        AuditLogger::withoutAuditing(function (): void {
            $companyId = Employee::query()
                ->selectRaw('company_id, count(*) as people')
                ->groupBy('company_id')
                ->orderByDesc('people')
                ->value('company_id');

            $company = $companyId
                ? Company::query()->find($companyId)
                : Company::query()->orderBy('id')->first();

            if (! $company) {
                return;
            }

            $type = EmployeeType::query()->firstOrCreate(
                ['company_id' => $company->id, 'code' => 'PERM'],
                ['name' => 'Permanent', 'is_active' => true],
            );
            $status = EmploymentStatus::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SGCONF'],
                ['name' => 'Confirmed', 'is_exit' => false, 'is_active' => true],
            );
            $title = Designation::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SGOP'],
                ['name' => 'Operator', 'employee_type_id' => $type->id, 'is_active' => true],
            );
            $department = Department::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SEAT'],
                ['name' => 'Seat Cover', 'is_active' => true],
            );
            DepartmentWorkHour::query()->updateOrCreate(
                ['department_id' => $department->id],
                ['hours_per_day' => 9],
            );

            $shift = Shift::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SEATDAY'],
                ['name' => 'Seat Cover day', 'starts_at' => '09:00:00', 'ends_at' => '18:00:00', 'break_minutes' => 0, 'is_flexible' => false, 'is_active' => true],
            );

            $basic = SalaryHead::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SGBASIC'],
                ['name' => 'Basic', 'category' => 'earning', 'method' => 'percent_gross', 'value' => 100, 'sort_order' => 1, 'is_active' => true],
            );
            $structure = SalaryStructure::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SGDEMO'],
                ['name' => 'Seat Cover demo', 'is_active' => true],
            );
            SalaryStructureLine::query()->updateOrCreate(
                ['salary_structure_id' => $structure->id, 'salary_head_id' => $basic->id],
                ['value' => 100],
            );
            $removedPf = SalaryHead::query()->where('company_id', $company->id)->where('code', 'SGPF')->first();
            if ($removedPf) {
                SalaryStructureLine::query()->where('salary_head_id', $removedPf->id)->delete();
                $removedPf->delete();
            }

            $paid = LeaveType::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SGPL'],
                ['name' => 'Paid leave', 'is_paid' => true, 'annual_days' => 12, 'counts_weekly_off' => false, 'counts_holidays' => false, 'is_active' => true],
            );
            $unpaid = LeaveType::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SGUL'],
                ['name' => 'Unpaid leave', 'is_paid' => false, 'annual_days' => 12, 'counts_weekly_off' => false, 'counts_holidays' => false, 'is_active' => true],
            );

            $people = [
                ['SG01', 'Meera', 'Shah'],
                ['SG02', 'Jay', 'Patel'],
                ['SG03', 'Neha', 'Rana'],
                ['SG04', 'Amit', 'Joshi'],
                ['SG05', 'Kavya', 'Desai'],
                ['SG06', 'Ravi', 'Trivedi'],
                ['SG07', 'Pooja', 'Modi'],
                ['SG08', 'Hardik', 'Kanani'],
            ];

            $employees = [];
            foreach ($people as [$code, $first, $last]) {
                $employee = Employee::query()->updateOrCreate(
                    ['company_id' => $company->id, 'employee_code' => $code],
                    [
                        'department_id' => $department->id,
                        'designation_id' => $title->id,
                        'employee_type_id' => $type->id,
                        'employment_status_id' => $status->id,
                        'first_name' => $first,
                        'last_name' => $last,
                        'email' => strtolower($code).'@seatcover.test',
                        'joining_date' => '2026-04-01',
                        'is_blocked' => false,
                    ],
                );
                EmployeeSalary::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'effective_from' => '2026-04-01'],
                    ['salary_structure_id' => $structure->id, 'gross_monthly' => 21000],
                );
                $employees[$code] = $employee;
            }

            $ids = array_map(fn (Employee $employee) => $employee->id, $employees);
            AttendanceDay::query()->whereIn('employee_id', $ids)->whereBetween('work_date', ['2026-11-01', '2026-11-30'])->delete();
            LeaveRequest::query()->whereIn('employee_id', $ids)->whereDate('starts_on', '>=', '2026-11-01')->whereDate('ends_on', '<=', '2026-11-30')->delete();

            $fullDays = ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'];
            $skip = [
                'SG02' => ['2026-11-06'],
                'SG03' => ['2026-11-05'],
                'SG06' => ['2026-11-05', '2026-11-06'],
            ];

            foreach ($employees as $code => $employee) {
                foreach ($fullDays as $date) {
                    if (in_array($date, $skip[$code] ?? [], true)) {
                        continue;
                    }
                    if ($code === 'SG05' && $date === '2026-11-04') {
                        $this->punch($employee->id, $shift->id, $date, '09:00:00', '13:30:00', 270, 'half_day');

                        continue;
                    }
                    if ($code === 'SG07' && $date === '2026-11-02') {
                        $this->punch($employee->id, $shift->id, $date, '09:00:00', '19:00:00', 600, 'present');

                        continue;
                    }
                    if ($code === 'SG08' && $date === '2026-11-02') {
                        $this->punch($employee->id, $shift->id, $date, '09:25:00', '18:25:00', 540, 'present');

                        continue;
                    }
                    $this->punch($employee->id, $shift->id, $date, '09:00:00', '18:00:00', 540, 'present');
                }
            }

            LeaveRequest::query()->create([
                'employee_id' => $employees['SG02']->id,
                'leave_type_id' => $unpaid->id,
                'starts_on' => '2026-11-06',
                'ends_on' => '2026-11-06',
                'days' => 1,
                'reason' => 'Unpaid leave',
                'status' => 'approved',
                'decided_at' => now(),
            ]);
            LeaveRequest::query()->create([
                'employee_id' => $employees['SG03']->id,
                'leave_type_id' => $paid->id,
                'starts_on' => '2026-11-05',
                'ends_on' => '2026-11-05',
                'days' => 1,
                'reason' => 'Paid leave',
                'status' => 'approved',
                'decided_at' => now(),
            ]);

            Advance::query()->updateOrCreate(
                ['employee_id' => $employees['SG04']->id, 'title' => 'Salary advance'],
                ['amount' => 2000, 'emi' => 2000, 'balance' => 2000, 'starts_on' => '2026-11-03', 'status' => 'active'],
            );

            SalarySheet::query()
                ->where('department_id', $department->id)
                ->whereDate('starts_on', '2026-11-02')
                ->whereDate('ends_on', '2026-11-06')
                ->delete();

            app(SalarySheetBuilder::class)->make(
                (int) $company->id,
                (int) $department->id,
                $ids,
                '2026-11-02',
                '2026-11-06',
                null,
                $department->name,
            );
        });
    }

    private function punch(int $employeeId, int $shiftId, string $date, string $in, string $out, int $minutes, string $status): void
    {
        AttendanceDay::query()->create([
            'employee_id' => $employeeId,
            'work_date' => $date,
            'shift_id' => $shiftId,
            'punch_in' => $in,
            'punch_out' => $out,
            'status' => $status,
            'minutes_worked' => $minutes,
            'minutes_away' => 0,
            'minutes_personal' => 0,
            'minutes_office' => $minutes,
            'source' => 'manual',
        ]);
    }
}
