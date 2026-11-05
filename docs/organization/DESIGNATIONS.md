# Designations

Designations and employee types stay. The nav item Designations still covers both `designations.*` and `employee-types.*`.

## Code

- `app/Http/Controllers/Organization/DesignationController.php`
- `app/Http/Controllers/Organization/EmployeeTypeController.php`
- Permissions: `designations.view|create|edit|delete` and `employee_types.view|create|edit|delete`

## Leave unchanged

An employee's designation and category on the profile keep reading these records. See `docs/employees/PROFILE.md`.
