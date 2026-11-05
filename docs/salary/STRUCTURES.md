# Salary structures

Salary heads, structures, and employee assignment stay. Payroll must keep reading the same structure records.

## Code

- `app/Http/Controllers/Operations/SalaryController.php`
- Routes: `salary.index`, `salary.heads.store`, `salary.structures.store`, `salary.assign`

## Menu

View uses `salary.view`. Head, structure, and assign writes use `salary.manage`. Grants follow roles that can open Salary today. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Chrome only. Do not change head or structure field names that payroll compute already reads.
