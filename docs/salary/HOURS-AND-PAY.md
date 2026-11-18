# Hours and pay

One screen sets the clock windows and the monthly salary. Attendance punches stay the record of real in and out. Payroll and the salary sheet read `employee_salaries.gross_monthly`. Cutoff reads these windows, not the shift.

## How it reads

1. A department has a day window and a night window. Day defaults to Monday–Saturday 09:00–18:00 and Sunday 08:00–13:00. Night defaults to Monday–Saturday 21:00–06:00 and Sunday 21:00–02:00. `hours_per_day` stays the day Monday–Saturday length (9).
2. A person is Day or Night. That choice is on the employee detail page and the employee edit page, and it shows as a Day or Night badge on the employee list and on Hours & pay. They can keep the department clocks for that shift, or set their own. Sunday is a switch on those pages. Off pays that Sunday with no punch and no cutoff. On asks for the Sunday start and end. `works_sunday` left empty keeps the older rule: night is off, day is on. A night shift on the roster for a Monday–Saturday date uses that shift’s start and end. The minute rate stays the day Monday–Saturday length.
3. Each clock can hold a break inside it, start and end, on the department and on the person, including Sunday. 13:00–14:00 is 60 minutes. Those minutes come off the attended time. They do not add a salary cutoff when the person completes the outer clock.
4. A night person's punches belong to the date they clock in. An in at 20:00 on 18 Nov and an out at 08:00 on 19 Nov is one working day on 18 Nov.
5. Monthly salary is set on that person: structure, amount, and the date it starts. Payroll uses the row already effective on the pay date.

## Code

- `app/Http/Controllers/Operations/HoursPayController.php`
- `app/Services/Operations/WorkHourResolver.php`
- Routes: `hours-pay.index`, `hours-pay.show`, `hours-pay.departments.store`, `hours-pay.employees.hours`, `hours-pay.employees.salary`

## Menu

The Payroll item "Hours & pay" uses `salary.view`. Saves use `salary.manage`. Rows stay inside `visibleTo`.

Salary heads and structures stay on `docs/salary/STRUCTURES.md`.
