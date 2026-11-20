# Day and night by date

One person can work day on some dates and night on others. The clocks stay the ones already saved for Day and Night. This screen only chooses which dates use which clock.

## How a month is saved

The card is on the employee profile and on Hours & pay. It shows one month, with the real length: 28, 29, 30, or 31 days.

- All days on Day keeps every date on the day clock and turns Night off.
- All days on Night keeps every date on the night clock and turns Day off.
- Turning Day off leaves Night on for every day. Turning Night off leaves Day on for every day. Both off does not save.
- With both on, each row is one shift and one date range. The start and the end are chosen in the same calendar. One click, then close, is that single day. The rows together must cover every date once. A gap, such as 1–10 Nov on Day and nothing after that, does not save. Overlap does not save.
- A date already chosen on one row stays hidden in every other row’s calendar. A range that would cross those dates does not apply. Change the row that already has them.
- Add dates stays hidden once every day of the month is covered. Shorten a row to open dates again.
- Month previous and next stay on the page. The list fragment is ajax. Do not reload the layout.
- Saving November does not wipe December. An All days choice starts on the first of the selected month and continues until a later month is saved.

Super Admin, HR Admin, and a parent in the reporting tree can save a person under them. A person cannot save their own shift.

## Punch and pay

On a saved date the in and out belong to that clock. A night date still groups the evening in with the next morning out. The next day, when it is a day shift, starts at the day clock, so the night out is not counted as the day in. A punch outside that clock does not join the shift, so the day stays inactive. The door terminal is not blocked and is not turned off. Late, early, and a missing punch still cut pay from that clock. The minute rate stays the Monday–Saturday day length.

Dates with no saved span keep the person’s current Day or Night choice. The employee list badge is the shift for today. A roster night shift replaces the window only on a Monday–Saturday date that has no saved span.

## Code

- `app/Services/Operations/ShiftSchedule.php`
- `app/Http/Controllers/Employees/EmployeeShiftController.php`
- `resources/views/employees/_shift_month.blade.php`
- Routes: `employees.shifts.show`, `employees.shifts.update`
