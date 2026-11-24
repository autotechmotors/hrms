# Employee times, Sunday, shifts, and pay

Use this when changing Hours & pay, the employee shift month, holidays, leave pay, gate pass, or the salary sheet. Read `docs/prompts/HRMS-SYSTEM.md`, `docs/prompts/HRMS-LIVE-UI.md`, `docs/salary/HOURS-AND-PAY.md`, `docs/shifts/SCHEDULE.md`, `docs/salary/SHEETS.md`, `docs/holidays/CALENDAR.md`, and `docs/attendance/GATE-PASS.md` first.

Super Admin is `super-admin`. Admin is HR Admin (`hr-admin`). A parent may set the shift month for a person they manage (`ShiftSchedule::canAssign`). An employee login cannot set times, Sunday, holidays, or another person’s shift. Do not add a role.

Do not give every person the same clock, the same Sunday, or a night shift. Department is the starting copy. Each person can differ.

## What already exists

Extend these. Do not add a second attendance engine or a second salary column.

| Piece | Where |
|---|---|
| Department and person clocks, breaks, Sunday switch, day or night | `department_work_hours`, `employee_work_hours`, `WorkHourResolver` |
| Which dates are day and which are night | `employee_shift_spans`, `ShiftSchedule` |
| Day pay, Sunday off, holiday, leave, late and early | `PayDayMath` |
| Company holiday `public` or `cutoff` | `holidays`, `HolidayController` |
| One person’s paid days | `employee_holidays` |
| Personal or office gate pass | `gate_passes.work_type` `personal` or `office` |
| Salary the sheet reads | `employee_salaries.gross_monthly` |

## Department, then the person

Hours & pay holds the department template:

- Day Monday–Saturday start, end, and break
- Day Sunday start, end, break, and Sunday on or off
- Night Monday–Saturday start, end, and break
- Night Sunday start, end, break, and Sunday on or off
- Default pattern: all days on Day, or all days on Night

A new person copies that department. After that, the person is separate.

On the employee page, in this order:

1. Clocks. “Use department times” or “Own times”. Own times store that person’s day and night windows and breaks. They do not change anyone else.
2. Sunday. A switch for the shift they are on. Day Sunday and night Sunday are both stored on that person. Off pays that Sunday. On asks for that person’s Sunday clock and break. A department Sunday switch is only the default before the person has one. Any role uses the same switch. Do not look at the role to decide Sunday pay.
3. Shift month. The card already on the profile and Hours & pay (`employees.shifts.show`). All days on Day, all days on Night, or ranges that cover every date of that month once. Saving November does not wipe December. An “all days” choice starts on the first of that month and continues until a later month is saved.

The employee list badge is today’s shift only. It is not a promise that the whole month is that shift.

Save clocks, Sunday, and the shift month with the existing ajax. Do not reload the layout.

## Which clock a date uses

`ShiftSchedule::kindOn` picks day or night for that date. The window is that person’s own clocks when they left the department times. Otherwise it is the department clocks for that kind.

A night date groups the evening in with the next morning out. The next date, when it is day, starts at the day clock. A night out is not the next day’s in. A punch outside that clock does not join the shift. The door terminal stays open. Do not block it.

Overtime starts at the Monday–Saturday end of that kind, including on Sunday, and runs until the out face. Arriving before the window does not add pay. Time inside the shift does not add pay. Factory time does not add pay. The hour rate is the monthly salary divided by the days in that month times the full shift hours. Overtime pay is a separate line and does not change late, early, or a personal cutoff. See `docs/salary/SHEETS.md`.

## Sunday pay

Sunday off for that person, on the shift of that date: full day pay. No punch. No late, early, or gate-pass cut. Status on the sheet is Sunday off. Punches that day do not add pay and do not cut pay.

Sunday on: they must fill the Sunday window. The day’s pay is still one full calendar day, not a half day.

```
day pay = monthly salary / days in that month
minute rate = day pay / full-shift minutes
```

Full-shift minutes are the Monday–Saturday window of the same kind, not the Sunday window.

Example. Day shift 08:00–19:00 is 11 hours, 660 minutes. Sunday window 08:00–13:00. The person punches 08:30–13:00. Short is 30 minutes. They filled 10 hours 30 minutes of the 11. Cut `day pay × 30 / 660`. Completing 08:00–13:00 cuts nothing and pays the same day amount as completing 08:00–19:00 on Monday.

A person whose Monday–Saturday day is 09:00–18:00 uses 540 minutes, not 660. A night Sunday uses that person’s night Monday–Saturday length. Do not use one company rate for everyone.

Grace is `attendance_rules.grace_minutes`. It applies to a late in only. Do not add a second grace.

No in and no out on a Sunday that is on: the whole day is cut. Show absent, not a 5-hour cut.

A 30-day month divides by 30. A 31-day month divides by 31. February divides by 28, or 29 in a leap year. Do not drop Sundays or holidays before dividing. A full month on one salary with no cutoff pays that salary exactly. A salary that changes on the 15th pays the earlier days at the earlier amount and the later days at the new amount. A day before joining pays nothing. No salary row pays nothing.

## Holidays and leave

These are not the Sunday switch.

| Case | Pay |
|---|---|
| Company holiday, kind `public` | Every person, full day, no punch, no cutoff |
| Company holiday, kind `cutoff` | Every person loses that day. A personal paid day on that same date still pays that one person |
| `employee_holidays` | That person only, full day, no punch, no cutoff. Not a leave request |
| Approved paid leave | Full day, no cutoff |
| Approved unpaid leave | Whole day cut |
| Sunday off and a paid holiday on the same date | Pay the day once. Do not add a second day |

Attendance status for a company holiday and a personal paid day stays `holiday`. Recalculate with `AttendanceCalculator`. Do not write the status by hand. Deleting the holiday puts the day back to the person’s normal window or Sunday switch.

## Gate pass

Office work is factory work (`work_type` office). Personal work is `personal`. Only an approved or expired pass counts. Pending and rejected do nothing.

Show every counted pass on that salary day, including when the rupees cut are zero: type, out, in, and “no cutoff” or the minutes cut. The sheet, Excel, and PDF all show it.

Office work never cuts, inside the break or outside it. If they are still out, the clock end stands in as the out, so those minutes are not early leave.

Personal work:

- The whole outing sits inside that date’s break: show the pass, cut nothing.
- The outing is not wholly inside the break: cut only the minutes that fall outside the break. The break slice stays free.
- Day 08:00–19:00, break 13:00–14:00. Out 13:00–14:00 cuts 0. Out 13:30–15:00 cuts 60 minutes (14:00–15:00), not 90. Out 10:00–12:00 cuts 120 minutes.
- No return on a personal pass: cut from the out time to the window end, and still forgive the minutes that sit inside the break.
- No break saved on that clock: the whole personal outing is cut.
- A late in is still a late cut. A free break pass does not remove it.
- Two passes on one day are counted separately. Office minutes are never added to the personal cut.
- A night break such as 01:00–02:00 uses the same inside-break test across midnight.

The cut uses the same minute rate as a late in. Late, early, and personal minutes outside the break add together. The total cut for a date cannot pass that day’s pay.

## Do not change

- Device push, Hikvision, and ezapi
- Who may approve a gate pass or a leave
- `AccessScope`
- Salary structure field names
- Auth and MFA
- The door terminal. A missed punch does not lock it

Update `docs/salary/HOURS-AND-PAY.md`, `docs/salary/SHEETS.md`, `docs/shifts/SCHEDULE.md`, and `docs/attendance/GATE-PASS.md` in the same change.

## Checks

- Two people in one department: different clocks, different Sunday switches, one all-month day and one all-month night. Each generate uses only that person.
- A month split 1–10 day and 11–end night uses the day clocks on the 1st and the night clocks on the 11th.
- Sunday off, any role: that Sunday is paid, no punch, no cutoff.
- Sunday on, window 08:00–13:00, full day 08:00–19:00, punches 08:30–13:00: 30 minutes, rupees = day pay × 30 / 660.
- Same Sunday completed 08:00–13:00: no cutoff, full day pay.
- 25000, 30 days, no cutoff: 25000. 25000, 31 days, no cutoff: 25000.
- Public holiday: everyone paid. Cutoff holiday: everyone cut, except a person with `employee_holidays` that date.
- Paid leave: no cutoff. Unpaid leave: full day cut.
- Personal pass 13:00–14:00 inside a 13:00–14:00 break: listed, 0 cutoff.
- Personal pass 13:30–15:00: listed, 60 minutes, not 90.
- Personal pass 10:00–12:00: listed, 120 minutes.
- Office pass outside the break, still not returned: listed, 0 cutoff, not absent.
- Pending pass: not on the salary line.
