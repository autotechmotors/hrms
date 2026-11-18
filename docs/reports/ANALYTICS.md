# Reports

The reports screen stays a read of data the user may already see. Do not add a report that ignores `AccessScope`.

## Counting

The month filter stays on the page. Counts are for active people in scope, for that calendar month.

- Present is a working day with a clock in and a clock out.
- Absent is a working day with no punch. That cuts a full day of pay.
- Paid holidays are company public holidays and one person’s paid day. They are not leave.
- Late and left-early minutes use that person’s day or night window, then the Monday–Saturday minute rate.
- Salary cutoff is those minutes plus absent and unpaid leave, in rupees. It uses `employee_salaries.gross_monthly` divided by the real days in the month.
- A saved payroll run is shown beside these counts. Calculating payroll again is what makes the run match the report.

## Code

- `app/Http/Controllers/Operations/ReportController.php`
- Route: `reports.index`

## Menu

View uses `reports.view`. HR Admin and Director keep it inside their current scope. Manager does not gain it unless `docs/roles/NAV-PERMISSIONS.md` is changed first.

## Visual

Charts and tables sit on solid cards. Under 700px, tables scroll inside `.tw`. Chrome follows `docs/prompts/HRMS-LIQUID-GLASS.md`.
