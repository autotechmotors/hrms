<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    /**
     * @return array<string, list<array{label: string, route: string, active: list<string>, icon: string, any: list<string>, page: string}>>
     */
    public static function groups(): array
    {
        return [
            'Overview' => [
                self::link('Dashboard', 'dashboard', ['dashboard'], 'dash', ['dashboard.view'], 'dashboard'),
            ],
            'People' => [
                self::link('Employees', 'employees.index', ['employees.*'], 'users', ['employees.view'], 'employees'),
                self::link('Attendance', 'attendance.index', ['attendance.*'], 'clock', ['attendance.view'], 'attendance'),
                self::link('Leave Management', 'leave.index', ['leave.*'], 'minus', ['leave.view'], 'leave management'),
                self::link('Gate Pass', 'gate-passes.index', ['gate-passes.*'], 'clock', ['gate_passes.view'], 'gate pass'),
                self::link('Holidays', 'holidays.index', ['holidays.*'], 'sun', ['holidays.view'], 'holidays'),
            ],
            'Payroll' => [
                self::link('Hours & pay', 'hours-pay.index', ['hours-pay.*'], 'clock', ['salary.view'], 'hours & pay'),
                self::link('Salary sheets', 'salary-sheets.index', ['salary-sheets.*'], 'wallet', ['salary.view', 'salary.decide', 'payroll.process'], 'salary sheets'),
                self::link('Advances & Deductions', 'advances.index', ['advances.*'], 'minus', ['advances.view', 'advances.manage', 'advances.decide', 'payroll.process'], 'advances & deductions'),
            ],
            'Operations' => [
                self::link('Devices', 'devices.index', ['devices.*'], 'cpu', ['devices.manage'], 'devices'),
                self::link('Reports & Analytics', 'reports.index', ['reports.*'], 'chart', ['reports.view'], 'reports & analytics'),
                self::link('Bank Transfers', 'bank.index', ['bank.*'], 'bank', ['bank.view', 'bank_accounts.view', 'bank_accounts.manage', 'payments.release'], 'bank transfers'),
            ],
            'Administration' => [
                self::link('Settings', 'settings.edit', ['settings.*'], 'set', ['settings.manage'], 'settings'),
                self::link('Roles & Permissions', 'roles.index', ['roles.*'], 'shield', ['roles.view'], 'roles & permissions'),
                self::link('Departments', 'departments.index', ['departments.*'], 'building', ['departments.view'], 'departments'),
                self::link('Designations', 'designations.index', ['designations.*', 'employee-types.*'], 'users', ['designations.view'], 'designations'),
                self::link('Statuses', 'employment-statuses.index', ['employment-statuses.*'], 'set', ['employment_statuses.view'], 'statuses'),
                self::link('Work calendars', 'work-calendars.index', ['work-calendars.*'], 'cal', ['work_calendars.view'], 'work calendars'),
                self::link('Custom fields', 'custom-fields.index', ['custom-fields.*'], 'set', ['custom_fields.view'], 'custom fields'),
                self::link('Audit Logs', 'audit-logs.index', ['audit-logs.*'], 'log', ['audit_logs.view'], 'audit logs'),
            ],
        ];
    }

    /**
     * @return array<string, list<array{label: string, route: string, active: list<string>, icon: string, any: list<string>, page: string}>>
     */
    public static function visible(User $user): array
    {
        $groups = [];
        foreach (self::groups() as $label => $links) {
            $shown = array_values(array_filter($links, fn (array $link): bool => $user->hasAnyPermission($link['any'])));
            if ($shown !== []) {
                $groups[$label] = $shown;
            }
        }

        return $groups;
    }

    /**
     * @return array{primary: list<array{label: string, route: string, active: list<string>, icon: string, any: list<string>, page: string}>, more: list<array{label: string, route: string, active: list<string>, icon: string, any: list<string>, page: string}>}
     */
    public static function phoneBar(User $user): array
    {
        $flat = [];
        foreach (self::visible($user) as $links) {
            foreach ($links as $link) {
                $flat[] = $link;
            }
        }

        return [
            'primary' => array_slice($flat, 0, 3),
            'more' => array_slice($flat, 3),
        ];
    }

    /**
     * @param  list<string>  $active
     * @param  list<string>  $any
     * @return array{label: string, route: string, active: list<string>, icon: string, any: list<string>, page: string}
     */
    private static function link(string $label, string $route, array $active, string $icon, array $any, string $page): array
    {
        return compact('label', 'route', 'active', 'icon', 'any', 'page');
    }
}
