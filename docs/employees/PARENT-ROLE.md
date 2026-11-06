# Parent and role

The employee create and edit form stores one role from the same list as Roles. That saved role is what the Parent box uses.

- `It has parent` on the role makes Parent required for this person.
- `It has child` on the role puts this person in other people’s Parent list.
- A person with no role, and no login user on a child role, does not appear in Parent.
- Login roles on Users stay separate. A linked user’s role still counts for the same two checks.

Code: `employees.role_id`, `Employee::needsReportingManager()`, `Employee::scopeCanManagePeople()`, `resources/views/employees/form.blade.php`.
