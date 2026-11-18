# Advances and deductions

Create and close stay. There is no monthly EMI and no start date. The advance is given in the current month. Payroll deducts the full amount once, from that month’s salary only, as a deduction line named `Advance · {title}`. Approving that month marks the advance deducted. A closed advance is not deducted.

## Code

- `app/Http/Controllers/Operations/AdvanceController.php`
- Routes: `advances.index`, `advances.store`, `advances.close`

## Menu

View uses `advances.view`. Create and close use `advances.manage`. Grants: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Chrome only. Amounts stay on solid surfaces. Under 700px, rows scroll inside `.tw` or stack without dropping the amount or the close action.
