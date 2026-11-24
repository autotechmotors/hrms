# Attendance register

Past and current days use this same register. Default day is today. Date back, date forward, and Today stay. Department and status filters stay. Do not add a second attendance engine.

## Already on the day row

Department, in, out, worked time (`minutes_worked` shown as `Xh YYm`), status, punch photo zoom, and edit when `attendance.edit` is allowed. HR Admin and Super Admin have that permission. A machine day is marked A. A day saved from Add punch or Edit is marked M. A later terminal pull does not replace an M day. Delete that day to let the machine fill it again. Which clock a date uses, and how a night out stays on the in date when the next day is Day, is `docs/shifts/SCHEDULE.md`.

Statuses stay: present, late, half day, absent, on leave, holiday, weekly off, unmarked, pending.

How a day is marked: a company holiday that closes the office, or one person’s paid day, is holiday. Approved leave is on leave. Sunday off with no punch is weekly off; a punch that day is present. A roster off day, or a calendar day that is not working, is weekly off. Personal work that is still open after the pass expires, when the person has clocked in, is half day. A clock in on a working day is present. No punch is absent, unless the company rule is set to leave that day unmarked. The door terminal is not switched off for a wrong shift. A punch outside that date’s clock does not join the day. A second face before the out window is not the clock out. Clock out is a face inside the minutes saved in Settings before the scheduled end, a face any time after that end (including the next morning), or a face an approved gate pass allows. The person record keeps In and Out for the shift. A gate-pass let out does not fill Out.

The Gate pass column replaces Break. An icon shows only when that date has an approved or expired pass. Pending and rejected show nothing. One icon covers every pass on that date. Clicking it opens a popup on the same page with a clear title, type, leave kind, taken time, reason, machine let out, machine let in, and the machine out and in photos (`attendance_punches.image` linked by `gate_pass_id`). The phone card and the live day feed use the same icon. An approved early-leave pass puts the machine let-out into the Out column. A return pass does not. Overtime on that row is every minute from the full shift end until the out face. Factory time is not overtime. Time inside the shift is not overtime. Arriving early is not overtime. Sunday uses the Monday–Saturday length for that rate, not a half day.

## Add

The employee's phone on every row. On a phone, a `tel:` link is fine. Do not drop in, out, or worked hours to make the row fit.

## Phone layout (under 700px)

A stack of cards, not a squeezed table. Each card:

- Name, code, department
- Phone
- In, out, worked hours, status
- Photo zoom when a punch image exists
- Edit only with the edit permission

The signed-in person sees their own attendance and every person below them in the parent line, including people whose direct parent is a manager further down. Super Admin and HR Admin see the company. "All employees" means that `visibleTo` set. The tree is `docs/employees/PARENT-ROLE.md`.

## Code

- `resources/views/operations/attendance/index.blade.php`
- `resources/views/operations/attendance/show.blade.php`
- `app/Http/Controllers/Operations/AttendanceController.php`
- `app/Services/Operations/AttendanceCalculator.php` — do not change the math

Calculation, recalculate, rules, selfie storage, and device ingest stay. Office-work gate pass is the exception: those hours stay in the day. See `docs/attendance/GATE-PASS.md`, `docs/prompts/HRMS-FREEZE.md`, and `docs/devices/TERMINALS.md`.
