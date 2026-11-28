# Hours and pay

One screen sets the clock windows and the monthly salary. Attendance punches stay the record of real in and out. Payroll and the salary sheet read `employee_salaries.gross_monthly`. Cutoff reads these windows, not the shift.

## How it reads

1. A department has a day window and a night window. Day defaults to Monday–Saturday 09:00–18:00 and Sunday 08:00–13:00 with Sunday on. Night defaults to Monday–Saturday 21:00–06:00 and Sunday night off (paid, no punch). Each kind has its own Sunday on or off switch. `hours_per_day` stays the day Monday–Saturday length (9).
2. On the person page, clocks come first: use department times, or own times. Both modes set Day Sunday and Night Sunday on or off for that person. Own times also show Day and Night side by side with Monday–Saturday and Sunday clocks. That card does not choose today’s shift. The month card under it picks which dates are day and which are night. Own clocks do not change anyone else. Off pays that Sunday with no punch and no cutoff. On requires the Sunday window to be filled; late or early vs that window cuts from the Monday–Saturday day-pay rate (for example Sunday 08:00–13:00 with punches 08:30–13:00 cuts 30 minutes from a ₹500 day when Mon–Sat is the full day length). Completing the Sunday window pays the same full day as Monday. Time after the Sunday end is overtime at the Monday–Saturday hour rate (08:00–15:00 → full day plus 2 hours OT from 13:00). The department Sunday switch is the default before the person has one. See `docs/prompts/HRMS-PAY-DAYS.md`.
3. Each clock can hold a break inside it. Day clocks that have no break yet are 13:00–14:00. Night clocks that have no break yet are 01:00–02:00. Those minutes come off the attended time. They do not add a salary cutoff when the person completes the outer clock. A personal gate pass that sits inside the break is shown and cuts nothing. Minutes of that pass outside the break are cut. A day 08:00–19:00 with a break 13:00–14:00 cuts 0 for 13:00–14:00, 60 minutes for 13:30–15:00, and 120 minutes for 10:00–12:00. No saved break means the whole personal outing is cut. A personal pass with no return is cut from the out time to the window end, and the break slice stays free. Office work is not a cutoff, inside the break or outside it, including while that person is still outside: the clock end stands in as the out. Two passes on one day are counted separately. Office minutes are never added to the personal cut. A night break such as 01:00–02:00 uses the same inside-break test across midnight.
4. A night person's punches belong to the date they clock in. An in at 20:00 on 18 Nov and an out at 08:00 on 19 Nov is one working day on 18 Nov.
5. Monthly salary is set on that person only when salary type is Hours based: the amount and the date it starts. Piece based people have no monthly amount here and are not counted on salary sheets or report rupee cutoffs. No salary structure is required. Salary sheets use the row already effective on the pay date.
6. Day and night can change by date. The month card, the one date-range calendar, hidden dates that are already used, and who may save are in `docs/shifts/SCHEDULE.md`.

## Code

- `app/Http/Controllers/Operations/HoursPayController.php`
- `app/Services/Operations/WorkHourResolver.php`
- Routes: `hours-pay.index`, `hours-pay.show`, `hours-pay.departments.store`, `hours-pay.employees.hours`, `hours-pay.employees.salary`

## Menu

The Payroll item "Hours & pay" uses `salary.view`. Saves use `salary.manage`. Rows stay inside `visibleTo`.

The employee list shows a Salary action beside View, Edit, and Delete when the signed-in person has `salary.view`. It opens `hours-pay.show` for that person.

Salary heads and structures stay on `docs/salary/STRUCTURES.md`.
