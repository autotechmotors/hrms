<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        AuditLogger::withoutAuditing(function (): void {
            foreach (PermissionCatalog::all() as $permission) {
                Permission::query()->updateOrCreate(['slug' => $permission['slug']], $permission);
            }

            $all = Permission::query()->pluck('id', 'slug');

            $this->role('Super Admin', 'super-admin', true, true, $all->values()->all());
            $this->role('HR Admin', 'hr-admin', true, false, $all->except([
                'roles.create', 'roles.edit', 'roles.delete', 'users.delete', 'settings.manage',
                'payments.release', 'payroll.lock', 'payroll.reopen',
            ])->values()->all());
            $this->role('Payroll Admin', 'payroll-admin', true, true, $all->only([
                'dashboard.view', 'employees.view', 'employees.export', 'bank_accounts.view', 'audit_logs.view',
                'payroll.process', 'payroll.approve', 'payroll.lock', 'payroll.reopen',
            ])->values()->all());
            $this->role('Accounts', 'accounts', true, true, $all->only([
                'dashboard.view', 'employees.view', 'bank_accounts.view', 'bank_accounts.manage', 'payments.release', 'audit_logs.view',
            ])->values()->all());
            $this->role('Manager', 'manager', true, false, $all->only([
                'dashboard.view', 'employees.view', 'departments.view', 'branches.view',
            ])->values()->all());
            $this->role('Director', 'director', true, false, $all->only([
                'dashboard.view', 'employees.view', 'departments.view', 'audit_logs.view',
            ])->values()->all());
            $this->role('Device Administrator', 'device-administrator', true, false, $all->only([
                'dashboard.view', 'devices.manage', 'employees.view',
            ])->values()->all());

            $employeeRole = Role::query()->where('slug', 'employee')->first();
            if ($employeeRole) {
                $employeeRole->users()->detach();
                $employeeRole->permissions()->detach();
                $employeeRole->delete();
            }
        });
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function role(string $name, string $slug, bool $system, bool $mfa, array $permissionIds): void
    {
        $role = Role::query()->updateOrCreate(['slug' => $slug], [
            'name' => $name,
            'is_system' => $system,
            'requires_mfa' => $mfa,
        ]);
        $role->permissions()->sync($permissionIds);
    }
}
