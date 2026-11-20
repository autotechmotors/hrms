# Salary sheets

Generate pay for one department, for the people you tick, and for a date range of up to 31 days. Generate uses `salary.manage` or `payroll.process`. The people must belong to that department and be people the user can already see. Approve and reject use `salary.decide`. Reject needs a reason of at least 3 characters. A sheet that is already decided cannot be decided again.

Open **Payroll → Salary sheets**.

1. Set the day’s hours and the monthly salary under **Hours & pay**. A person’s own hours are used when they are set. Otherwise the department hours are used.
2. Choose the dates, the department, and the people.
3. Generate. That sends a request to the accountant and keeps the sheet in Requests.
4. The accountant approves it, or rejects it with a reason. Super Admin and HR Admin can take the same decision. Open the sheet for the day grid, then download Excel or PDF.

## Decision

A new sheet is Pending. Approve accepts the pay. Reject does not save unless a reason is written. The reason stays on the request and in the notification. A decided request cannot be decided again. Generate a new sheet if the pay should be sent once more.

## Counting

Day pay is the monthly salary divided by the real number of days in that month. A 30-day month divides by 30. A 31-day month divides by 31. February divides by 28, or 29 in a leap year. Sundays and holidays stay in that length.

Completing the Sunday window pays the same day amount as completing the Monday to Saturday window. There is no overtime. Staying late or arriving early does not add pay.

Cutoff uses the clock window from Hours & pay, not the shift. Late minutes are the clock in minus the window start, minus the company grace. Early minutes are the window end minus the clock out. The minute rate is the day pay divided by the Monday–Saturday window (540 minutes for 09:00–18:00), including on Sunday. The cut is never more than that day’s pay.

- A day before the joining date pays nothing. A person with no monthly salary yet pays nothing for that day.
- Sunday off, when that date’s shift does not work Sunday, pays the full day and shows N. No punch is required.
- Office work that is still outside counts the clock end as the out, so those minutes are not early leave. Personal minutes outside the break are added to early leave.
- No punch on a working day cuts the whole day and is shown as absent.
- A public holiday, a person’s own paid day, and approved leave with no cutoff pay the full day with no cutoff. The day grid names a company holiday with the reason that was saved.
- A cutoff holiday pays nothing for that day, for every role. The day grid says `Company holiday · {name} · cutoff`.
- Approved leave with salary cutoff pays nothing for those days. The day grid says which leave it was.
- A granted gate pass shows on that day with its type and duration, in the sheet, the Excel file, and the PDF.
- Each cut is listed with the date, window, in, out, minutes, rupees, and the reason: late, left early, absent, paid holiday, or holiday cutoff.
- An active advance whose given date falls inside the range is deducted in full, once. Approving the sheet sets that advance to completed and its balance to zero. Closing an advance sets it closed and leaves it off the sheet. A completed or closed advance is not deducted again.
- Advances are the only extra deduction. They come off the earned pay, not off a salary structure.

See `docs/prompts/HRMS-PAY-DAYS.md`.

The numbers on a saved sheet do not change if attendance is edited later. Generate again to pick up new punches, leave, or advances.

Approving a sheet marks the advances inside its dates as deducted. There is no separate payroll run.

## Demo

`PlantTreeSeeder` keeps one Plant department and 24 people: Director, two Heads, four Managers, and the people under those managers. September, October, and 1–20 November have attendance. The cases cover day, night, a month that changes shift, late, early leave, a missing out, absence, paid leave, cutoff leave, a personal gate pass, factory work, a company cutoff day, and advances. September is approved, so that advance is completed. October and November stay pending.
