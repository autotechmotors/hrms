# Holiday calendar

The page opens on the current month. Previous and next stay on the page and load that month only. The grid lists every date of the month: 31, 30, 29, or 28. Blank cells before the 1st keep Monday as the first column. They are not dates from another month.

Create and delete stay on the page. A holiday has two kinds. Public is the default and is a paid day for everyone. Cutoff cuts that day’s pay for everyone. Both close the company, so attendance status is `holiday` and no punch is required. Older restricted and optional rows can still appear, and they do not pay or cut a day by themselves. The salary day names a public holiday `Company holiday · {name}` and a cutoff holiday `Company holiday · {name} · cutoff`. See `docs/salary/SHEETS.md`.

One person’s paid day is an `employee_holidays` row, unique per person and date. The button is at the top of the page. It opens a popup for the person, a from and to date, and a reason. The range is 366 days or fewer. Each date in that range is one row. It pays that person even on a company cutoff day. It is not a company holiday and not a leave request. Deleting either kind puts that day back through `AttendanceCalculator`. Only Super Admin and HR Admin can add or delete either kind.

## Code

- `app/Http/Controllers/Operations/HolidayController.php`
- `resources/views/operations/holidays/index.blade.php`
- `resources/views/operations/holidays/_body.blade.php`
- `resources/views/operations/holidays/partials/day.blade.php`
- Routes: `holidays.index`, `holidays.store`, `holidays.destroy`, `employee-holidays.store`, `employee-holidays.destroy`

## Menu

Every role that can sign in may view the company holidays (`holidays.view`). Create and delete stay with Super Admin and HR Admin (`holidays.manage`). Grants: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

One month fills the page. Day cells may shrink on a phone. The holiday name stays on the cell. Do not drop a holiday off the grid to fit the width.
