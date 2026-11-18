# Nav permissions

A screen shows only when both are true:

1. The role has that module's permission. Super Admin still bypasses in code.
2. Rows are limited by `AccessScope` (company, department, employee, plus a manager's direct reports and self).

## Menu order

| Group | Modules | Note |
|---|---|---|
| Overview | Dashboard | |
| People | Employees, Attendance, Leave, Holidays | `docs/employees/PROFILE.md`, `docs/attendance/REGISTER.md`, `docs/leave/REQUESTS.md`, `docs/holidays/CALENDAR.md`. Shifts stays out of the menu. Clocks are on the employee and on Hours & pay. Roster code stays. |
| Payroll | Payroll, Salary, Advances | `docs/payroll/RUNS.md`, `docs/salary/STRUCTURES.md`, `docs/advances/DEDUCTIONS.md` |
| Operations | Devices, Reports, Bank | `docs/devices/TERMINALS.md`, `docs/reports/ANALYTICS.md`, `docs/bank/TRANSFERS.md` |
| Administration | Users, Roles, Departments, Designations, Statuses, Financial years, Work calendars, Custom fields, Branding, Audit logs | `docs/users/ACCOUNTS.md`, `docs/organization/` |

## Gap to close

`resources/views/partials/nav.blade.php` wraps People, Payroll, and Operations in `@permission('employees.view')`. Attendance edit is `employees.edit`. A Manager with only `employees.view` currently sees payroll and devices.

Each item must use its own permission, and the matching controller must check that same permission. Hide a group when every item in it is hidden.

## Slugs to add

Add these only where `app/Support/PermissionCatalog.php` does not already have them. Then grant each new slug to every existing role that can already open that screen, and seed. A person who can open a screen today must still open it after the split.

- `attendance.view`, `attendance.edit`
- `shifts.view`, `shifts.manage`
- `leave.view`, `leave.decide`
- `holidays.view`, `holidays.manage`
- `salary.view`, `salary.manage`
- `advances.view`, `advances.manage`
- `payroll.view` (keep `payroll.process`, `payroll.approve`, `payroll.lock`, `payroll.reopen`)
- `reports.view`
- `bank.view` (keep `bank_accounts.view`, `bank_accounts.manage`, `payments.release`)

## Role result

| Role | Keeps | Does not gain |
|---|---|---|
| Super Admin | Full bypass | |
| HR Admin | Every permission: create, edit, delete, and manage on every module | Assigning or removing the Super Admin role |
| Payroll Admin | Payroll actions they have now | Device admin, employee create |
| Accounts | Bank, payments, and salary-sheet approve or reject | Attendance edit, generating a salary sheet |
| Manager | Dashboard, employees in department and direct reports, attendance view, and view-only shifts, leave, and holidays | Payroll, salary, advances, devices, reports, user admin, and any manage or edit action |
| Director | Current read scope, plus attendance and reports inside that scope | Payroll process, devices |
| Device Administrator | Devices and employee view | Payroll, user admin |
| Head | Same modules as Manager, for their reporting tree | Payroll, devices, user admin |
| Employee | Dashboard, their own employee row, their attendance, leave view, holidays, and Gate Pass apply | Anyone else's records, payroll, devices, administration, and Gate Pass decide |

Phone bottom bar uses this same filter. See `docs/prompts/HRMS-LIQUID-GLASS.md`.
