# User accounts

There is no Users item in the menu. `/users` redirects to Employees. A login is created from the employee form when that person has an email and a staff role. The employee role can sign in for their own row. A user with no role on the employee does not get a new login.

Company and department scope still decide which employees that login can see. Do not put data scope on the role alone. Only a Super Admin can assign or remove the Super Admin role.

## Code

- `app/Services/Employees/EmployeeService.php` creates or updates the login from the employee role
- `app/Services/AccessScope.php`
- `routes/web.php` redirects `users` and `users/{any}` to `/employees`

## Menu

Map: `docs/roles/NAV-PERMISSIONS.md`. Do not add a second user screen.

## Visual

The user form may use liquid-glass chrome. Scope checkboxes and role assignment stay in the same request fields.
