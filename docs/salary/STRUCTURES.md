# Salary structures

The Salary Structures screen is gone. A person’s monthly amount is saved on Hours & pay. Extra salary-head deductions are not calculated. An advance is the deduction, from Advances & Deductions, and it is taken when a salary sheet that covers that date is approved.

## Code

- `app/Http/Controllers/Operations/HoursPayController.php` `storeEmployeeSalary`
- Route: `hours-pay.employees.salary`

## Menu

Hours & pay uses `salary.view` to open and `salary.manage` to save the amount. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

The amount and the start date are the fields. There is no structure picker.
