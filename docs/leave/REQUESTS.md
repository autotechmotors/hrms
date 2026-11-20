# Leave requests

A leave type can be saved for one department, several departments, or every department when none is chosen. Several departments create one type for each. On Record leave, a department’s type is listed only after an employee from that department is chosen. A type with no department is listed for every employee. It is either salary cutoff or no cutoff. Yearly caps, and counting weekly offs or holidays as leave, are not on the form. Weekly offs and company holidays are never counted as leave days.

An employee with `leave.view` can request leave for themselves. HR with `leave.decide` can record leave for a person they can see. The request is pending until Super Admin, HR Admin, or a person above them in the reporting tree approves it. Rejecting requires a reason. Nobody can decide their own request except Super Admin. A parent can approve or reject, and cannot delete someone else’s request. The owner can delete their own pending request. Editing a request needs `leave.decide` and makes it pending again.

A request needs a reason. The end date cannot be before the start. Dates that overlap a pending or approved request for that person do not save. A range that is only weekly offs and company holidays does not save, because those days are not counted. A type for another department is rejected. A yearly cap is checked only when that type still has `annual_days` above zero. The form does not set a cap.

Deleting a type removes it from the type list and from Record leave. If a request already uses it, the type is closed: past leave keeps the name, and the same code can be used again.

Approved cutoff leave cuts that day’s pay. Approved leave with no cutoff pays the day. Attendance status `on_leave` still comes from an approved request when that day is recalculated. Pay reads the approved request directly.

## Code

- `app/Http/Controllers/Operations/LeaveController.php`
- Routes: `leave.index`, `leave.types.store`, `leave.types.update`, `leave.types.destroy`, `leave.store`, `leave.update`, `leave.destroy`, `leave.decide`
- Screen layout and small-screen rules: `docs/leave/SCREEN.md`

## Menu

View uses `leave.view`. Type setup and decide use `leave.decide`. Grant them so current HR access does not shrink. Map: `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Same liquid-glass chrome. Under 700px the request table is replaced by stacked cards, and the search stays on the page. Decision actions stay available to roles that can decide today. Layout detail is in `docs/leave/SCREEN.md`.
