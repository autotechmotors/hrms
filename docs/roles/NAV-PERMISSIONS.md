# Nav permissions

A screen shows only when both are true:

1. The role has that module's permission. Super Admin still bypasses in code.
2. Rows are limited by `AccessScope`. Super Admin and HR Admin see the company. Payroll Admin, Accounts, and Device Administrator keep their company or department scope. Every other login sees their own row and every person assigned under them, at every level. People under a manager are also visible to each parent above that manager. See `docs/employees/PARENT-ROLE.md`.

## Menu order

| Group | Modules | Note |
|---|---|---|
| Overview | Dashboard | `dashboard.view` |
| People | Employees, Attendance, Leave, Gate Pass, Holidays | `docs/employees/PROFILE.md`, `docs/attendance/REGISTER.md`, `docs/attendance/GATE-PASS.md`, `docs/leave/REQUESTS.md`, `docs/holidays/CALENDAR.md`. Shifts stay out of the menu. |
| Payroll | Hours & pay, Salary sheets, Advances | `docs/salary/HOURS-AND-PAY.md`, `docs/salary/SHEETS.md`, `docs/advances/DEDUCTIONS.md`. The Payroll run screen and Salary Structures screen are gone. |
| Operations | Devices, Reports, Bank | `docs/devices/TERMINALS.md`, `docs/reports/ANALYTICS.md`, `docs/bank/TRANSFERS.md` |
| Administration | Settings, Roles, Departments, Designations, Statuses, Financial years, Work calendars, Custom fields, Branding, Audit logs | Settings: `docs/employees/AUTO-BLOCK.md`. The rest: `docs/organization/`. Logins are on the employee, not a Users screen. `docs/users/ACCOUNTS.md`. |

`app/Support/Navigation.php` shows each item only when that person has one of its permissions. A group is hidden when every item in it is hidden. The slugs below are already in `app/Support/PermissionCatalog.php`. Do not add them again.

- `attendance.view`, `attendance.edit`
- `shifts.view`, `shifts.manage`
- `leave.view`, `leave.decide`
- `gate_passes.view`, `gate_passes.create`, `gate_passes.decide`, `gate_passes.manage`
- `holidays.view`, `holidays.manage`
- `salary.view`, `salary.manage`, `salary.decide`
- `advances.view`, `advances.manage`
- `payroll.view`, `payroll.process`, `payroll.approve`, `payroll.lock`, `payroll.reopen`
- `reports.view`
- `bank.view`, `bank_accounts.view`, `bank_accounts.manage`, `payments.release`

## Role result

| Role | Keeps | Does not gain |
|---|---|---|
| Super Admin | Full bypass | |
| HR Admin | Every permission: create, edit, delete, and manage on every module | Assigning or removing the Super Admin role |
| Payroll Admin | Payroll actions they have now | Device admin, employee create |
| Accounts | Bank, payments, and salary-sheet approve or reject | Attendance edit, generating a salary sheet |
| Manager | Dashboard, own row and everyone below, attendance, leave view, holidays view, Gate Pass apply. Can approve or reject leave and gate passes below them, with a reason. Can set Day and night dates for people under them (`docs/shifts/SCHEDULE.md`) | Payroll, Hours & pay, salary sheets, advances, devices, reports, profile edit, holiday create, and delete of someone else’s leave |
| Director | Same people-tree view as Manager, plus reports | Payroll process, devices, profile edit, holiday create |
| Device Administrator | Devices and employee view, holiday view | Payroll, user admin, holiday create |
| Head | Same modules as Manager, for everyone below them | Payroll, devices, user admin, profile edit, holiday create |
| Employee | Dashboard, their own row, people assigned under them, their attendance, leave view, holidays view, and Gate Pass apply | Anyone outside that tree, payroll, devices, administration, profile edit, holiday create, and deciding their own leave or pass |

The phone bar keeps More on every login. The other tabs and the rows inside More are the modules that login is allowed to open. Several roles on one person are added together.

See `docs/prompts/HRMS-LIQUID-GLASS.md`.
