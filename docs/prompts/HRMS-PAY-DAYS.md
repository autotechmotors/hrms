# Pay days, Sunday, and holidays

Use this when changing holidays, the work calendar, Hours & pay, salary sheets, or payroll compute. Read `docs/prompts/HRMS-SYSTEM.md`, `docs/prompts/HRMS-LIVE-UI.md`, `docs/holidays/CALENDAR.md`, `docs/salary/HOURS-AND-PAY.md`, `docs/salary/SHEETS.md`, and `docs/payroll/RUNS.md` first.

There is no role named Admin. Super Admin is `super-admin`. Admin in this product is HR Admin (`hr-admin`). Both already have `holidays.manage` and `salary.manage`. Do not add a new role. An `employee` login cannot add holidays, Sunday off, or working times.

## What the code does today

Do not build a second attendance engine. These files already own the rules.

| Rule | Where |
|---|---|
| With no work calendar, Monday–Sunday are working. A public holiday is not | `WorkCalendarResolver::isWorkingDay` |
| A public holiday or an `employee_holidays` row sets attendance status `holiday` | `AttendanceCalculator` |
| Company holidays stay one date per company. Kinds stay `public`, `restricted`, `optional` | `holidays` |
| One person’s paid day is `employee_holidays`, unique on employee + date. Not leave | `HolidayController` |
| Hours & pay stores the four clock times next to `hours_per_day` | `department_work_hours`, `employee_work_hours`, `WorkHourResolver` |
| Salary sheet and payroll both use calendar-day pay and the window cutoff | `PayDayMath`, `SalarySheetBuilder`, `PayrollRunner` |
| Shift clock times stay the roster shift | `shifts`, `shift_rosters` |
| Employee Sunday switch. Off pays that Sunday. On uses the Sunday clock. Empty keeps night off and day on | `employee_work_hours.works_sunday`, employee detail and edit |

## Default week

Every calendar day is a working day. No Saturday off. No Sunday off. No holiday until Super Admin or HR Admin adds one.

Sunday is still a working day. Its clock window is shorter. It is not `weekly_off`, not `half_day`, and not leave.

Set the active work calendar so Monday through Sunday are `is_working`. `WorkCalendarDay.weekday` uses Carbon: 0 Sunday … 6 Saturday. If a company has no calendar, `isWorkingDay` must return true for every weekday, including Saturday and Sunday. A public holiday remains the only company-wide non-working day.

## Clock windows

Hours & pay is where Mon–Sat time and Sunday time are set. Shifts stay the named day and night shifts on the roster. Do not replace shifts with these windows.

Department window, used by everyone in that department:

- Monday–Saturday default `09:00`–`18:00`
- Sunday default `08:00`–`13:00`

An employee may keep the department windows, or set their own Mon–Sat pair and Sunday pair. Store the four times on `department_work_hours` and `employee_work_hours` next to the existing `hours_per_day` and `uses_department` columns. Keep `hours_per_day` equal to the Monday–Saturday length (9.00 for 09:00–18:00) so older screens still read a day length. Pay cutoff reads the clock windows, not `hours_per_day`.

Each person is Day or Night (`employee_work_hours.shift_kind`). Night uses the department night clocks Monday–Saturday, 21:00–06:00 unless that person has their own night clocks. Sunday is not a night duty. A night-shift person is paid that Sunday in full, with no punch and no cutoff. A roster row whose shift ends at or before it starts is a night shift for a Monday–Saturday date and its start and end replace the window. Day roster rows do not replace the Sunday 08:00–13:00 window. The minute rate stays the day Monday–Saturday length, including on a night shift.

On Hours & pay, show two time rows: “Monday to Saturday” and “Sunday”. Department first. On the employee page, a switch: use the department times, or this person’s own times. Save with the existing jQuery ajax on that page. Do not reload the layout.

Write these defaults now for every active department in the company. Point employees at the department windows (`uses_department` true) unless they already have their own hours. If they already have their own hours, set their four times to the same defaults without deleting their salary.

Sunday on or off is not a department flag. On the employee detail and edit pages, that person has a Sunday switch. Off pays the day with no punch and no cutoff. On stores the Sunday clock. A night person with no switch is still off on Sunday. A holiday can still pay a day for someone whose Sunday is on.

## Holidays are paid

A holiday is not leave. Do not create a `leave_requests` row for it.

Company holiday, from `/holidays`, Super Admin or HR Admin, permission `holidays.manage`:

- Kind stays `public`
- Attendance status stays `holiday`
- Every employee is paid that calendar day’s full pay
- No punch is required
- No late or early cutoff
- The day still counts in the month length. It is not removed before dividing

Copy on the holiday form: “Paid day for everyone. No punch needed.” Restricted and optional kinds stay on the form and do not pay a day by themselves.

One employee’s Sunday off, also Super Admin or HR Admin:

- New table `employee_holidays`: `employee_id`, `holiday_date`, `name`
- Unique on employee + date
- Not a company `holidays` row, because that date must stay a working Sunday for everyone else
- Attendance for that employee on that date is `holiday`
- That employee is paid the full day. No punch. No cutoff
- Other employees keep the Sunday window

Put this on the Holidays page as a second card: pick the employee, the date, and a name. Stay on the page (ajax). The employee list is `visibleTo`. Do not add it under Leave.

Deleting either holiday puts that day back to a normal working day. Recalculate attendance for that date with `AttendanceCalculator`. Do not hand-edit `attendance_days.status`.

## How a month is paid

Use the month’s real length. 31-day months divide by 31. 30-day months divide by 30. February divides by 28, or 29 in a leap year. Do not divide by working days. Do not drop Sundays or holidays before dividing.

```
day pay = monthly salary / days in that month
```

Example: 25000 in a 30-day month is 833.33 a day. The same 25000 in a 31-day month is 806.45 a day. A full month with no cutoff and no unpaid absence pays the monthly salary exactly.

Sunday’s shorter window does not make Sunday a half-pay day. Completing 08:00–13:00 pays the same day pay as completing 09:00–18:00.

The salary is `employee_salaries.gross_monthly` already effective on the pay date. Hours & pay remains the place that writes it. Payroll and the salary sheet both read it. Do not invent a second salary column.

## Cutoff

Compare clock in and clock out with that person’s window for that weekday.

- Late minutes = max(0, in − window start − grace)
- Early minutes = max(0, window end − out)
- Grace is `attendance_rules.grace_minutes` for the company. Do not add a second grace field
- Short minutes = late + early
- A punch after the window end, or before the window start, does not add pay

Minute rate uses the Monday–Saturday window, on Sunday as well:

```
minute rate = day pay / Monday–Saturday window minutes
cutoff rupees = min(day pay, short minutes × minute rate)
day net = day pay − cutoff rupees
```

Monday–Saturday 09:00–18:00 is 540 minutes. Sunday 08:00–13:00 is 300 minutes, but the rate is still the 540-minute rate.

- In 08:30 and out 13:00 on Sunday cuts 30 minutes
- In 08:00 and out 12:00 on Sunday cuts 60 minutes
- In 09:00 and out 18:00 Monday–Saturday pays the full day
- In 09:30 and out 17:30 Monday–Saturday cuts 60 minutes
- Staying past the window end does not increase pay. Do not add overtime

No clock in and no clock out on a working day, and the day is not a holiday and not approved leave: cutoff is the whole day pay. Show it as absent, not as a 5-hour or 9-hour cut.

Approved paid leave pays the full day pay with no cutoff. Unpaid leave and absence pay nothing. Do not change leave approval.

Gate-pass minutes already stored on `attendance_days` stay. Personal minutes out of the office count as short minutes. Office-credit minutes do not. Do not change gate-pass approval.

Never cut more than that day’s pay. Never pay more than that day’s pay for attendance. Structure deductions and advances stay as they are and apply after the earned total.

## What generate must show

On the salary sheet day grid, and in the payroll item breakdown, every cutoff is a line the accountant can read:

- Date
- Window (09:00–18:00 or 08:00–13:00, or the person’s own window)
- In and out
- Short minutes
- Rupees cut
- Reason: late, left early, absent, or holiday (paid, no cut)

The month total has a “Salary cutoff” figure. Net uses the earned days after cutoff, then the structure deductions and advances that already exist.

Salary sheet and payroll run must use this same day pay and cutoff. Today they do not: the sheet pays minutes worked, and the run divides by working days. Change both. Update `docs/salary/SHEETS.md` and `docs/payroll/RUNS.md` in the same change. `docs/prompts/HRMS-FREEZE.md` may say payroll math must not change; this prompt is the exception, and the freeze note must mention this file after the change.

A saved sheet still does not change if punches are edited later. Generate again to pick up new punches or holidays.

## Do not change

- Device push, Hikvision, and ezapi routes
- `AccessScope`, except that holiday and window queries stay inside the company and `visibleTo`
- Leave request workflow
- Shift and roster save payloads
- Salary structure field names
- Employee block, exit, and import
- Auth and MFA

## Checks

- No calendar: a Tuesday and a Sunday are both working days. A public holiday is not.
- 25000 and a 30-day month, every window completed, no holiday: earned 25000.
- 25000 and a 31-day month, same: earned 25000.
- Sunday 08:30–13:00: one line, 30 minutes, rupees = day pay × 30 / 540.
- Sunday 08:00–12:00: 60 minutes.
- Sunday with no punches: one full day cut, unless that Sunday is a company holiday or that employee’s holiday.
- Company holiday on Sunday: every employee paid, no cutoff.
- Employee holiday on Sunday: only that employee paid. The next employee is still on 08:00–13:00.
- That employee holiday does not appear as leave.
- HR Admin and Super Admin can add both. An employee login gets 403.
