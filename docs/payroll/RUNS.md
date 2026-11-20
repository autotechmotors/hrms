# Payroll runs

The Payroll screen is gone. Salary sheets own the pay. The day-pay math stays in `docs/prompts/HRMS-PAY-DAYS.md`.

A month divides the monthly salary by the real calendar length of that month. It does not divide by working days. Sunday 08:00–13:00 pays a full day when that window is completed. Late and early minutes are cut at the Monday–Saturday minute rate. A missing punch cuts that day. A public holiday and a person’s own paid day pay the full day. A cutoff holiday cuts that full day for everyone. Advances apply after that earned total when the salary sheet is approved. An approved sheet is not recalculated.

## Code

- `app/Services/Operations/PayDayMath.php`
- `app/Services/Operations/SalarySheetBuilder.php`
- Routes: `salary-sheets.index`, `salary-sheets.store`, `salary-sheets.approve`

## Menu

Hours & pay, Salary sheets, and Advances stay in the Payroll menu. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Salary sheet money figures stay on solid cards. Under 700px, tables scroll inside `.tw`.
