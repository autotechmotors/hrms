# Advances and deductions

Create sends a **pending** request to the accountant. The accountant **approves** it, or **rejects** it with a required reason. Only an **approved** (`active`) advance is deducted from salary. Create, edit, close, delete, approve, and reject stay on the page.

The form has a title and an amount of at least 1. There is no EMI field and no date field. The given date is today, stored as `starts_on`. A salary sheet deducts the full remaining amount once, as a line named `Advance · {title}`, when that given date falls inside the sheet and the advance is approved. Approving the sheet sets status `completed` and balance 0. Close sets status `closed` on an approved advance and does not deduct. Edit changes title and amount on a pending advance only. Delete removes a pending, rejected, or closed advance. A deducted advance cannot be edited or deleted.

## Status

| Status | Meaning |
|---|---|
| `pending` | Waiting for the accountant |
| `active` | Approved; will deduct on the salary sheet |
| `rejected` | Rejected; `decision_note` holds the reason |
| `closed` | Closed after approval; not deducted |
| `completed` | Taken when the salary sheet was approved |

## Permissions

- View: `advances.view`, `advances.decide`, or `payroll.process`
- Create, edit (pending), close (approved), delete: `advances.manage` or `payroll.process`
- Approve / reject: `advances.decide` (Accounts; Super Admin and HR Admin have it too)

## Code

- `app/Http/Controllers/Operations/AdvanceController.php`
- `app/Notifications/AdvanceNotice.php`
- Routes: `advances.index`, `advances.store`, `advances.update`, `advances.destroy`, `advances.close`, `advances.approve`, `advances.reject`
- Salary deduction still uses `status = active` in `SalarySheetBuilder`

## Menu

Grants: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Chrome only. Amounts stay on solid surfaces. Under 700px, rows scroll inside `.tw` or stack without dropping the amount or the decision actions.
