<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * Permissions are stored in the database so administrators can assign them.
     * Super Admin bypasses this list in code and always has every permission.
     * HR Admin is granted every slug by RoleAndPermissionSeeder.
     *
     * @return list<array{group: string, name: string, slug: string}>
     */
    public static function all(): array
    {
        $modules = [
            'companies' => 'Companies',
            'departments' => 'Departments',
            'designations' => 'Designations',
            'employee_types' => 'Employee types',
            'employment_statuses' => 'Employment statuses',
            'financial_years' => 'Financial years',
            'work_calendars' => 'Work calendars',
            'custom_fields' => 'Custom fields',
            'users' => 'Users',
            'roles' => 'Roles',
        ];

        $actions = [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ];

        $permissions = [
            ['group' => 'dashboard', 'name' => 'View dashboard', 'slug' => 'dashboard.view'],
            ['group' => 'branding', 'name' => 'Edit branding', 'slug' => 'branding.edit'],
            ['group' => 'settings', 'name' => 'Manage system settings', 'slug' => 'settings.manage'],
            ['group' => 'audit_logs', 'name' => 'View audit logs', 'slug' => 'audit_logs.view'],
        ];

        foreach ($modules as $slug => $label) {
            foreach ($actions as $action => $actionLabel) {
                $permissions[] = [
                    'group' => $slug,
                    'name' => $label.' — '.$actionLabel,
                    'slug' => $slug.'.'.$action,
                ];
            }
        }

        $extra = [
            'employees' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'export' => 'Export', 'import' => 'Import'],
            'employee_documents' => ['view' => 'View', 'create' => 'Create', 'delete' => 'Delete'],
            'employment_events' => ['view' => 'View', 'create' => 'Create'],
            'employee_exits' => ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit'],
            'bank_accounts' => ['view' => 'View', 'manage' => 'Manage'],
            'devices' => ['manage' => 'Manage devices'],
            'attendance' => ['view' => 'View attendance', 'edit' => 'Edit attendance'],
            'shifts' => ['view' => 'View shifts', 'manage' => 'Manage shifts'],
            'leave' => ['view' => 'View leave', 'decide' => 'Decide leave'],
            'gate_passes' => ['view' => 'View gate passes', 'create' => 'Create gate passes', 'decide' => 'Decide gate passes', 'manage' => 'Manage gate pass rules'],
            'holidays' => ['view' => 'View holidays', 'manage' => 'Manage holidays'],
            'salary' => ['view' => 'View salary', 'manage' => 'Manage salary'],
            'advances' => ['view' => 'View advances', 'manage' => 'Manage advances'],
            'payroll' => [
                'view' => 'View payroll',
                'process' => 'Process payroll',
                'approve' => 'Approve payroll',
                'lock' => 'Lock payroll',
                'reopen' => 'Reopen payroll',
            ],
            'reports' => ['view' => 'View reports'],
            'bank' => ['view' => 'View bank transfers'],
            'payments' => ['release' => 'Release payments'],
        ];

        foreach ($extra as $group => $groupActions) {
            foreach ($groupActions as $action => $label) {
                $permissions[] = [
                    'group' => $group,
                    'name' => $label,
                    'slug' => $group.'.'.$action,
                ];
            }
        }

        return $permissions;
    }
}
