# Departments

Department create, edit, and delete stay company-scoped. A department that employees, or another record, still use cannot be deleted. Employee placement still stores `department_id`. Manager visibility still uses department scope plus the reporting tree.

## Code

- `app/Http/Controllers/Organization/DepartmentController.php`
- Route resource: `departments` except show
- Permissions: `departments.view`, `departments.create`, `departments.edit`, `departments.delete`

## Leave unchanged

Branch and location modules were removed. Do not add them back on the department screen.

Menu visibility for this item already uses `departments.view`. Keep that. Role map: `docs/roles/NAV-PERMISSIONS.md`.
