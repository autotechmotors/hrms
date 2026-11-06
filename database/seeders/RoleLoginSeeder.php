<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\GatePass;
use App\Models\GatePassSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Services\AuditLogger;
use App\Services\Operations\GatePassService;
use Illuminate\Database\Seeder;

class RoleLoginSeeder extends Seeder
{
    public const PASSWORD = 'Role@12345';

    public function run(): void
    {
        AuditLogger::withoutAuditing(function (): void {
            $company = Company::query()->where('code', 'AT')->first()
                ?? Company::query()->orderBy('id')->first();
            if (! $company) {
                $this->command?->warn('No company found. Role logins were not added.');

                return;
            }

            $this->call(RoleAndPermissionSeeder::class);

            foreach ($this->accounts() as $account) {
                $this->login($company->id, $account);
            }

            $this->sampleGatePass();

            $this->command?->info('Role logins use password '.self::PASSWORD);
            $this->command?->info('Super Admin, Payroll Admin, and Accounts still ask for the authenticator. Code 123456 is accepted.');
        });
    }

    /**
     * @param  array{email: string, name: string, role: string, employee: ?string, scope: string}  $account
     */
    private function login(int $companyId, array $account): void
    {
        $employee = $account['employee']
            ? Employee::query()->where('company_id', $companyId)->where('employee_code', $account['employee'])->first()
            : null;
        $role = Role::query()->where('slug', $account['role'])->first();
        if (! $role || ($account['employee'] && ! $employee)) {
            $this->command?->warn('Skipped '.$account['email'].' — missing role or employee.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $account['email']],
            ['name' => $account['name'], 'password' => self::PASSWORD, 'is_active' => true]
        );
        $user->roles()->sync([$role->id]);

        $user->scopes()->delete();
        if ($account['scope'] === 'company') {
            UserScope::query()->create([
                'user_id' => $user->id,
                'scope_type' => 'company',
                'scope_id' => $companyId,
            ]);
        }
        if ($account['scope'] === 'department' && $employee) {
            UserScope::query()->create([
                'user_id' => $user->id,
                'scope_type' => 'department',
                'scope_id' => $employee->department_id,
            ]);
        }

        if ($employee) {
            Employee::query()->where('user_id', $user->id)->whereKeyNot($employee->id)->update(['user_id' => null]);
            $employee->update([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
        }
    }

    private function sampleGatePass(): void
    {
        $actor = User::query()->where('email', 'employee@autotech.test')->first();
        $employee = $actor?->employee;
        if (! $actor || ! $employee) {
            return;
        }

        GatePassSetting::forCompany((int) $employee->company_id);

        $reason = 'Sample: personal work, waiting for parent';
        if (GatePass::query()->where('employee_id', $employee->id)->where('reason', $reason)->exists()) {
            return;
        }

        app(GatePassService::class)->create($actor, [
            'date' => now()->toDateString(),
            'out_time' => '16:00',
            'duration_minutes' => 60,
            'is_early_leave' => false,
            'work_type' => GatePass::WORK_PERSONAL,
            'reason' => $reason,
        ], $employee);
    }

    /**
     * @return list<array{email: string, name: string, role: string, employee: ?string, scope: string}>
     */
    private function accounts(): array
    {
        return [
            ['email' => 'super@autotech.test', 'name' => 'Super Admin', 'role' => 'super-admin', 'employee' => null, 'scope' => 'all'],
            ['email' => 'hr@autotech.test', 'name' => 'Nisha Shah', 'role' => 'hr-admin', 'employee' => 'CHK01', 'scope' => 'company'],
            ['email' => 'director@autotech.test', 'name' => 'Amit Desai', 'role' => 'director', 'employee' => 'CHK02', 'scope' => 'company'],
            ['email' => 'head@autotech.test', 'name' => 'Mehul Patel', 'role' => 'head', 'employee' => 'CHK13', 'scope' => 'tree'],
            ['email' => 'manager@autotech.test', 'name' => 'Priya Patel', 'role' => 'manager', 'employee' => 'CHK03', 'scope' => 'department'],
            ['email' => 'payroll@autotech.test', 'name' => 'Bhavna Patel', 'role' => 'payroll-admin', 'employee' => 'CHK12', 'scope' => 'company'],
            ['email' => 'accounts@autotech.test', 'name' => 'Jay Modi', 'role' => 'accounts', 'employee' => 'CHK09', 'scope' => 'company'],
            ['email' => 'devices@autotech.test', 'name' => 'Device Admin', 'role' => 'device-administrator', 'employee' => null, 'scope' => 'company'],
            ['email' => 'employee@autotech.test', 'name' => 'Neha Trivedi', 'role' => 'employee', 'employee' => 'CHK06', 'scope' => 'own'],
        ];
    }
}
