# Salary sheets

Generate pay for one department, for the people you tick, and for a date range of up to 31 days.

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

- No punch on a working day cuts the whole day and is shown as absent.
- A public holiday, a person’s own paid day, and approved paid leave pay the full day with no cutoff.
- Unpaid leave pays nothing.
- Each cut is listed with the date, window, in, out, minutes, rupees, and the reason: late, left early, absent, or paid holiday.
- An active advance whose date falls inside the range is deducted in full, once. Closing an advance, or a later payroll run that already took it, leaves it off the next sheet.
- Other deductions come from the person’s salary structure and are calculated on the earned pay, not on the full month.

Payroll runs use this same day pay and cutoff. See `docs/prompts/HRMS-PAY-DAYS.md`.

The numbers on a saved sheet do not change if attendance is edited later. Generate again to pick up new punches, leave, or advances.

This screen does not approve or lock a payroll run. Payroll runs stay on **Payroll**.

## Demo

`SalarySheetDemoSeeder` adds eight people in the department **Seat Cover**, codes SG01 to SG08. Each has a monthly salary of 21,000 and a 9-hour day. Attendance, one paid leave, one unpaid leave, one half day, two absences, one overtime day, one late day, and one advance of 2,000 are stored for 2–6 Nov 2026. A sheet for that week is saved in History.
