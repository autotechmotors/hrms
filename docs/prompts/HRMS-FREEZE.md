# Freeze

These already work. Layout, PWA, and menu work must not change their behaviour.

- Sign-in, logout, throttles, and authenticator MFA. Roles that require MFA still require it. Note: `docs/auth/SIGN-IN.md`.
- `AccessScope`: company, department, and employee scopes are additive. A manager also sees direct reports (`reporting_manager_id`). A user always sees their own employee row. Super Admin bypasses scope. Empty scope returns no rows (`whereRaw('1 = 0')`), never the whole company.
- Attendance calculation (`AttendanceCalculator`), punch ingest, recalculate, rules, and selfie storage. Note: `docs/attendance/REGISTER.md`.
- Device bridges: `device-events` and Hikvision token routes. Do not change tokens, throttles, CSRF exceptions, or payload handling. Note: `docs/devices/TERMINALS.md`.
- Payroll compute, approve, lock, reopen, salary structures, advances, and bank batches, including the permission slugs they already have.
- Employee import, export, documents, credentials, bank accounts, block toggle, exit, custom fields, and the audit log.
- Role slugs: `super-admin`, `hr-admin`, `payroll-admin`, `accounts`, `manager`, `head`, `director`, `device-administrator`, `employee`. The `employee` role is the self-service login. It sees only that person's own row.
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
