# Advances and deductions

Advance create and close stay. Payroll deduction of an open advance stays as it is.

## Code

- `app/Http/Controllers/Operations/AdvanceController.php`
- Routes: `advances.index`, `advances.store`, `advances.close`

## Menu

View uses `advances.view`. Create and close use `advances.manage`. Grants: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Chrome only. Amounts stay on solid surfaces. Under 700px, rows scroll inside `.tw` or stack without dropping the amount or the close action.
