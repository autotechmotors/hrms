# Reports

The reports screen stays a read of data the user may already see. Do not add a report that ignores `AccessScope`.

## Code

- `app/Http/Controllers/Operations/ReportController.php`
- Route: `reports.index`

## Menu

View uses `reports.view`. HR Admin and Director keep it inside their current scope. Manager does not gain it unless `docs/roles/NAV-PERMISSIONS.md` is changed first.

## Visual

Charts and tables sit on solid cards. Under 700px, tables scroll inside `.tw`. Chrome follows `docs/prompts/HRMS-LIQUID-GLASS.md`.
