# Payroll runs

Compute and approve stay. Existing slugs stay: `payroll.process`, `payroll.approve`, `payroll.lock`, `payroll.reopen`. Lock and reopen rules stay. The day-pay math is the exception in `docs/prompts/HRMS-PAY-DAYS.md`.

A payroll month divides the monthly salary by the real calendar length of that month. It does not divide by working days. Sunday 08:00–13:00 pays a full day when that window is completed. Late and early minutes are cut at the Monday–Saturday minute rate. A missing punch cuts that day. A public holiday and a person’s own paid day pay the full day. The run stores each cut on the payroll item: date, window, in, out, minutes, rupees, and the reason. Structure deductions and the month’s advances still apply after that earned total. An approved run is not recalculated.

## Code

- `app/Http/Controllers/Operations/PayrollController.php`
- Routes: `payroll.index`, `payroll.store`, `payroll.show`, `payroll.compute`, `payroll.approve`

## Menu

The Payroll link uses `payroll.view` once that slug exists. Process and approve buttons stay behind the slugs they use now. HR Admin does not gain lock or reopen. Payroll Admin does not gain employee create. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Run list and run detail follow liquid-glass chrome. Money figures stay on solid cards. Under 700px, tables scroll inside `.tw`.
