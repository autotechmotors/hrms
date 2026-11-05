# Leave requests

Leave types, leave requests, edits, deletes, and approve or reject stay. Attendance status `on_leave` still comes from an approved request. Do not change that link.

## Code

- `app/Http/Controllers/Operations/LeaveController.php`
- Routes: `leave.index`, `leave.types.store`, `leave.store`, `leave.update`, `leave.destroy`, `leave.decide`

## Menu

View uses `leave.view`. Type setup and decide use `leave.decide`. Grant them so current HR access does not shrink. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Same liquid-glass chrome. Under 700px the list becomes stacked cards or a scroll inside `.tw`. Decision actions stay available to roles that can decide today.
