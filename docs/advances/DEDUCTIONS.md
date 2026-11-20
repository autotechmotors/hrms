# Advances and deductions

Create and close stay. The form has a title and an amount of at least 1. There is no EMI field and no date field. The given date is today, stored as `starts_on`. A salary sheet deducts the full remaining amount once, as a line named `Advance · {title}`, when that given date falls inside the sheet. Approving the sheet sets status `completed` and balance 0. Close sets status `closed` and does not deduct. `advances.manage` or `payroll.process` can create and close.

## Code

- `app/Http/Controllers/Operations/AdvanceController.php`
- Routes: `advances.index`, `advances.store`, `advances.close`

## Menu

View uses `advances.view`. Create and close use `advances.manage`. Grants: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Chrome only. Amounts stay on solid surfaces. Under 700px, rows scroll inside `.tw` or stack without dropping the amount or the close action.
