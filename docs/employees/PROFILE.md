# Employee profile

Keep the current employee record working: identity, email, phone, department, designation, status, manager, joining, probation, notice, photograph, custom fields, movement history, documents, bank accounts, device credentials, block, exit, import, and export. The payout account on this profile is the person’s account for salary. Company bank accounts stay on `/bank`: `docs/bank/TRANSFERS.md`.

Super Admin and HR Admin can edit, update, and delete a profile. Anyone else can open only their own row and the people assigned under them, and cannot edit those profiles.

Do not add a second employee screen. Fill the holes on the existing Identity, Contact, and Placement cards.

## Fields on the form

These are on the employee form and are saved with the employee.

- `alternate_phone`
- `confirmation_date`
- `address`, `city`, `state`, `postal_code`

## Code

- `resources/views/employees/form.blade.php`
- `resources/views/employees/show.blade.php`
- `app/Models/Employee.php`
- `app/Http/Requests/EmployeeRequest.php`
- `app/Services/Employees/EmployeeService.php`

Import and export column lists stay stable. Add a column only if a current import file would otherwise drop a value the form can now save. Do not rename existing columns.

## Attendance on the profile

One card on the employee profile, for that person only. Today first, then earlier days. Each row: in, out, worked hours, status. Link to the existing `attendance.show` route. Show it only with `attendance.view` and `AccessScope::canAccessEmployee`. Day rules: `docs/attendance/REGISTER.md`.

Who may open the employee list: `docs/roles/NAV-PERMISSIONS.md`.

The list has a Salary action beside View, Edit, and Delete when the person has `salary.view`. It opens that person’s Hours & pay page. The profile also has the Day and night month card. Rules: `docs/shifts/SCHEDULE.md`.
