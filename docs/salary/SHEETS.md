# Salary sheets

Generate pay for one department, for the people you tick, and for a date range of up to 31 days. Generate uses `salary.manage` or `payroll.process`. The people must belong to that department and be people the user can already see. Approve and reject use `salary.decide`. Reject needs a reason of at least 3 characters. A sheet that is already decided cannot be decided again.

Open **Payroll → Salary sheets**.

1. Set salary type on the employee profile (`hours` or `piece`). Set the day’s hours and the monthly salary under **Hours & pay** for hours-based people only. Piece based people are attendance only and never go on a sheet.
2. Choose the dates, the department, and the people. Only hours-based people with a monthly amount set are selectable.
3. Generate. Before the sheet is created, every selected person must have salary type Hours based and a monthly amount greater than zero. Otherwise generate stops with a clear validation message. On success it sends a request to the accountant and keeps the sheet in Requests.
4. The accountant approves it, or rejects it with a reason. Super Admin and HR Admin can take the same decision. Open the sheet for the day grid. The top Excel and PDF download the whole sheet. Excel and PDF next to a person’s name download that person’s full detail only: facts, day grid, summary (late hours, salary cutoff, overtime pay, net), advances, late-entry and cutoff rows, overtime, leave, holiday, and gate pass. The person PDF is one A3 landscape page: brand header, KPI strip, day register, then summary beside late-entry and cutoff. Day register headers show the date number and the weekday short (Mon–Sun). PDF and Excel use the same saved line values. Nothing is left out.

## Decision

A new sheet is Pending. Approve accepts the pay. Reject does not save unless a reason is written. The reason stays on the request and in the notification. A decided request cannot be decided again. Generate a new sheet if the pay should be sent once more.

## Net pay change

After generate, open the sheet. Each person has their own counted net (earned + overtime pay − advances − other deductions). Change net is person-wise only, never for the whole bulk at once. A new net and a reason of at least 3 characters are required. The counted net, overtime pay, advances, and cuts stay visible. The final net on the sheet, Excel, and PDF is the changed amount, with the reason. Only a pending sheet can change. `salary.manage` or `payroll.process` may change it.

## Counting

Day pay is the monthly salary divided by the real number of days in that month. A 30-day month divides by 30. A 31-day month divides by 31. February divides by 28, or 29 in a leap year. Sundays and holidays stay in that length.

Completing the Sunday window pays the same day amount as completing the Monday to Saturday window. Arriving early does not add pay. Time inside the shift is not overtime. Factory time is not overtime.

Cutoff uses the clock window from Hours & pay, not a second salary column. Late minutes are the clock in minus the window start, minus `attendance_rules.grace_minutes`. Grace does not apply to an early out. Early minutes are the window end minus the clock out, and a slice inside that date’s break is not early. The minute rate is the day pay divided by that person’s Monday–Saturday window of the same kind. A day 08:00–19:00 is 660 minutes. A day 09:00–18:00 is 540. A night date uses that person’s night Monday–Saturday length. Sunday uses that same length, not the Sunday window. Completing Sunday 08:00–13:00 pays the same day amount as completing Monday 08:00–19:00. A 30-minute late in on that Sunday cuts day pay × 30 / 660. No in and no out on a Sunday that is on cuts the whole day and shows absent. The cut is never more than that day’s pay.

Overtime is a separate line. It does not change late, early, or a personal cutoff. If the out face is after that date’s window end, every minute from that end until the out is overtime. In at 10:00 on 24 Nov and out at 07:00 on 25 Nov, with the weekday ending at 18:00, is 60 late minutes and overtime from 24 Nov 18:00 through 25 Nov 07:00. Sunday on uses the Sunday window end (for example after 13:00 when Sunday is 08:00–13:00): completing 08:00–13:00 pays the full day; out at 15:00 adds overtime from 13:00 to 15:00. The hour rate still uses the Monday–Saturday length, not the short Sunday window. A 09:00–18:00 person is 9 hours. If one hour is 100, nine extra hours are 900. A 30-day month divides by 30. A 31-day month divides by 31. Overtime pay is the extra hours times that hour rate. The sheet, the Excel file, and the PDF list each day with the shift end, the out time, the extra hours, the rupees, and the total. A saved sheet picks this up only when it is generated again.

- A day before the joining date pays nothing and shows status `BJ`. A person with no monthly salary yet on that date pays nothing and shows status `NS`. Attendance in/out can still show on those days. Hours & pay defaults the salary start date to the joining date.
- Sunday off, when that date’s shift does not work Sunday, pays the full day and shows N. No punch is required.
- Office work that is still outside counts the clock end as the out, so those minutes are not early leave. Personal minutes outside the break are added to early leave.
- No punch on a working day cuts the whole day and is shown as absent.
- A public holiday, a person’s own paid day, and approved leave with no cutoff pay the full day with no cutoff. The day grid names a company holiday with the reason that was saved.
- A cutoff holiday pays nothing for that day, for every role. The day grid says `Company holiday · {name} · cutoff`.
- Approved leave with salary cutoff pays nothing for those days. The day grid says which leave it was.
- A granted gate pass shows on that day even when the cut is zero: type, out, in, and “no cutoff” or the minutes cut. The sheet, the Excel file, and the PDF use that same text. Pending and rejected passes are not shown.
- Each cut is listed with the date, window, in, out, minutes, rupees, and the reason: late, left early, absent, paid holiday, or holiday cutoff.
- An active advance whose given date falls inside the range is deducted in full, once. Approving the sheet sets that advance to completed and its balance to zero. Closing an advance sets it closed and leaves it off the sheet. A completed or closed advance is not deducted again.
- Advances are the only extra deduction. They come off the earned pay, not off a salary structure.

See `docs/prompts/HRMS-PAY-DAYS.md`.

The numbers on a saved sheet do not change if attendance is edited later. Generate again to pick up new punches, leave, or advances.

Approving a sheet marks the advances inside its dates as deducted. There is no separate payroll run.

## Demo

`PlantTreeSeeder` keeps one Plant department and 24 people: Director, two Heads, four Managers, and the people under those managers. September, October, and 1–20 November have attendance. The cases cover day, night, a month that changes shift, late, early leave, a missing out, absence, paid leave, cutoff leave, a personal gate pass, factory work, a company cutoff day, and advances. September is approved, so that advance is completed. October and November stay pending.
