# Role and data separation

Access has two layers. Both must pass.

1. **Permission** — the role’s slugs. Menu and routes use `hasPermission` / `hasAnyPermission`. Super Admin bypasses the permission table. HR Admin is granted every slug by the seeder.
2. **Scope** — `AccessScope` and `Employee::visibleTo`. Super Admin sees every company. HR Admin sees the company on their scope. Payroll Admin, Accounts, and Device Administrator use company or department scope. Every other role linked to a person sees that person and the reporting tree under them.

## Approve without decide slugs

Manager, Head, Director, and a parent with the Employee role approve leave and gate passes for people under them through the reporting tree. They do **not** get `leave.decide` or `gate_passes.decide`. Those slugs also open leave-type manage and delete of other people’s leave. Day and night dates use the same parent rule (`docs/shifts/SCHEDULE.md`), not `shifts.manage`.

## Must not leak

- Attendance detail, punch photos, and face credentials use the same `visibleTo` guard as the employee list.
- Day/night month HTML is only for people the login can already see.
- Accounts may approve or reject salary and advances. They may not change net pay or create an advance.
- Only a Super Admin may assign or remove the Super Admin role. The last active Super Admin must keep it. HR cannot edit the Super Admin role record.
- New staff logins created from an employee email get a random password, not a shared default.

## Role grants

See `docs/roles/NAV-PERMISSIONS.md` and `database/seeders/RoleAndPermissionSeeder.php`. Custom roles stay flexible on the Roles screen; system Super Admin permissions stay locked.
