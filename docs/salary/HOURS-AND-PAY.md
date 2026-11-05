# Hours and pay

One screen sets the expected day length and the monthly salary. Attendance punches stay the record of real in and out. Payroll still reads `employee_salaries` and does not use these hours in the payslip math.

## How it reads

1. A department has one hours-a-day value. People in that department use it.
2. A person can keep the department hours, or set their own hours.
3. Monthly salary is set on that person: structure, amount, and the date it starts. Payroll uses the row already effective in the pay month.

## Code

- `app/Http/Controllers/Operations/HoursPayController.php`
- `app/Services/Operations/WorkHourResolver.php`
- Routes: `hours-pay.index`, `hours-pay.show`, `hours-pay.departments.store`, `hours-pay.employees.hours`, `hours-pay.employees.salary`

## Menu

The Payroll item "Hours & pay" uses `salary.view`. Saves use `salary.manage`. Rows stay inside `visibleTo`.

Salary heads and structures stay on `docs/salary/STRUCTURES.md`.
