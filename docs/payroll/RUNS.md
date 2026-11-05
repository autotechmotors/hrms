# Payroll runs

Compute and approve stay. Existing slugs stay: `payroll.process`, `payroll.approve`, `payroll.lock`, `payroll.reopen`. Do not change payslip math, lock rules, or reopen rules.

## Code

- `app/Http/Controllers/Operations/PayrollController.php`
- Routes: `payroll.index`, `payroll.store`, `payroll.show`, `payroll.compute`, `payroll.approve`

## Menu

The Payroll link uses `payroll.view` once that slug exists. Process and approve buttons stay behind the slugs they use now. HR Admin does not gain lock or reopen. Payroll Admin does not gain employee create. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Run list and run detail follow liquid-glass chrome. Money figures stay on solid cards. Under 700px, tables scroll inside `.tw`.
