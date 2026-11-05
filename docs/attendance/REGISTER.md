# Attendance register

Past and current days use this same register. Default day is today. Date back, date forward, and Today stay. Department and status filters stay. Do not add a second attendance engine.

## Already on the day row

Department, in, out, worked time (`minutes_worked` shown as `Xh YYm`), status, punch photo zoom, and edit when `employees.edit` is allowed today (`attendance.edit` after `docs/roles/NAV-PERMISSIONS.md`).

Statuses stay: present, late, half day, absent, on leave, holiday, weekly off, unmarked, pending.

## Add

The employee's phone on every row. On a phone, a `tel:` link is fine. Do not drop in, out, or worked hours to make the row fit.

## Phone layout (under 700px)

A stack of cards, not a squeezed table. Each card:

- Name, code, department
- Phone
- In, out, worked hours, status
- Photo zoom when a punch image exists
- Edit only with the edit permission

"All employees" means employees `visibleTo` the signed-in user for that company and department scope.

## Code

- `resources/views/operations/attendance/index.blade.php`
- `resources/views/operations/attendance/show.blade.php`
- `app/Http/Controllers/Operations/AttendanceController.php`
- `app/Services/Operations/AttendanceCalculator.php` — do not change the math

Calculation, recalculate, rules, selfie storage, and device ingest stay. See `docs/prompts/HRMS-FREEZE.md` and `docs/devices/TERMINALS.md`.
