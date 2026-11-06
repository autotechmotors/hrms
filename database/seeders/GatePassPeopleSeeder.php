<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\GatePass;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Services\AuditLogger;
use App\Services\Operations\GatePassService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GatePassPeopleSeeder extends Seeder
{
    public const STAFF_PASSWORD = 'password';

    public const SUPER_PASSWORD = '12345678';

    public function run(): void
    {
        AuditLogger::withoutAuditing(function (): void {
            $this->call(RoleAndPermissionSeeder::class);
            $this->seedPeople();
            $this->seedDemoPasses();
        });
    }

    private function seedPeople(): void
    {
        $anchor = Employee::query()->whereIn('employee_code', ['100033', 'AUTO001', 'AUTO003'])->first()
            ?? Employee::query()->orderBy('id')->first();
        if (! $anchor) {
            return;
        }

        $company = Company::query()->find($anchor->company_id);
        if (! $company) {
            return;
        }

        $type = EmployeeType::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PERM'],
            ['name' => 'Permanent', 'is_active' => true]
        );
        $status = EmploymentStatus::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'CONF'],
            ['name' => 'Confirmed', 'is_exit' => false, 'is_active' => true]
        );

        $people = [
            ['key' => 'vipul', 'role' => 'director', 'parent' => null, 'dept' => 'Office', 'des' => 'Director', 'gms' => 'AT00001', 'codes' => ['AUTO001'], 'names' => ['VIPULBHAI']],
            ['key' => 'dharmesh', 'role' => 'head', 'parent' => 'vipul', 'dept' => 'Office', 'des' => 'Head', 'gms' => 'AT00002', 'codes' => ['100033'], 'names' => ['Dharmesh Koladia', 'DHARMESHBHAI']],
            ['key' => 'manish', 'role' => 'head', 'parent' => 'dharmesh', 'dept' => 'Office', 'des' => 'Head', 'gms' => 'AT00003', 'codes' => ['AUTO003'], 'names' => ['MANISHBHAI']],
            ['key' => 'ghanshyam', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Cad Office', 'des' => 'Manager', 'gms' => 'AT00004', 'codes' => ['AUTO005'], 'names' => ['GHANSHYAMBHAI']],
            ['key' => 'hitesh', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Cad Office', 'des' => 'Manager', 'gms' => 'AT00005', 'codes' => ['AUTO004'], 'names' => ['HITESHBHAI']],
            ['key' => 'manoj', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Seat Cover', 'des' => 'Manager', 'gms' => 'AT00006', 'codes' => ['ATX089'], 'names' => ['Manojbhai']],
            ['key' => 'sahil', 'role' => 'manager', 'parent' => 'manish', 'dept' => '7D', 'des' => 'Manager', 'gms' => 'AT00007', 'codes' => ['ATS065'], 'names' => ['SAHIL User']],
            ['key' => 'alpesh', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Lamination', 'des' => 'Manager', 'gms' => 'AT00008', 'codes' => ['AUTO006'], 'names' => ['ALPESHBHAI']],
            ['key' => 'kajal', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'X-Liner', 'des' => 'Manager', 'gms' => 'AT00009', 'codes' => ['ATS279'], 'names' => ['Kajal']],
            ['key' => 'rashid', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Cutting', 'des' => 'Manager', 'gms' => 'AT00010', 'codes' => ['AT7D112'], 'names' => ['RASHID']],
            ['key' => 'rahul', 'role' => 'manager', 'parent' => 'manish', 'dept' => '7D', 'des' => 'Manager', 'gms' => 'AT00011', 'codes' => ['AT7D099'], 'names' => ['RAHUL MADHUKAR VARULE']],
            ['key' => 'arif', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Seat Cover', 'des' => 'Manager', 'gms' => 'AT00012', 'codes' => ['ATS319'], 'names' => ['Arif']],
            ['key' => 'amit', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Seat Cover', 'des' => 'Manager', 'gms' => 'AT00013', 'codes' => ['AUTO007'], 'names' => ['AMITBHAI']],
            ['key' => 'pankaj', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'Bonding', 'des' => 'Manager', 'gms' => 'AT00014', 'codes' => ['ATB001'], 'names' => ['PANKAJ ASHOK SASTE']],
            ['key' => 'archit', 'role' => 'manager', 'parent' => 'manish', 'dept' => 'X-Liner', 'des' => 'Manager', 'gms' => 'AT00016', 'codes' => ['AUTO009'], 'names' => ['ARSHITBHAI']],
            ['key' => 'hardik', 'role' => 'manager', 'parent' => 'dharmesh', 'dept' => 'Operations', 'des' => 'Manager', 'gms' => 'AT0007', 'codes' => ['AT0007'], 'names' => ['hardik g Kanani']],
        ];

        $resolved = [];
        foreach ($people as $row) {
            $employee = $this->findEmployee($row);
            if (! $employee) {
                $this->command?->warn('Skipped '.$row['key'].' — no matching employee.');
                continue;
            }

            $department = $this->department($company->id, $row['dept']);
            $designation = $this->designation($company->id, $row['des'], $type->id);

            $employee->fill([
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'employee_type_id' => $type->id,
                'employment_status_id' => $status->id,
                'email' => $employee->email ?: strtolower($row['gms']).'@autotechmotors.com',
            ])->save();

            $resolved[$row['key']] = ['employee' => $employee->fresh(), 'row' => $row];
        }

        foreach ($resolved as $item) {
            $parentKey = $item['row']['parent'];
            $parentId = $parentKey && isset($resolved[$parentKey])
                ? $resolved[$parentKey]['employee']->id
                : null;
            $item['employee']->update(['reporting_manager_id' => $parentId]);
        }

        foreach ($resolved as $item) {
            $this->login($item['employee']->fresh(), $item['row'], $company->id);
        }

        $this->attachRest($resolved);
        $this->gmsAdminUser();

        $this->command?->info('Gate Pass people: '.count($resolved).' employees linked with GMS roles/parents.');
        $this->command?->info('Staff login password: '.self::STAFF_PASSWORD.'  ·  Super Admin auto1729@autotechmotors.com / '.self::SUPER_PASSWORD);
    }

    private function seedDemoPasses(): void
    {
        $service = app(GatePassService::class);
        $arif = $this->byCode(['ATS319']);
        $sahil = $this->byCode(['ATS065']);
        $kajal = $this->byCode(['ATS279']);
        $hitesh = $this->byCode(['AUTO004']);
        $pankaj = $this->byCode(['ATB001']);
        $manish = $this->byCode(['AUTO003']);
        $dharmesh = $this->byCode(['100033']);
        $hr = User::query()->where('email', 'hr@hrms.test')->first();

        $demos = [
            ['actor' => $arif?->user, 'for' => $arif, 'work' => 'personal', 'reason' => 'Demo: bank work — waiting on parent', 'then' => 'approve', 'decider' => $manish?->user],
            ['actor' => $sahil?->user, 'for' => $sahil, 'work' => 'office', 'reason' => 'Demo: client visit — rejected by head', 'then' => 'reject', 'decider' => $dharmesh?->user],
            ['actor' => $manish?->user, 'for' => $kajal, 'work' => 'personal', 'reason' => 'Demo: manager created for child'],
            ['actor' => $dharmesh?->user, 'for' => $hitesh, 'work' => 'office', 'reason' => 'Demo: head created for manager'],
            ['actor' => $hr, 'for' => $pankaj, 'work' => 'personal', 'reason' => 'Demo: HR created and auto-approved'],
        ];

        foreach ($demos as $demo) {
            if (! $demo['actor'] || ! $demo['for']) {
                continue;
            }
            if (GatePass::query()->where('employee_id', $demo['for']->id)->where('reason', $demo['reason'])->exists()) {
                continue;
            }

            try {
                $pass = $service->create($demo['actor'], [
                    'date' => now()->toDateString(),
                    'out_time' => now()->format('H:i'),
                    'duration_minutes' => 60,
                    'is_early_leave' => false,
                    'work_type' => $demo['work'],
                    'reason' => $demo['reason'],
                ], $demo['for']);
            } catch (ValidationException $e) {
                $this->command?->warn($demo['reason'].' — '.$e->getMessage());
                continue;
            }

            if (($demo['then'] ?? null) === 'approve' && ($demo['decider'] ?? null)) {
                try {
                    $service->approve($pass, $demo['decider']);
                } catch (ValidationException) {
                }
            }
            if (($demo['then'] ?? null) === 'reject' && ($demo['decider'] ?? null)) {
                try {
                    $service->reject($pass, $demo['decider'], 'Need the visit letter first.');
                } catch (ValidationException) {
                }
            }
        }
    }

    /**
     * @param  array{codes: list<string>, names: list<string>}  $row
     */
    private function findEmployee(array $row): ?Employee
    {
        foreach ($row['codes'] as $code) {
            $found = Employee::query()->where('employee_code', $code)->first();
            if ($found) {
                return $found;
            }
        }
        foreach ($row['names'] as $name) {
            $found = Employee::query()
                ->whereRaw("concat_ws(' ', first_name, middle_name, last_name) like ?", ['%'.$name.'%'])
                ->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function byCode(array $codes): ?Employee
    {
        return Employee::query()->whereIn('employee_code', $codes)->first();
    }

    /**
     * @param  array<string, array{employee: Employee, row: array<string, mixed>}>  $resolved
     */
    private function attachRest(array $resolved): void
    {
        $sample = collect($resolved)->first();
        $companyId = $sample['employee']->company_id ?? 0;
        if (! $companyId) {
            return;
        }

        $type = EmployeeType::query()->where('company_id', $companyId)->where('code', 'PERM')->first();
        $operator = $this->designation($companyId, 'Operator', (int) ($type?->id ?: 0));

        $prefixMap = [
            'AT7D' => ['dept' => '7D', 'parent' => 'sahil'],
            'ATL0' => ['dept' => 'Lamination', 'parent' => 'alpesh'],
            'ATL1' => ['dept' => 'Lamination', 'parent' => 'alpesh'],
            'ATS0' => ['dept' => 'Seat Cover', 'parent' => 'arif'],
            'ATS1' => ['dept' => 'Seat Cover', 'parent' => 'amit'],
            'ATS2' => ['dept' => 'X-Liner', 'parent' => 'kajal'],
            'ATS3' => ['dept' => 'Seat Cover', 'parent' => 'arif'],
            'AUTO' => ['dept' => 'Office', 'parent' => 'manish'],
            'ATX0' => ['dept' => 'Seat Cover', 'parent' => 'manoj'],
            'ATX1' => ['dept' => 'X-Liner', 'parent' => 'archit'],
            'AT00' => ['dept' => 'Operations', 'parent' => 'hardik'],
            'ATB0' => ['dept' => 'Bonding', 'parent' => 'pankaj'],
            'ATGE' => ['dept' => 'Office', 'parent' => 'manish'],
        ];

        $fallback = $resolved['manish']['employee']->id ?? $resolved['dharmesh']['employee']->id ?? null;
        if (! $fallback) {
            return;
        }

        $skipIds = collect($resolved)->pluck('employee.id')->filter()->all();
        $updated = 0;

        Employee::query()
            ->whereNotIn('id', $skipIds ?: [0])
            ->where('employee_code', 'not like', 'CHK%')
            ->orderBy('id')
            ->each(function (Employee $employee) use ($prefixMap, $resolved, $companyId, $operator, $fallback, &$updated): void {
                $map = $prefixMap[substr((string) $employee->employee_code, 0, 4)] ?? null;
                $parentKey = $map['parent'] ?? null;
                $parentId = ($parentKey && isset($resolved[$parentKey]))
                    ? $resolved[$parentKey]['employee']->id
                    : $fallback;

                $payload = ['reporting_manager_id' => $employee->reporting_manager_id ?: $parentId];
                if ($map) {
                    $payload['department_id'] = $this->department($companyId, $map['dept'])->id;
                    $payload['designation_id'] = $operator->id;
                }

                $employee->update($payload);
                $updated++;
            });

        $this->command?->info("Assigned reporting managers to {$updated} remaining employees.");
    }

    private function department(int $companyId, string $name): Department
    {
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'DEP', 0, 8));

        return Department::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => $name],
            ['code' => $code, 'is_active' => true]
        );
    }

    private function designation(int $companyId, string $name, int $typeId): Designation
    {
        $code = strtoupper(substr($name, 0, 6));

        return Designation::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => $name],
            ['code' => $code, 'employee_type_id' => $typeId, 'is_active' => true]
        );
    }

    /**
     * @param  array{gms: string, role: string}  $row
     */
    private function login(Employee $employee, array $row, int $companyId): void
    {
        $email = strtolower($row['gms']).'@autotechmotors.com';
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $employee->full_name,
                'password' => self::STAFF_PASSWORD,
                'is_active' => true,
            ]
        );
        if (! Hash::check(self::STAFF_PASSWORD, $user->password)) {
            $user->forceFill(['password' => self::STAFF_PASSWORD])->save();
        }
        $roleId = Role::query()->where('slug', $row['role'])->value('id');
        if ($roleId) {
            $user->roles()->sync([$roleId]);
        }
        UserScope::query()->updateOrCreate([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $companyId,
        ]);
        $employee->update(['user_id' => $user->id]);
    }

    private function gmsAdminUser(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'auto1729@autotechmotors.com'],
            [
                'name' => 'Super Admin',
                'password' => self::SUPER_PASSWORD,
                'is_active' => true,
            ]
        );
        if (! Hash::check(self::SUPER_PASSWORD, $admin->password)) {
            $admin->forceFill(['password' => self::SUPER_PASSWORD])->save();
        }
        $roleId = Role::query()->where('slug', 'super-admin')->value('id');
        if ($roleId) {
            $admin->roles()->sync([$roleId]);
        }
    }
}
