# Audit logs

Administration → Audit Logs. The list is read-only. It shows who changed a record, the event, and when.

Super Admin sees every company. Anyone else with `audit_logs.view` sees only the companies in their scope. The list can be limited by event and by a from/to date. It stays on the page when those filters change.

Do not add edit or delete. Employee import, export, documents, block, and exit already write here. See `docs/prompts/HRMS-FREEZE.md`.

## Code

- `app/Http/Controllers/Admin/AuditLogController.php`
- `app/Models/AuditLog.php`
- `resources/views/admin/audit/index.blade.php`
- Route: `audit-logs.index`
