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

            $this->role('Super Admin', 'super-admin', true, true, $all->values()->all(), false, true);
            $this->role('HR Admin', 'hr-admin', true, false, $all->values()->all(), false, true);
            $this->role('Payroll Admin', 'payroll-admin', true, true, $all->only([
                'dashboard.view', 'employees.view', 'employees.export', 'bank_accounts.view', 'bank.view', 'audit_logs.view',
                'payroll.view', 'payroll.process', 'payroll.approve', 'payroll.lock', 'payroll.reopen',
                'advances.view', 'advances.manage',
            ])->values()->all());
            $this->role('Accounts', 'accounts', true, true, $all->only([
                'dashboard.view', 'employees.view', 'bank.view', 'bank_accounts.view', 'bank_accounts.manage', 'payments.release', 'audit_logs.view',
            ])->values()->all());
            $this->role('Manager', 'manager', true, false, $all->only([
                'dashboard.view', 'employees.view', 'departments.view',
                'attendance.view', 'shifts.view', 'leave.view', 'holidays.view',
                'gate_passes.view', 'gate_passes.create', 'gate_passes.decide',
            ])->values()->all(), true, true);
            $this->role('Head', 'head', true, false, $all->only([
                'dashboard.view', 'employees.view', 'departments.view',
                'attendance.view', 'shifts.view', 'leave.view', 'holidays.view',
                'gate_passes.view', 'gate_passes.create', 'gate_passes.decide',
            ])->values()->all(), true, true);
            $this->role('Director', 'director', true, false, $all->only([
                'dashboard.view', 'employees.view', 'departments.view', 'audit_logs.view',
                'attendance.view', 'reports.view',
                'gate_passes.view', 'gate_passes.create', 'gate_passes.decide',
            ])->values()->all(), false, true);
            $this->role('Device Administrator', 'device-administrator', true, false, $all->only([
                'dashboard.view', 'devices.manage', 'employees.view',
            ])->values()->all());
            $this->role('Employee', 'employee', true, false, $all->only([
                'dashboard.view', 'employees.view', 'attendance.view',
                'leave.view', 'holidays.view',
                'gate_passes.view', 'gate_passes.create',
            ])->values()->all(), true, false);
        });
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function role(string $name, string $slug, bool $system, bool $mfa, array $permissionIds, bool $hasParent = false, bool $hasChild = false): void
    {
        $role = Role::query()->updateOrCreate(['slug' => $slug], [
            'name' => $name,
            'is_system' => $system,
            'requires_mfa' => $mfa,
            'has_parent' => $hasParent,
            'has_child' => $hasChild,
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);
    }
}
