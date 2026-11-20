# Freeze

These already work. Layout, PWA, and menu work must not change their behaviour.

- Sign-in, logout, throttles, and authenticator MFA. Roles that require MFA still require it. Note: `docs/auth/SIGN-IN.md`.
- `AccessScope`: company, department, and employee scopes are additive for payroll, accounts, and device admin. Everyone else who is linked to a person sees that person and the full reporting tree below them, not only the next row. Super Admin bypasses scope. HR Admin sees the company. Empty scope returns no rows (`whereRaw('1 = 0')`), never the whole company. Tree: `docs/employees/PARENT-ROLE.md`.
- Attendance calculation (`AttendanceCalculator`), punch ingest, recalculate, rules, and selfie storage. Office-work gate pass hours are the exception in `docs/attendance/GATE-PASS.md`. Note: `docs/attendance/REGISTER.md`.
- Device bridges: `device-events` and Hikvision token routes. Do not change tokens, throttles, CSRF exceptions, or payload handling. Note: `docs/devices/TERMINALS.md`.
- Salary sheets, advances, and bank batches, including the permission slugs they already have. The Payroll run screen and the Salary Structures screen stay removed. Day pay and cutoff stay in `docs/prompts/HRMS-PAY-DAYS.md`: a salary sheet divides by the calendar length of the month and cuts late or early minutes from that date’s clock window. Do not change that file’s rules from this freeze list.
- Employee import, export, documents, credentials, bank accounts, block toggle, exit, custom fields, and the audit log.
- Role slugs: `super-admin`, `hr-admin`, `payroll-admin`, `accounts`, `manager`, `head`, `director`, `device-administrator`, `employee`. The `employee` role is the self-service login. It sees that person's own row and anyone assigned under them.
- Route names in `routes/web.php`. Add a route only when the module note says the gap has no route. Do not rename existing routes.
- CSS class names already in use: `.sb`, `.mn`, `.tb`, `.pg`, `.card`, `.btn`, `.att-*`, `.el-*`, `.ed-*`. Extend them. Do not rename them.

A visual change that needs a server change keeps the same response shape and the same query scope.

## Code map

| Area | Path |
|---|---|
| Layout | `resources/views/layouts/app.blade.php` |
| Nav | `resources/views/partials/nav.blade.php` |
| Styles | `public/assets/css/app.css` (raise the `?v=` query) |
| Script | `public/assets/js/app.js` |
| Permissions | `app/Support/PermissionCatalog.php`, `database/seeders/RoleAndPermissionSeeder.php` |
| Scope | `app/Services/AccessScope.php` |
| Mark | `docs/brand/app-icon.png` |
