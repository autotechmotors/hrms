# Employee profile

Keep the current employee record working: identity, email, phone, department, designation, status, manager, joining, probation, notice, photograph, custom fields, movement history, documents, bank accounts, device credentials, block, exit, import, and export. Access Credentials asks which face terminal to use, then sends the face, finger, card, or password only to that machine. The payout account on this profile is the person’s account for salary. Company bank accounts stay on `/bank`: `docs/bank/TRANSFERS.md`.

Super Admin and HR Admin can edit, update, and delete a profile. Anyone else can open only their own row and the people assigned under them, and cannot edit those profiles.

Deleting a person removes their related rows across the system: attendance, punches, credentials, documents and files, bank accounts, exits, employment events, leave, gate passes, advances, salaries, hours, shift spans, holidays, salary-sheet lines, payroll items, payment lines, device links, photograph, and their staff login (roles, scopes, notifications). People who reported to them keep their rows with an empty parent. The last active Super Admin cannot be deleted. Device faces are dropped after the database commit (`EmployeeRemover`).

Do not add a second employee screen. Fill the holes on the existing Identity, Contact, and Placement cards.

## Fields on the form

These are on the employee form and are saved with the employee.

- `alternate_phone`
- `confirmation_date`
- `address`, `city`, `state`, `postal_code`
- `salary_type` — `hours` (Hours based) or `piece` (Piece based). Required on create and edit. Piece based people stay on attendance only and are not put on salary sheets. Hours based people need a monthly amount on Hours & pay before generate.

## Code

- `resources/views/employees/form.blade.php`
- `resources/views/employees/show.blade.php`
- `app/Models/Employee.php`
- `app/Http/Requests/EmployeeRequest.php`
- `app/Services/Employees/EmployeeService.php`
- `app/Services/Employees/EmployeeRemover.php`

Import and export column lists stay stable. Add a column only if a current import file would otherwise drop a value the form can now save. Do not rename existing columns.

## Attendance on the profile

One card on the employee profile, for that person only. Today first, then earlier days. Each row: in, out, worked hours, status. Link to the existing `attendance.show` route. Show it only with `attendance.view` and `AccessScope::canAccessEmployee`. Day rules: `docs/attendance/REGISTER.md`.

Who may open the employee list: `docs/roles/NAV-PERMISSIONS.md`.

The list has a Salary action beside View, Edit, and Delete when the person has `salary.view`. It opens that person’s Hours & pay page. Working times and Day and night are not on the employee profile or edit form. They stay on Hours & pay. Rules: `docs/shifts/SCHEDULE.md` and `docs/salary/HOURS-AND-PAY.md`.
