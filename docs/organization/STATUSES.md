# Employment statuses

Status names and the exit flag stay. A status that an employee still uses cannot be deleted. An exit status keeps that person out of the report headcount and the dashboard “still employed” counts. The employee profile badge still treats an exit status as red and a probation name as amber.

## Code

- `app/Http/Controllers/Organization/EmploymentStatusController.php`
- Route resource: `employment-statuses` except show
- Permissions: `employment_statuses.view|create|edit|delete`

## Leave unchanged

Do not rename seeded statuses in a layout pass. Exit flow on the employee stays in `docs/employees/PROFILE.md`.
