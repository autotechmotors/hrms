# Holiday calendar

Holiday create and delete stay. A holiday still produces attendance status `holiday`. Do not change that link or the calendar day partial.

## Code

- `app/Http/Controllers/Operations/HolidayController.php`
- `resources/views/operations/holidays/index.blade.php`
- `resources/views/operations/holidays/partials/day.blade.php`
- Routes: `holidays.index`, `holidays.store`, `holidays.destroy`

## Menu

View uses `holidays.view`. Create and delete use `holidays.manage`. Grants: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

The month grid must not overflow the page on a phone. Day cells may shrink. Do not drop a holiday off the grid to fit the width.
