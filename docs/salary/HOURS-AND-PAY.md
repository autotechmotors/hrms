# Hours and pay

One screen sets the clock windows and the monthly salary. Attendance punches stay the record of real in and out. Payroll and the salary sheet read `employee_salaries.gross_monthly`. Cutoff reads these windows, not the shift.

## How it reads

1. A department has a day window and a night window. Day defaults to Monday–Saturday 09:00–18:00 and Sunday 08:00–13:00. Night defaults to Monday–Saturday 21:00–06:00 and Sunday 21:00–02:00. `hours_per_day` stays the day Monday–Saturday length (9).
2. A person is Day or Night. That choice is on the employee detail page and the employee edit page, and it shows as a Day or Night badge on the employee list and on Hours & pay. They can keep the department clocks for that shift, or set their own. Day Sunday is a switch on the employee pages. Off pays that Sunday with no punch and no cutoff. On asks for the Sunday work time and break. `works_sunday` left empty means a day person works Sunday. Each department has Night · Sunday on Hours & pay: off pays Sunday for night people who still use the department clocks, and on stores that department’s Sunday night work time and break. A night person with their own hours uses the Sunday night switch, work time, and break saved on that person. The same Work and Break rows are on the department card, the person’s Hours & pay page, and the employee detail, edit, and create forms. A night shift on the roster for a Monday–Saturday date uses that shift’s start and end. The minute rate stays the day Monday–Saturday length.
3. Each clock can hold a break inside it. Day clocks that have no break yet are 13:00–14:00. Night clocks that have no break yet are 01:00–02:00. Those minutes come off the attended time. They do not add a salary cutoff when the person completes the outer clock. A personal gate pass during that break is not a cutoff. Minutes outside the break are. Office work is not a cutoff, including while that person is still outside: those minutes stay in the day's working time through the end of the clock. A personal pass that expires with no return is early leave.
4. A night person's punches belong to the date they clock in. An in at 20:00 on 18 Nov and an out at 08:00 on 19 Nov is one working day on 18 Nov.
5. Monthly salary is set on that person: the amount and the date it starts. No salary structure is required. Salary sheets use the row already effective on the pay date.
6. Day and night can change by date. The month card, the one date-range calendar, hidden dates that are already used, and who may save are in `docs/shifts/SCHEDULE.md`.

## Code

- `app/Http/Controllers/Operations/HoursPayController.php`
- `app/Services/Operations/WorkHourResolver.php`
- Routes: `hours-pay.index`, `hours-pay.show`, `hours-pay.departments.store`, `hours-pay.employees.hours`, `hours-pay.employees.salary`

## Menu

The Payroll item "Hours & pay" uses `salary.view`. Saves use `salary.manage`. Rows stay inside `visibleTo`.

The employee list shows a Salary action beside View, Edit, and Delete when the signed-in person has `salary.view`. It opens `hours-pay.show` for that person.

Salary heads and structures stay on `docs/salary/STRUCTURES.md`.
