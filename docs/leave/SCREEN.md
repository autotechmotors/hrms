# Leave screen

Leave is one page. Types sit above the request list. Record leave, approve, and reject stay on that page. Decision rules are in `docs/leave/REQUESTS.md`. Salary day rows that read approved leave are in `docs/salary/SHEETS.md`.

## Files

| File | Owns |
|---|---|
| `app/Http/Controllers/Operations/LeaveController.php` | List, type save, request save, approve, reject, department match |
| `app/Models/LeaveType.php` | Department, cutoff (`is_paid` false means cutoff), active flag |
| `app/Models/LeaveRequest.php` | Dates, days, status, reject note |
| `app/Notifications/LeaveNotice.php` | Request, approve, and reject notices |
| `resources/views/operations/leave/index.blade.php` | Page header and the three modals |
| `resources/views/operations/leave/_body.blade.php` | Counts, type cards, filters, table, phone cards |
| `resources/views/operations/leave/_actions.blade.php` | Approve, reject, edit, and delete on a request |
| `resources/views/operations/leave/_retain.blade.php` | Keeps the current filters after a save |
| `routes/web.php` | `leave.index`, `leave.types.store`, `leave.types.update`, `leave.types.destroy`, `leave.store`, `leave.update`, `leave.destroy`, `leave.decide` |
| `public/assets/css/app.css` | `.lv-*` layout. Cache query is `app.css?v=123` |
| `app/Services/Operations/PayDayMath.php` | Approved leave on the salary day |
| `resources/views/operations/salary-sheets/show.blade.php` | Day grid on screen |
| `resources/views/operations/salary-sheets/print.blade.php` | Same rows on the PDF |
| `app/Services/Operations/SalarySheetExporter.php` | Same rows in Excel |

## Workflow on the page

1. HR adds a type for one department, several departments, or every department when the department field is empty. Several departments create one card each. Cutoff or no cutoff is the only pay choice.
2. The type cards show the code, name, cutoff, and department. Edit and delete are the pen and trash on the right of the card.
3. Delete removes a type that no request uses. A type that a request uses is closed: it leaves the cards and Record leave, and the old request keeps the name.
4. Record leave asks for the employee first. The type list then shows that department’s types, plus any type marked for every department. Another department’s type is not listed and is rejected if it is posted.
5. The employee can request only their own leave. Super Admin, HR Admin, and that employee’s parent approve it. Reject needs a reason. The employee cannot decide their own request.
6. Approve or reject stays on the row. Edit by HR makes the request pending again. The owner can delete their own pending request.
7. Filters for department, type, status, and employee search replace only `#leaveBody`. The type list inside Record leave is rebuilt from the same response.

## Layout

- Counts are four cards. At 900px they become two columns. At 700px they become one column.
- Type cards wrap. At 700px they are one column. The pen and trash stay on the right.
- From 701px up, requests are a table inside `.tw`, which scrolls sideways if the row is wider than the page.
- At 700px the table is hidden. Each request is a card: person, status, type, dates, reason, then approve, reject, edit, and delete.
- Search stays visible at 700px. The department, type, and status filters each take a full row.
- The modals stack the date fields at 700px. Department on a type is a multi-select. Empty means every department.
- Dark mode uses the same card shape with a solid dark fill. Status colours stay amber, green, and red.

## Not on this screen

Yearly day caps, counting a weekly off as leave, and counting a company holiday as leave are not fields. Weekly offs and company holidays are skipped when the days are counted. A salary sheet already generated does not gain the leave row until it is generated again.
