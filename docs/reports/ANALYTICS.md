# Reports

The reports screen stays a read of data the user may already see. Do not add a report that ignores `AccessScope`.

## Counting

The month filter and the department filter stay on the page. Counts are for people in `visibleTo` who are not on an exit status, for that calendar month. Days before joining are skipped.

- Present is a working day that the pay math marks P.
- Absent is a working day the pay math marks absent. That cuts a full day of pay.
- A Sunday that this shift does not work is counted as a night-Sunday off, not as present and not as absent. It pays the day.
- Paid holidays are company public holidays and one person’s paid day. A cutoff holiday is not a paid holiday. It cuts that day. They are not leave.
- Approved leave is counted by type. Paid leave is separate from unpaid leave.
- Late and left-early minutes use that person’s day or night window for that date, then the Monday–Saturday minute rate.
- Salary cutoff is those minutes plus absent and unpaid leave, in rupees. The report uses one amount: the latest `employee_salaries.gross_monthly` whose start date is on or before the last day of the month, divided by the real days in the month. A salary sheet is different: each day uses the amount already effective on that day. See `docs/prompts/HRMS-PAY-DAYS.md`.
- The attention list is the 20 people with the highest cutoff who also have an absence, a late minute, or an early minute.
- There is no payroll run on this screen.

## Code

- `app/Http/Controllers/Operations/ReportController.php`
- Route: `reports.index`

## Menu

View uses `reports.view`. HR Admin and Director keep it inside their current scope. Manager does not gain it unless `docs/roles/NAV-PERMISSIONS.md` is changed first.

## Visual

Charts and tables sit on solid cards. Under 700px, tables scroll inside `.tw`. Chrome follows `docs/prompts/HRMS-LIQUID-GLASS.md`.
