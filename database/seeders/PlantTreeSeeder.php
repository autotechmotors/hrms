<?php

namespace Database\Seeders;

use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Company;
use App\Models\Department;
use App\Models\DepartmentWorkHour;
use App\Models\Designation;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\EmployeeSalary;
use App\Models\EmployeeShiftSpan;
use App\Models\EmployeeType;
use App\Models\EmployeeWorkHour;
use App\Models\EmploymentStatus;
use App\Models\GatePass;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SalarySheet;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Operations\AttendanceCalculator;
use App\Services\Operations\SalarySheetBuilder;
use App\Services\Operations\ShiftSchedule;
use App\Services\Operations\WorkHourResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlantTreeSeeder extends Seeder
{
    public function run(): void
    {
        AuditLogger::withoutAuditing(function (): void {
            $company = $this->company();
            $this->clearPeople($company);
            $plant = $this->plant($company);
            $people = $this->people($company, $plant);
            $this->spans($people);
            $this->salaries($people);
            $this->advances($people);
            $this->leaveAndHolidays($company, $people);
            $this->passes($company, $people);
            $this->attendance($people);
            $this->sheets($company, $plant, $people);
            $this->command?->info('Plant tree ready: '.count($people).' people in '.$plant->name.'.');
        });
    }

    private function company(): Company
    {
        return Company::query()->updateOrCreate(['code' => 'AT'], [
            'name' => 'Autotech',
            'legal_name' => 'Autotech',
            'is_active' => true,
        ]);
    }

    private function clearPeople(Company $company): void
    {
        $removedCodes = Employee::query()
            ->where('company_id', $company->id)
            ->where('employee_code', 'not like', 'PL%')
            ->pluck('employee_code');
        $ids = Employee::query()->where('company_id', $company->id)->pluck('id');
        if ($ids->isNotEmpty()) {
            Employee::query()->whereIn('id', $ids)->update([
                'reporting_manager_id' => null,
                'user_id' => null,
            ]);
            foreach ([
                'payment_batch_lines', 'payroll_items', 'employee_documents', 'employment_events',
                'employee_exits', 'employee_bank_accounts', 'leave_requests', 'gate_passes',
                'attendance_days', 'attendance_punches', 'advances', 'employee_salaries',
                'employee_shift_spans', 'employee_work_hours', 'employee_holidays', 'shift_rosters',
                'device_users', 'salary_sheet_lines', 'employee_custom_field_values',
            ] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'employee_id')) {
                    DB::table($table)->whereIn('employee_id', $ids)->delete();
                }
            }
        }
        SalarySheet::query()->where('company_id', $company->id)->delete();
        Employee::query()->where('company_id', $company->id)->delete();

        $keep = User::query()->whereHas('roles', fn ($query) => $query->whereIn('slug', [
            'super-admin', 'hr-admin', 'payroll-admin', 'accounts', 'device-administrator',
        ]))->pluck('id');
        $drop = User::query()->whereNotIn('id', $keep)->pluck('id');
        if ($drop->isNotEmpty()) {
            DB::table('user_scopes')->whereIn('user_id', $drop)->delete();
            DB::table('user_roles')->whereIn('user_id', $drop)->delete();
            User::query()->whereIn('id', $drop)->delete();
        }

        LeaveType::query()->where('company_id', $company->id)->delete();
        DepartmentWorkHour::query()->whereIn('department_id', Department::query()->where('company_id', $company->id)->pluck('id'))->delete();
        Department::query()->where('company_id', $company->id)->delete();

        Holiday::query()->where('company_id', $company->id)->where('name', 'dsdsdsds')->update(['name' => 'Plant holiday']);
        $this->dismissTerminalCodes($company, $removedCodes);
        AttendanceRule::query()->updateOrCreate(
            ['company_id' => $company->id],
            ['grace_minutes' => 10, 'mark_absent_without_punch' => true],
        );
    }

    private function dismissTerminalCodes(Company $company, $codes): void
    {
        $deviceIds = Device::query()->where('company_id', $company->id)->pluck('id');
        foreach ($deviceIds as $deviceId) {
            foreach ($codes as $code) {
                DeviceTerminalUser::query()->updateOrCreate(
                    ['device_id' => $deviceId, 'user_id' => (string) $code],
                    ['dismissed_at' => now(), 'employee_id' => null],
                );
            }
        }
    }

    private function plant(Company $company): Department
    {
        $type = EmployeeType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PERM'],
            ['name' => 'Permanent', 'is_active' => true],
        );
        EmploymentStatus::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CONF'],
            ['name' => 'Confirmed', 'is_exit' => false, 'is_active' => true],
        );
        foreach ([
            'DIR' => 'Director',
            'HEAD' => 'Head',
            'MGR' => 'Manager',
            'OPR' => 'Operator',
        ] as $code => $name) {
            Designation::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'employee_type_id' => $type->id, 'is_active' => true],
            );
        }

        $plant = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Plant',
            'code' => 'PLANT',
            'is_active' => true,
        ]);
        DepartmentWorkHour::query()->create([
            'department_id' => $plant->id,
            'hours_per_day' => 9,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
            'sunday_start' => '08:00:00',
            'sunday_end' => '13:00:00',
            'works_sunday' => true,
            'weekday_break_start' => '13:00:00',
            'weekday_break_end' => '14:00:00',
            'night_weekday_start' => '21:00:00',
            'night_weekday_end' => '06:00:00',
            'night_weekday_break_start' => '01:00:00',
            'night_weekday_break_end' => '02:00:00',
            'night_works_sunday' => false,
        ]);

        return $plant;
    }

    /**
     * @return array<string, Employee>
     */
    private function people(Company $company, Department $plant): array
    {
        $typeId = EmployeeType::query()->where('company_id', $company->id)->where('code', 'PERM')->value('id');
        $statusId = EmploymentStatus::query()->where('company_id', $company->id)->where('code', 'CONF')->value('id');
        $titles = Designation::query()->where('company_id', $company->id)->pluck('id', 'code');
        $roles = Role::query()->whereIn('slug', ['director', 'head', 'manager', 'employee'])->pluck('id', 'slug');
        $rows = [
            ['PL01', 'Dharmesh', 'Patel', 'director', 'DIR', null, 'day', true],
            ['PL02', 'Vipul', 'Shah', 'head', 'HEAD', 'PL01', 'day', true],
            ['PL03', 'Manish', 'Dave', 'head', 'HEAD', 'PL01', 'day', true],
            ['PL04', 'Arif', 'Khan', 'manager', 'MGR', 'PL02', 'day', true],
            ['PL05', 'Nilesh', 'Rana', 'manager', 'MGR', 'PL02', 'night', true],
            ['PL06', 'Kiran', 'Joshi', 'manager', 'MGR', 'PL03', 'day', true],
            ['PL07', 'Sonal', 'Mehta', 'manager', 'MGR', 'PL03', 'day', true],
            ['PL08', 'Ravi', 'Solanki', 'employee', 'OPR', 'PL04', 'day', true],
            ['PL09', 'Amit', 'Parmar', 'employee', 'OPR', 'PL04', 'day', true],
            ['PL10', 'Neha', 'Trivedi', 'employee', 'OPR', 'PL04', 'day', true],
            ['PL11', 'Farhan', 'Shaikh', 'employee', 'OPR', 'PL04', 'day', true],
            ['PL12', 'Pooja', 'Desai', 'employee', 'OPR', 'PL04', 'day', false],
            ['PL13', 'Imran', 'Pathan', 'employee', 'OPR', 'PL05', 'night', true],
            ['PL14', 'Ketan', 'Chauhan', 'employee', 'OPR', 'PL05', 'night', true],
            ['PL15', 'Bhavin', 'Prajapati', 'employee', 'OPR', 'PL05', 'night', true],
            ['PL16', 'Mehul', 'Patel', 'employee', 'OPR', 'PL05', 'night', true],
            ['PL17', 'Riya', 'Shah', 'employee', 'OPR', 'PL06', 'day', true],
            ['PL18', 'Jay', 'Modi', 'employee', 'OPR', 'PL06', 'day', true],
            ['PL19', 'Tina', 'Raval', 'employee', 'OPR', 'PL06', 'day', true],
            ['PL20', 'Harsh', 'Vyas', 'employee', 'OPR', 'PL06', 'day', true],
            ['PL21', 'Suresh', 'Makwana', 'employee', 'OPR', 'PL07', 'day', true],
            ['PL22', 'Komal', 'Jani', 'employee', 'OPR', 'PL07', 'day', true],
            ['PL23', 'Nirav', 'Pandya', 'employee', 'OPR', 'PL07', 'day', true],
            ['PL24', 'Alpesh', 'Gohil', 'employee', 'OPR', 'PL07', 'day', true],
        ];
        $made = [];
        foreach ($rows as [$code, $first, $last, $role, $title, $parent, $kind, $sunday]) {
            $user = User::query()->updateOrCreate(
                ['email' => strtolower($code).'@autotech.test'],
                ['name' => $first.' '.$last, 'password' => RoleLoginSeeder::PASSWORD, 'is_active' => true],
            );
            $user->roles()->sync([$roles[$role]]);
            $employee = Employee::query()->create([
                'company_id' => $company->id,
                'department_id' => $plant->id,
                'designation_id' => $titles[$title],
                'employee_type_id' => $typeId,
                'employment_status_id' => $statusId,
                'role_id' => $roles[$role],
                'reporting_manager_id' => $parent ? $made[$parent]->id : null,
                'user_id' => $user->id,
                'employee_code' => $code,
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($code).'@autotech.test',
                'phone' => '980000'.substr($code, 2),
                'joining_date' => '2026-04-01',
                'salary_type' => 'hours',
                'is_blocked' => false,
            ]);
            $made[$code] = $employee;
            EmployeeWorkHour::query()->create([
                'employee_id' => $employee->id,
                'uses_department' => true,
                'shift_kind' => $kind,
                'works_sunday' => $sunday,
                'hours_per_day' => 9,
            ]);
        }

        return $made;
    }

    /**
     * @param  array<string, Employee>  $people
     */
    private function spans(array $people): void
    {
        foreach ($people as $code => $employee) {
            $kind = in_array($code, ['PL05', 'PL13', 'PL14', 'PL15', 'PL16'], true) ? 'night' : 'day';
            if ($code === 'PL15') {
                EmployeeShiftSpan::query()->create(['employee_id' => $employee->id, 'kind' => 'night', 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-31']);
                EmployeeShiftSpan::query()->create(['employee_id' => $employee->id, 'kind' => 'day', 'starts_on' => '2026-11-01', 'ends_on' => null]);
                $employee->workHour->update(['shift_kind' => 'day']);

                continue;
            }
            if ($code === 'PL24') {
                EmployeeShiftSpan::query()->create(['employee_id' => $employee->id, 'kind' => 'day', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
                EmployeeShiftSpan::query()->create(['employee_id' => $employee->id, 'kind' => 'night', 'starts_on' => '2026-10-01', 'ends_on' => null]);
                $employee->workHour->update(['shift_kind' => 'night']);

                continue;
            }
            EmployeeShiftSpan::query()->create([
                'employee_id' => $employee->id,
                'kind' => $kind,
                'starts_on' => '2026-09-01',
                'ends_on' => null,
            ]);
        }
    }

    /**
     * @param  array<string, Employee>  $people
     */
    private function salaries(array $people): void
    {
        $amount = [
            'PL01' => 80000, 'PL02' => 55000, 'PL03' => 55000,
            'PL04' => 40000, 'PL05' => 40000, 'PL06' => 40000, 'PL07' => 40000,
        ];
        foreach ($people as $code => $employee) {
            EmployeeSalary::query()->create([
                'employee_id' => $employee->id,
                'gross_monthly' => $amount[$code] ?? 25000,
                'effective_from' => '2026-09-01',
            ]);
        }
        EmployeeSalary::query()->create([
            'employee_id' => $people['PL20']->id,
            'gross_monthly' => 30000,
            'effective_from' => '2026-10-15',
        ]);
    }

    /**
     * @param  array<string, Employee>  $people
     */
    private function advances(array $people): void
    {
        Advance::query()->create([
            'employee_id' => $people['PL21']->id,
            'title' => 'Festival',
            'amount' => 2000,
            'emi' => 2000,
            'balance' => 2000,
            'starts_on' => '2026-09-03',
            'status' => 'active',
        ]);
        Advance::query()->create([
            'employee_id' => $people['PL22']->id,
            'title' => 'Medical',
            'amount' => 1500,
            'emi' => 1500,
            'balance' => 1500,
            'starts_on' => '2026-10-08',
            'status' => 'active',
        ]);
        Advance::query()->create([
            'employee_id' => $people['PL08']->id,
            'title' => 'Closed loan',
            'amount' => 500,
            'emi' => 500,
            'balance' => 500,
            'starts_on' => '2026-09-01',
            'status' => 'closed',
        ]);
    }

    /**
     * @param  array<string, Employee>  $people
     */
    private function leaveAndHolidays(Company $company, array $people): void
    {
        $paid = LeaveType::query()->create([
            'company_id' => $company->id,
            'name' => 'Paid leave',
            'code' => 'PLV',
            'is_paid' => true,
            'annual_days' => 0,
            'is_active' => true,
        ]);
        $cut = LeaveType::query()->create([
            'company_id' => $company->id,
            'name' => 'Cutoff leave',
            'code' => 'CUT',
            'is_paid' => false,
            'annual_days' => 0,
            'is_active' => true,
        ]);
        LeaveRequest::query()->create([
            'employee_id' => $people['PL17']->id,
            'leave_type_id' => $paid->id,
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-06',
            'days' => 2,
            'reason' => 'Family function',
            'status' => 'approved',
        ]);
        LeaveRequest::query()->create([
            'employee_id' => $people['PL18']->id,
            'leave_type_id' => $cut->id,
            'starts_on' => '2026-09-22',
            'ends_on' => '2026-09-22',
            'days' => 1,
            'reason' => 'Personal work',
            'status' => 'approved',
        ]);
        Holiday::query()->updateOrCreate(
            ['company_id' => $company->id, 'holiday_date' => '2026-09-17'],
            ['name' => 'Cutoff shutdown', 'kind' => 'cutoff'],
        );
        EmployeeHoliday::query()->create([
            'employee_id' => $people['PL22']->id,
            'holiday_date' => '2026-11-05',
            'name' => 'Family day',
        ]);
    }

    /**
     * @param  array<string, Employee>  $people
     */
    private function passes(Company $company, array $people): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->value('id');
        $this->pass($company, $people['PL10'], $actor, '2026-09-10', 'personal', false, 'expired', '11:00', '12:00', 'Personal work outside the break');
        $this->pass($company, $people['PL10'], $actor, '2026-10-14', 'personal', false, 'expired', '11:00', '12:00', 'Personal work outside the break');
        $this->pass($company, $people['PL10'], $actor, '2026-11-11', 'personal', false, 'expired', '11:00', '12:00', 'Personal work outside the break');
        $this->pass($company, $people['PL11'], $actor, '2026-09-16', 'office', false, 'expired', '15:00', '17:00', 'Factory work, returned');
        $this->pass($company, $people['PL11'], $actor, '2026-11-18', 'office', false, 'expired', '15:00', null, 'Factory work, still outside');
        $this->pass($company, $people['PL09'], $actor, '2026-10-07', 'personal', true, 'expired', '16:00', null, 'Early leave');
        $this->pass($company, $people['PL08'], $actor, '2026-11-20', 'personal', false, 'pending', '16:00', null, 'Waiting for the parent');
        $this->pass($company, $people['PL23'], $actor, '2026-11-12', 'personal', false, 'rejected', '15:00', null, 'Not approved');
    }

    private function pass(Company $company, Employee $employee, ?int $actor, string $date, string $type, bool $early, string $status, string $out, ?string $back, string $reason): void
    {
        $start = Carbon::parse($date.' '.$out, 'Asia/Kolkata');
        GatePass::query()->create([
            'company_id' => $company->id,
            'number' => 'PL-'.$employee->employee_code.'-'.str_replace('-', '', $date),
            'employee_id' => $employee->id,
            'created_by' => $actor,
            'date' => $date,
            'out_time' => $out,
            'duration_minutes' => 60,
            'is_early_leave' => $early,
            'work_type' => $type,
            'reason' => $reason,
            'status' => $status,
            'decided_by' => $status === 'pending' ? null : $actor,
            'decided_at' => $status === 'pending' ? null : $start,
            'decision_note' => $status === 'rejected' ? 'Stay on the line' : null,
            'expires_at' => $start->copy()->addHour(),
            'out_at' => $status === 'pending' || $status === 'rejected' ? null : $start,
            'in_at' => $back ? Carbon::parse($date.' '.$back, 'Asia/Kolkata') : null,
            'machine_status' => $status === 'pending' ? 'none' : ($status === 'rejected' ? 'revoked' : 'granted'),
        ]);
    }

    /**
     * @param  array<string, Employee>  $people
     */
    private function attendance(array $people): void
    {
        $schedule = app(ShiftSchedule::class);
        $calculator = app(AttendanceCalculator::class);
        $cursor = Carbon::parse('2026-09-01', 'Asia/Kolkata')->startOfDay();
        $end = Carbon::parse('2026-11-20', 'Asia/Kolkata')->startOfDay();
        $absent = [
            'PL19' => ['2026-09-08', '2026-10-13', '2026-11-10'],
        ];
        $leave = [
            'PL17' => ['2026-10-05', '2026-10-06'],
            'PL18' => ['2026-09-22'],
        ];
        $ownHoliday = ['PL22' => ['2026-11-05']];
        $companyOff = ['2026-09-17', '2026-10-25', '2026-11-01'];

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            foreach ($people as $code => $employee) {
                if (in_array($key, $companyOff, true) || in_array($key, $leave[$code] ?? [], true) || in_array($key, $ownHoliday[$code] ?? [], true) || in_array($key, $absent[$code] ?? [], true)) {
                    $calculator->recalculate($employee, $cursor->copy());

                    continue;
                }
                $employee->load('workHour');
                $night = $schedule->kindOn($employee, $cursor) === 'night';
                $sundayOff = $cursor->dayOfWeek === Carbon::SUNDAY && ! app(WorkHourResolver::class)->worksSunday($employee, $cursor);
                if ($sundayOff) {
                    $calculator->recalculate($employee, $cursor->copy());

                    continue;
                }
                [$in, $out] = $this->clocks($code, $cursor, $night);
                AttendanceDay::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'work_date' => $key],
                    ['punch_in' => $in, 'punch_out' => $out, 'source' => 'machine', 'status' => 'present'],
                );
                $calculator->recalculate($employee, $cursor->copy());
            }
            $cursor->addDay();
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function clocks(string $code, Carbon $day, bool $night): array
    {
        if ($code === 'PL23' && $day->toDateString() === '2026-09-09') {
            return ['09:00:00', null];
        }
        if ($code === 'PL11' && $day->toDateString() === '2026-11-18') {
            return ['09:00:00', null];
        }
        if ($night) {
            $in = $code === 'PL14' ? '21:40:00' : '21:00:00';

            return [$in, '06:00:00'];
        }
        if ($day->dayOfWeek === Carbon::SUNDAY) {
            return ['08:00:00', '13:00:00'];
        }
        $in = $code === 'PL08' ? '09:40:00' : '09:00:00';
        $out = ($code === 'PL09' && $day->dayOfWeek === Carbon::MONDAY) ? '16:30:00' : '18:00:00';

        return [$in, $out];
    }

    /**
     * @param  array<string, Employee>  $people
     */
    private function sheets(Company $company, Department $plant, array $people): void
    {
        $builder = app(SalarySheetBuilder::class);
        $ids = collect($people)->map(fn (Employee $employee) => $employee->id)->all();
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->value('id');
        $builder->make($company->id, $plant->id, $ids, '2026-10-01', '2026-10-31', $actor, 'October 2026');
        $builder->make($company->id, $plant->id, $ids, '2026-11-01', '2026-11-20', $actor, 'November 2026');
        $september = $builder->make($company->id, $plant->id, $ids, '2026-09-01', '2026-09-30', $actor, 'September 2026');
        $september->update([
            'status' => 'approved',
            'decided_by' => $actor,
            'decided_at' => now(),
        ]);
        $builder->settleAdvances($september);
    }
}
