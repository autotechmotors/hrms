# Parent and role

The employee create and edit form stores one role from the same list as Roles. That saved role is what the Parent box uses.

- `It has parent` on the role makes Parent required for this person.
- `It has child` on the role puts this person in other people’s Parent list.
- A person with no role, and no login user on a child role, does not appear in Parent.
- Login roles on Users stay separate. A linked user’s role still counts for the same two checks.

Code: `employees.role_id`, `Employee::needsReportingManager()`, `Employee::scopeCanManagePeople()`, `resources/views/employees/form.blade.php`.

The reporting line is the parent each person is assigned, at every level. One example is Director, then Head, then Manager, then the people under that manager: employee, helper, or any other role. The role names are not fixed. Any active role with `It has child` can be a parent. Any active role with `It has parent` must be assigned under one.

A login linked to a person sees their own row and every person below them, not only the next row. When the manager is the last parent, the head and the director above that manager see the same people the manager sees. A person does not see their own parent, a sibling, or a person in another branch. A company scope does not add the rest of the company.

Super Admin and HR Admin see the company. Payroll Admin, Accounts, and Device Administrator keep their company or department scope. More than one role adds those permissions together. Profile edit, update, and delete stay with Super Admin and HR Admin (`employees.edit`, `employees.delete`). A parent can open a profile under them and cannot change it. The same tree limits attendance, leave, gate pass, and the dashboard.
