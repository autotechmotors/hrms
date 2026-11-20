# Financial years

Financial year create, edit, and delete stay. A year is a company date range. Only one year for that company can be current. Salary sheets, Hours & pay, and reports do not read this table. Do not drop the records.

## Code

- `app/Http/Controllers/Organization/FinancialYearController.php`
- Route resource: `financial-years` except show, parameter `financialYear`
- Permissions: `financial_years.view|create|edit|delete`

## Visual

Chrome only. Date fields stay on the existing date control.
